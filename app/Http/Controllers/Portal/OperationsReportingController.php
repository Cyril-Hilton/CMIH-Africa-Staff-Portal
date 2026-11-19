<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\OperationsReportSubmission;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\OperationsReporting;
use App\Services\OperationsTracker;
use App\Services\PerformanceWorkflow;
use App\Support\ReportRichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsReportingController extends Controller
{
    public function save(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(OperationsReporting::member($actor), 403);
        $manager = OperationsReporting::manager($actor);
        $moduleEntry = $request->boolean('module_entry');
        $includePrivate = $manager && $moduleEntry;
        $input = $request->validate([
            'kind' => ['required', Rule::in(array_keys(OperationsReporting::schemas()))],
            'source' => 'nullable|integer',
            'submission' => 'nullable|integer',
            'revision' => 'required_with:submission|nullable|integer',
            'source_version' => 'required_with:source|nullable|string|size:64',
            'title' => 'required|string|max:255',
            'client' => 'nullable|string|max:255',
            'project' => 'nullable|string|max:255',
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')->whereIn('department', User::departmentAliases(OperationsReporting::DEPARTMENT)))],
            'week' => 'required|date_format:Y-m-d|before_or_equal:9999-12-25',
            'status' => ['required', Rule::in(array_keys(OperationsReporting::STATUSES))],
            'evidence_url' => 'nullable|url:http,https|max:2000',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'handover_id'=>'nullable|integer|exists:operations_handovers,id',
            'warehouse_assets_present'=>'nullable|boolean',
            'warehouse_assets'=>'nullable|array|max:500',
            'warehouse_assets.*.selected'=>'nullable|boolean',
            'warehouse_assets.*.asset_id'=>'required|integer|exists:assets,id',
            'warehouse_assets.*.quantity'=>'nullable|integer|min:1|max:9999999',
            'crm_project_id' => ['nullable', 'integer', Rule::exists('crm_pipeline_projects', 'id')->where('source_period_confirmed', true)],
            'activation_id' => ['nullable', 'integer', Rule::exists('performance_registers', 'id')
                ->where('department', OperationsReporting::DEPARTMENT)->where('kind', 'activation')->whereNotNull('approved_at')],
            'collaborators_present' => 'nullable|boolean',
            'collaborator_ids' => 'nullable|array|max:50',
            'collaborator_ids.*' => 'required|integer|distinct',
            'data' => 'nullable|array',
        ], [], ['data' => 'report details']);

        $rules = [];
        $schema = OperationsReporting::fields($input['kind'], $includePrivate);
        foreach ($schema as $field => $type) {
            $rules['data.'.$field] = match ($type) {
                'date' => 'nullable|date_format:Y-m-d',
                'number', 'money' => $field === 'progress_percent' ? 'nullable|numeric|between:0,100' : 'nullable|numeric|min:0|max:99999999999999',
                default => 'nullable|string|max:15000',
            };
        }
        $data = array_intersect_key($request->validate($rules)['data'] ?? [], $schema);
        app(OperationsTracker::class)->validate($input['kind'], $data);
        foreach ($data as $field => $value) {
            if (is_string($value) && $schema[$field] === 'text' && ! isset(config('operations_tracker.options')[$field])) {
                $data[$field] = ReportRichText::html($value);
            }
        }
        if ($input['kind'] === 'activation' && ! empty($input['campaign_id'])) {
            $data['campaign_id'] = (int) $input['campaign_id'];
        }

        $linkField = $input['kind'] === 'activation' ? 'crm_project_id' : 'activation_id';
        if (array_key_exists($linkField, $input)) {
            $data[$linkField] = ! empty($input[$linkField]) ? (int) $input[$linkField] : null;
        }

        if(!empty($input['warehouse_assets_present']))$data['warehouse_assets']=app(\App\Services\OperationsAssetRequests::class)->validate($input['warehouse_assets']??[]);

        $entry = DB::transaction(function () use ($input, $data, $actor, $manager, $includePrivate, $moduleEntry) {
            $entry = empty($input['submission']) ? new OperationsReportSubmission : OperationsReportSubmission::lockForUpdate()->findOrFail($input['submission']);
            $managerCorrection = $entry->exists && $manager && $entry->state === 'pending';
            if ($entry->exists) {
                abort_unless(($managerCorrection || $entry->submitted_by === $actor->id) && $entry->kind === $input['kind'], 403);
                abort_unless(($managerCorrection || $entry->state === 'returned') && $entry->revision === (int) $input['revision'], 409);
            }
            $sourceId = $entry->exists ? $entry->register_id : ($input['source'] ?? null);
            $source = $sourceId ? PerformanceRegister::whereIn('department', User::departmentAliases(OperationsReporting::DEPARTMENT))
                ->where('kind', $input['kind'])->whereNotNull('approved_at')->lockForUpdate()->findOrFail($sourceId) : null;
            if ($source) {
                abort_if(($source->data['import_undated'] ?? false) === true, 422, 'This undated workbook record is retained as source history.');
                abort_unless($manager || $source->owner_id === $actor->id || $source->submitted_by === $actor->id, 403);
                abort_unless(hash_equals(OperationsReporting::version($source), $input['source_version'] ?? $entry->source_version ?? ''), 409, 'This report changed. Open the published row and start a fresh correction.');
            }
            $existingCollaborators = collect($entry->payload['data']['collaborator_ids'] ?? $source?->data['collaborator_ids'] ?? [])->map(fn ($id) => (int) $id);
            $ownerId = $manager ? ($input['owner_id'] ?? $source?->owner_id ?? ($entry->payload['owner_id'] ?? $actor->id)) : ($source?->owner_id ?? $actor->id);
            if (! empty($input['collaborators_present']) || isset($input['collaborator_ids'])) {
                $collaborators = collect($input['collaborator_ids'] ?? [])->map(fn ($id) => (int) $id);
                $newCollaborators = $collaborators->diff($existingCollaborators);
                if ($newCollaborators->isNotEmpty() && User::internalStaff()->where('status', 'active')->whereIn('id', $newCollaborators)->count() !== $newCollaborators->count()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['collaborator_ids' => 'Select active staff as collaborators.']);
                }
                $data['collaborator_ids'] = $collaborators->reject(fn ($id) => $id === (int) $ownerId)->values()->all();
            } elseif ($existingCollaborators->isNotEmpty()) {
                $data['collaborator_ids'] = $existingCollaborators->reject(fn ($id) => $id === (int) $ownerId)->values()->all();
            }
            $handoverId=$input['handover_id']??$entry->payload['data']['operations_handover_id']??$source?->data['operations_handover_id']??null;
            if($handoverId){
                $handover=\App\Models\OperationsHandover::with('project')->lockForUpdate()->findOrFail($handoverId);
                abort_unless($manager||(int)$handover->lead_id===$actor->id,403);
                if(!$handover->lead_id||!$handover->handover_approved_at)throw ValidationException::withMessages(['handover_id'=>'The HOD must approve the handover and assign an Operations lead first.']);
                $ownerId=$handover->lead_id;$data['operations_handover_id']=$handover->id;$data['crm_project_id']=$handover->project_id;
                $input['project']=$handover->project->project_name;$input['client']=$handover->project->client_name;
            }
            $week = Carbon::parse($input['week'])->startOfWeek();
            $payload = [
                'title' => $input['title'],
                'client' => $input['client'] ?? null,
                'project' => $input['project'] ?? null,
                'owner_id' => $ownerId,
                'period_start' => $source?->period_start?->toDateString() ?? ($entry->payload['period_start'] ?? $week->toDateString()),
                'period_end' => $source?->period_end?->toDateString() ?? ($entry->payload['period_end'] ?? $week->copy()->endOfWeek()->toDateString()),
                'status' => $input['status'],
                'evidence_url' => $moduleEntry ? ($input['evidence_url'] ?? null) : ($entry->payload['evidence_url'] ?? $source?->evidence_url),
                'data' => $data + array_diff_key(($entry->payload['data'] ?? []) + ($source?->data ?? []), OperationsReporting::fields($input['kind'], $includePrivate), ['campaign_id' => true]),
            ];
            $payload=$this->retainHandoverIdentity($payload,$source);
            if (! empty($input['owner_id'])) {
                unset($payload['data']['import_no_owner']);
            }
            $previousPayload = $entry->payload ?? [];
            $entry->fill([
                'submitted_by' => $entry->submitted_by ?? $actor->id,
                'register_id' => $source?->id,
                'kind' => $input['kind'],
                'state' => 'pending',
                'source_version' => $source ? OperationsReporting::version($source) : null,
                'payload' => $payload,
                'revision' => $entry->exists ? $entry->revision + 1 : 1,
            ])->save();
            if ($manager) {
                $this->publish($entry, $source, $actor);
            }
            PerformanceWorkflow::record('operations_submission', $entry->id, $actor->id, $managerCorrection ? 'correct_and_publish' : ($manager ? 'publish' : 'submit'), $managerCorrection ? $previousPayload : [], ['revision' => $entry->revision, 'register_id' => $entry->register_id], $manager ? 'Manager published report' : 'Staff submitted report');
            if ($managerCorrection) {
                PerformanceWorkflow::notify($entry->submitted_by, "ops-result:{$entry->id}:{$entry->revision}", 'Operations report corrected and published', 'Open the submission to see the published details.', route('portal.operations', ['ops_review' => $entry->id]).'#operations-reviews');
            }
            if (! $manager) {
                $reviewUrl = route('portal.operations', ['ops_review' => $entry->id]).'#operations-reviews';
                User::internalStaff()->where('status', 'active')->whereIn('department', User::departmentAliases(OperationsReporting::DEPARTMENT))
                    ->get()->filter(fn (User $person) => OperationsReporting::manager($person))
                    ->each(fn (User $person) => PerformanceWorkflow::notify($person->id, "ops-review:{$entry->id}:{$entry->revision}", 'Operations report awaiting review', 'Open the submission to review it.', $reviewUrl));
            }
            return $entry;
        });

        $destination = route($moduleEntry ? 'portal.operations' : 'dashboard', [
            'weekly_department' => OperationsReporting::DEPARTMENT,
            'report_period' => 'week',
            'report_date' => $entry->payload['period_start'],
            'ops_review' => $entry->id,
        ]).'#operations-reviews';

        return redirect($destination)->with('status', $entry->state === 'published'
            ? 'Report published.'
            : 'Report submitted for manager review. Submission #'.$entry->id.'.')
            ->with('operations_saved_kind', $entry->kind);
    }

    public function review(Request $request, OperationsReportSubmission $submission): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(OperationsReporting::manager($actor), 403);
        $input = $request->validate([
            'decision' => 'required|in:approve,return',
            'source_version' => 'nullable|string|size:64',
            'confirm_updated_report' => 'sometimes|accepted',
            'revision' => 'required|integer',
            'note' => 'required_if:decision,return|nullable|string|max:3000',
        ]);
        $alreadyPublished = DB::transaction(function () use ($actor, $submission, $input) {
            $entry = OperationsReportSubmission::lockForUpdate()->findOrFail($submission->id);
            if ($entry->state === 'published' && $input['decision'] === 'approve' && (int) $input['revision'] <= $entry->revision) {
                return true;
            }
            if ($entry->state !== 'pending' || $entry->revision !== (int) $input['revision']) {
                throw ValidationException::withMessages([
                    'review' => 'This submission changed after you opened it. The latest entry is now displayed. Check its status and details before reviewing again.',
                ]);
            }
            if ($input['decision'] === 'approve') {
                $source = $entry->register_id ? PerformanceRegister::lockForUpdate()->findOrFail($entry->register_id) : null;
                if ($source && ! hash_equals(OperationsReporting::version($source), $entry->source_version ?? '')) {
                    $reviewedVersion = $input['source_version'] ?? '';
                    if (empty($input['confirm_updated_report']) || ! hash_equals(OperationsReporting::version($source), $reviewedVersion)) {
                        throw ValidationException::withMessages([
                            'review' => 'The published report has changed. Compare the latest report with this submission below, then confirm you reviewed the differences before approving.',
                        ]);
                    }
                }
                $this->publish($entry, $source, $actor);
            } else {
                $entry->state = 'returned';
            }
            $entry->fill(['reviewed_by' => $actor->id, 'review_note' => $input['note'] ?? null, 'revision' => $entry->revision + 1])->save();
            PerformanceWorkflow::record('operations_submission', $entry->id, $actor->id, $input['decision'], [], ['revision' => $entry->revision, 'register_id' => $entry->register_id], $entry->review_note ?? 'Manager approved report');
            PerformanceWorkflow::notify($entry->submitted_by, "ops-result:{$entry->id}:{$entry->revision}", $entry->state === 'published' ? 'Operations report published' : 'Operations corrections requested', 'Open the submission to see the decision.', route('portal.operations', ['ops_review' => $entry->id]).'#operations-reviews');
        });

        return back()->with('status', $alreadyPublished ? 'This report is already published. Approval confirmed.'
            : ($input['decision'] === 'approve' ? 'Report published.' : 'Report returned with correction notes.'));
    }

    private function retainHandoverIdentity(array $payload, ?PerformanceRegister $source): array
    {
        $handoverId=$payload['data']['operations_handover_id']??$source?->data['operations_handover_id']??null;
        if(!$handoverId)return $payload;
        $handover=\App\Models\OperationsHandover::with('project')->lockForUpdate()->findOrFail($handoverId);
        $payload['client']=$handover->project->client_name;
        $payload['project']=$handover->project->project_name;
        $payload['owner_id']=$handover->lead_id;
        $payload['data']['crm_project_id']=$handover->project_id;
        $payload['data']['operations_handover_id']=$handover->id;
        return $payload;
    }

    private function publish(OperationsReportSubmission $entry, ?PerformanceRegister $source, User $actor): void
    {
        $register = $source ?? new PerformanceRegister([
            'department' => OperationsReporting::DEPARTMENT,
            'kind' => $entry->kind,
            'reference' => 'OPS-'.Str::ulid(),
            'submitted_by' => $entry->submitted_by,
        ]);
        $before = $register->exists ? $register->getAttributes() : [];
        $payload=$this->retainHandoverIdentity($entry->payload,$source);
        $register->fill($payload + ['approved_by' => $actor->id, 'approved_at' => now()])->save();
        app(\App\Services\OperationsAssetRequests::class)->sync($register,$actor);
        app(\App\Services\WarehouseAssetIntake::class)->fromOperations($register);
        $entry->update(['register_id' => $register->id, 'state' => 'published', 'reviewed_by' => $actor->id]);
        PerformanceWorkflow::record('register', $register->id, $actor->id, $source ? 'update' : 'create', $before, $register->getAttributes(), 'Published Operations report');
    }
}
