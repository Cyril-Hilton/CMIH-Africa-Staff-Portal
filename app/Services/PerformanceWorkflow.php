<?php

namespace App\Services;

use App\Models\PerformanceEvent;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceOutbox;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerformanceWorkflow
{
    public static function record(string $type, int $id, ?int $actor, string $action, array $before, array $after, string $reason): void
    {
        PerformanceEvent::create(['entity_type' => $type, 'entity_id' => $id, 'actor_id' => $actor, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason]);
    }

    public static function notify(int $user, string $key, string $title, string $message, ?string $url = null): void
    {
        if ($user <= 0) {
            return;
        }
        PerformanceOutbox::firstOrCreate(['dedupe_key' => hash('sha256', $key.':'.$user)], ['user_id' => $user, 'title' => $title, 'message' => $message, 'url' => $url ?? route('portal.performance.index')]);
    }

    public function availableActions(WeeklyConsolidatedItem $item, User $actor): array
    {
        $owner = (int) $item->lead_staff_id === $actor->id;
        $manager = PerformanceAccess::manages($actor, $item->department);
        $reviewer = ! $owner && ((int) $item->reviewer_id === $actor->id || $manager);
        $canVerify = $reviewer && $actor->isActive() && ($manager || (int) $item->leadStaff?->line_manager_id === $actor->id);
        $actions = match ($item->workflow_state) {
            'assigned' => $owner ? ['accept' => 'Accept Assignment'] : [],
            'accepted' => $owner ? ['execute' => 'Begin Execution'] : [],
            'executing' => $owner ? ['submit' => 'Submit for Review'] : [],
            'review' => $reviewer ? ['complete' => 'Mark Reviewed / Completed', 'return' => 'Return for Correction'] : [],
            'completed' => $reviewer ? array_merge($canVerify ? ['verify' => 'Manager Sign-off / Verify Quality'] : [], ['return' => 'Return for Correction']) : [],
            'verified' => array_merge($reviewer ? ['close' => 'Close'] : [], $manager ? ['reopen' => 'Reopen'] : []),
            'closed' => $manager ? ['reopen' => 'Reopen'] : [],
            default => [],
        };

        return $actions;
    }

    public function transition(WeeklyConsolidatedItem $record, User $actor, string $action, int $revision, string $reason, ?float $quality = null): WeeklyConsolidatedItem
    {
        return DB::transaction(function () use ($record, $actor, $action, $revision, $reason, $quality) {
            $item = WeeklyConsolidatedItem::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless(PerformanceAccess::view($actor, $item), 403);
            abort_if($revision !== (int) $item->revision, 409, 'This commitment changed. Refresh before submitting.');
            $before = $item->getAttributes();
            $state = $item->workflow_state;
            $owner = (int) $item->lead_staff_id === $actor->id;
            $reviewer = ((int) $item->reviewer_id === $actor->id || PerformanceAccess::manages($actor, $item->department)) && ! $owner;
            $transitions = ['accept' => ['assigned', 'accepted'], 'execute' => ['accepted', 'executing'], 'submit' => ['executing', 'review'], 'complete' => ['review', 'completed'], 'verify' => ['completed', 'verified'], 'close' => ['verified', 'closed']];
            if ($action === 'return') {
                abort_unless($reviewer && in_array($state, ['review', 'completed'], true), 403);
                $next = 'executing';
                $item->completed_at = null;
                $item->submitted_at = null;
            } elseif ($action === 'reopen') {
                abort_unless(PerformanceAccess::manages($actor, $item->department) && in_array($state, ['verified', 'closed'], true), 403);
                $next = 'assigned';
                $item->completed_at = null;
                $item->verified_at = null;
                $item->closed_at = null;
                $item->quality_score = null;
            } else {
                if (! isset($transitions[$action]) || $state !== $transitions[$action][0]) {
                    throw ValidationException::withMessages(['action' => 'This transition is not available from the current state.']);
                }
                abort_unless(in_array($action, ['accept', 'execute', 'submit']) ? $owner : $reviewer, 403);
                $next = $transitions[$action][1];
            }
            if ($action === 'execute' || $action === 'close') {
                $blocked = \App\Models\PerformanceDependency::where('downstream_id', $item->id)->whereHas('upstream', fn ($q) => $q->whereNotIn('workflow_state', ['verified', 'closed']))->exists();
                if ($blocked) {
                    throw ValidationException::withMessages(['action' => 'An upstream dependency has not been verified.']);
                }
            }
            if ($action === 'submit' && $item->actual_value === null) {
                throw ValidationException::withMessages(['actual_value' => 'Record the actual result before submitting.']);
            }
            if ($action === 'submit' && $item->performanceKpi?->evidence_required && ! $item->evidence_url && ! PerformanceEvidence::where('commitment_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Attach evidence before submitting this commitment.']);
            }
            if ($action === 'verify') {
                $lead = User::find($item->lead_staff_id);
                abort_unless($actor->isActive() && $lead && ! $owner && ((int) $lead->line_manager_id === $actor->id || PerformanceAccess::manages($actor, $item->department)), 403, 'A line manager or department head must verify completed work.');
            }
            if ($action === 'verify' && ($quality === null || $quality < 0 || $quality > 100)) {
                throw ValidationException::withMessages(['quality_score' => 'Record a quality score before verification.']);
            }
            $item->workflow_state = $next;
            $item->status = in_array($next, ['completed', 'verified', 'closed']) ? 'Done' : ($next === 'assigned' ? 'Planned' : 'In Progress');
            if ($action === 'accept') {
                $item->accepted_at = now();
            }
            if ($action === 'submit') {
                $item->submitted_at = now();
            }
            if ($action === 'complete') {
                $item->completed_at = now();
            }
            if ($action === 'verify') {
                $item->verified_at = now();
                $item->quality_score = $quality;
            }
            if ($action === 'close') {
                $item->closed_at = now();
            }
            $item->progress_percent = in_array($next, ['completed', 'verified', 'closed']) ? 100 : $item->progress_percent;
            $item->revision++;
            $item->updated_by = $actor->id;
            $item->save();
            self::record('commitment', $item->id, $actor->id, $action, $before, $item->getAttributes(), $reason);
            self::notify((int) ($next === 'review' ? $item->reviewer_id : $item->lead_staff_id), "transition:{$item->id}:{$item->revision}", 'Commitment '.$next, 'WCT-'.$item->id.' · '.$item->campaign_name.' · '.$reason);

            return $item;
        });
    }
}
