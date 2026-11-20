<?php

namespace App\Services;

use App\Models\PerformanceEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/** Delivery movement comes from original workflow records, never from elapsed time. */
class ExecutiveProjectFlow
{
    public function build(array $row, Collection $briefs, Collection $handovers, Collection $activations, User $viewer): array
    {
        $p = $row['project'];
        $kind = $row['source_kind'] ?? 'crm';
        $bs = $briefs->filter(fn ($b) => $kind === 'creative' ? $b->id === $row['native_id'] : in_array($b->crm_project_id, $row['ids']));
        $hs = $handovers->filter(fn ($h) => in_array($h->project_id, $row['ids']));
        $as = $activations->filter(fn ($a) => $kind === 'activation' ? $a->id === $row['native_id'] : in_array($a->data['crm_project_id'] ?? $a->data['_crm_project_id'] ?? null, $row['ids']));
        $packs = $bs->flatMap(fn ($b) => $b->is_group ? $b->parts : [$b]);
        $sections = [];
        if ($kind === 'crm') {
            $journey = app(ProjectJourney::class)->project($p, $bs, $hs, $as, $viewer);
            foreach (ProjectJourney::STAGES as $key => $definition) {
                $sections[$key] = ['label' => $definition[0], 'team' => $definition[1], 'state' => $journey['skipped'][$key] ? 'skipped' : ($journey['stage'] === $key ? 'current' : ($journey['done'][$key] ? 'done' : 'waiting')), 'detail' => $journey['notes'][$key], 'records' => []];
            }
            $history = PerformanceEvent::whereIn('entity_id', $row['ids'])->whereIn('entity_type', ['crm_project', 'crm_pipeline_project'])->orderBy('id')->get();
            $sections['client']['records'][] = ['title' => $p->project_name, 'owner' => $p->owner?->name, 'url' => route('portal.project-journey', ['project' => $p->id]), 'state' => config('crm_pipeline.stages.'.$p->stage.'.label', $p->stage), 'steps' => collect(config('crm_pipeline.stages'))->reject(fn ($v, $k) => in_array($k, ['on_hold', 'lost', 'cancelled']))->map(function ($v, $k) use ($p, $history) {
                $event = $history->last(fn ($e) => ($e->after['stage'] ?? null) === $k && ($e->before['stage'] ?? null) !== $k);

                return $this->step($v['label'], $k === $p->stage ? ($k === 'project_completed' ? 'done' : 'current') : ($event ? 'done' : 'waiting'), $event?->created_at, 'Recorded in portal');
            })->values()->all()];
        } elseif ($kind === 'creative') {
            foreach (['creative' => ['Brief & design', 'Creative'], 'approval' => ['Review & corrections', 'Creative HOD'], 'closeout' => ['Verified completion', 'Creative HOD']] as $key => $definition) {
                $sections[$key] = ['label' => $definition[0], 'team' => $definition[1], 'state' => 'waiting', 'detail' => 'Original Creative workflow', 'records' => []];
            }
        } else {
            foreach (['handover' => ['Source & planning', 'Operations'], 'execution' => ['Execution', 'Operations'], 'closeout' => ['Report & closeout', 'Operations']] as $key => $definition) {
                $sections[$key] = ['label' => $definition[0], 'team' => $definition[1], 'state' => 'waiting', 'detail' => 'Published Operations source', 'records' => []];
            }
        }
        $creativeEvents = PerformanceEvent::where('entity_type', 'creative_request')->whereIn('entity_id', $packs->pluck('id'))->orderBy('id')->get();
        $handoverEvents = PerformanceEvent::where('entity_type', 'operations_handover')->whereIn('entity_id', $hs->pluck('id'))->orderBy('id')->get();
        foreach ($packs as $b) {
            $verified = $b->completed_at && in_array($b->stage, ['completed', 'archived']);
            $steps = [];
            foreach (['brief_received' => ['Brief received', $b->received_at ?? $b->created_at], 'scheduled' => ['Scheduled · planned start', ($b->assignee_id && $b->internal_eta) ? $b->planned_start : null], 'in_progress' => ['In progress', null], 'in_review' => ['Awaiting review', $b->first_submitted_at], 'delivered' => ['Approved for delivery', $b->delivered_at], 'completed' => ['Verified & completed', $verified ? $b->completed_at : null]] as $key => [$label,$at]) {
                $recorded = $creativeEvents->last(fn ($e) => (int) $e->entity_id === $b->id && ($e->after['stage'] ?? null) === $key && ($e->before['stage'] ?? null) !== $key);
                if (in_array($key, ['scheduled', 'in_progress'])) {
                    $at = $recorded?->created_at;
                    $label = $key === 'scheduled' ? 'Scheduled' : 'In progress';
                }
                $steps[] = $this->step($label, $b->stage === $key ? ($key === 'completed' && $verified ? 'done' : 'current') : ($at ? 'done' : 'waiting'), $at, in_array($key, ['scheduled', 'in_progress']) ? 'Recorded in portal' : null);
            }
            $record = ['title' => $b->title, 'owner' => $b->assignee?->name ?? 'Creative HOD · assignment needed', 'url' => route('portal.creative', ['request' => $b->id]), 'state' => config('creative.stages.'.$b->stage, $b->stage).($b->iteration > 1 ? ' · iteration '.$b->iteration : ''), 'steps' => $steps, 'planned_start' => $b->planned_start, 'requested' => $b->requested_deadline, 'agreed' => $b->internal_eta];
            $sections['creative']['records'][] = $record;
            $sections['approval']['records'][] = ['title' => $b->title, 'owner' => $b->reviewer?->name ?? 'Creative HOD', 'url' => $record['url'], 'state' => $record['state'], 'steps' => [$this->step('Submission recorded', $b->first_submitted_at ? 'done' : 'waiting', $b->first_submitted_at), $this->step('HOD delivery approval', $b->delivered_at ? 'done' : (in_array($b->stage, ['in_review', 'delivered']) ? 'current' : 'waiting'), $b->delivered_at), $this->step('Final verification', $verified ? 'done' : 'waiting', $verified ? $b->completed_at : null)]];

        }
        foreach ($hs as $h) {
            $sections['handover']['records'][] = ['title' => $h->title, 'owner' => $h->lead?->name ?? 'Operations HOD · assignment needed', 'url' => route('portal.operations.handovers.index', ['handover' => $h->id]), 'state' => ucwords(str_replace('_', ' ', $h->stage)), 'steps' => [$this->step('Handover submitted', 'done', $h->created_at), $this->step('Acknowledged', $h->acknowledged_at ? 'done' : 'waiting', $h->acknowledged_at), $this->step('Meeting held', $h->meeting_held_at ? 'done' : 'waiting', $h->meeting_held_at), $this->step('Client Relations sign-off', $h->cr_signoff_at ? 'done' : 'waiting', $h->cr_signoff_at), $this->step('Operations sign-off', $h->ops_signoff_at ? 'done' : 'waiting', $h->ops_signoff_at), $this->step('HOD handover approval', $h->handover_approved_at ? 'done' : 'waiting', $h->handover_approved_at)]];
            $sections['execution']['records'][] = ['title' => $h->title, 'owner' => $h->lead?->name, 'url' => route('portal.operations.handovers.index', ['handover' => $h->id]), 'state' => ucwords(str_replace('_', ' ', $h->stage)), 'steps' => collect(['planning' => 'Planning', 'sourcing' => 'Sourcing', 'setup' => 'Setup'])->map(function ($v, $k) use ($h, $handoverEvents) {
                $event = $handoverEvents->last(fn ($e) => (int) $e->entity_id === $h->id && ($e->after['stage'] ?? null) === $k && ($e->before['stage'] ?? null) !== $k);

                return $this->step($v, $h->stage === $k ? 'current' : ($event ? 'done' : 'waiting'), $event?->created_at, 'Recorded in portal');
            })->push($this->step('Execution reported', $h->submitted_at ? 'done' : 'waiting', $h->submitted_at))->all(), 'requested' => $h->execution_due_at, 'agreed' => $h->agreed_execution_due_at];
            $sections['closeout']['records'][] = ['title' => $h->title, 'owner' => $h->stage === 'awaiting_acceptance' ? $h->requester?->name : $h->lead?->name, 'url' => route('portal.operations.handovers.index', ['handover' => $h->id]), 'state' => ucwords(str_replace('_', ' ', $h->stage)), 'steps' => [$this->step('Delivery report submitted', $h->submitted_at ? 'done' : 'waiting', $h->submitted_at), $this->step('Client Relations acceptance', $h->accepted_at ? 'done' : 'waiting', $h->accepted_at), $this->step('Independent verification', $h->verified_at ? 'done' : 'waiting', $h->verified_at)]];
        }
        foreach ($as as $a) {
            $executed = OperationsReporting::activationExecuted($a);
            $url = route('portal.executive.registers.show', $a);
            $sections['execution']['records'][] = ['title' => $a->title, 'owner' => $a->owner?->name, 'url' => $url, 'state' => ucwords(str_replace('_', ' ', $a->status)), 'steps' => [$this->step('Plan published', 'done', $a->approved_at), $this->step('Execution', $executed ? 'done' : ($a->status === 'in_progress' ? 'current' : 'waiting'), null), $this->step('Execution reported', $executed ? 'done' : 'waiting', null)]];
            if ($kind === 'activation') {
                $sections['handover']['state'] = 'done';
                $sections['handover']['detail'] = 'Independent activation · Client Relations handover not established';
                $sections['execution']['state'] = $executed ? 'done' : 'current';
                $sections['closeout']['detail'] = 'Reported execution and independently verified closeout remain separate';
            }
        }
        if ($kind === 'creative') {
            $allVerified = $packs->isNotEmpty() && $packs->every(fn ($b) => in_array($b->stage, ['completed', 'archived']) && $b->completed_at);
            $review = $packs->isNotEmpty() && $packs->every(fn ($b) => in_array($b->stage, ['in_review', 'delivered', 'completed', 'archived']));
            $sections['creative']['state'] = $review ? 'done' : 'current';
            $sections['approval']['state'] = $allVerified ? 'done' : ($review ? 'current' : 'waiting');
            $sections['closeout']['state'] = $allVerified ? 'done' : 'waiting';
        }
        if ($as->isNotEmpty()) {
            foreach (\App\Models\PerformanceRegister::whereNotNull('approved_at')->whereIn('department', User::departmentAliases('operations_projects'))->where('kind', 'project_report')->whereIn('data->activation_id', $as->pluck('id'))->with('owner:id,name')->get() as $report) {
                $sections['closeout']['records'][] = ['title' => $report->title, 'owner' => $report->owner?->name, 'url' => route('portal.executive.registers.show', $report), 'state' => 'Published Operations report · '.$report->status, 'steps' => [$this->step('Project report published', 'done', $report->approved_at)]];
                if ($kind === 'activation') {
                    $sections['closeout']['state'] = 'current';
                }
            }
        }
        $recap = $row['schedule']['recap_submitted_at'] ?? null;
        if ($recap) {
            $sections['closeout']['records'][] = ['title' => 'Execution recap', 'owner' => null, 'url' => $row['control'] ? route('portal.executive.controls.show', $row['control']) : null, 'state' => 'Recap submitted', 'steps' => [$this->step('Recap submitted', 'done', $recap)]];
        }

        return ['sections' => $sections, 'connected' => $kind === 'crm', 'updated' => now(), 'cancelled' => in_array($p->stage, ['cancelled', 'lost'])];
    }

    private function step(string $label, string $state, $at, ?string $date_kind = null): array
    {
        return compact('label', 'state', 'at', 'date_kind');
    }
}
