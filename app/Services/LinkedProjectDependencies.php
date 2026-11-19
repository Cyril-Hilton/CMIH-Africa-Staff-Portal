<?php

namespace App\Services;

use App\Models\PerformanceDependency;
use App\Models\PettyCashClaim;
use App\Models\ProjectBudget;
use App\Models\SupplierInvoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/** Exact source identifiers only. Similar titles do not establish project or financial identity. */
class LinkedProjectDependencies
{
    public function finance(Collection $projects, Collection $briefs, Collection $handovers, array $idMap, User $viewer): Collection
    {
        $taskMap = collect();
        foreach ($briefs as $root) {
            $pid = $root->crm_project_id ? ($idMap[$root->crm_project_id] ?? $root->crm_project_id) : -1000000000 - $root->id;
            foreach (collect([$root])->merge($root->parts) as $brief) {
                if ($brief->task_id) {
                    $taskMap->put($brief->task_id, $pid);
                }
            }
        }
        foreach ($handovers as $h) {
            if ($h->task_id) {
                $taskMap->put($h->task_id, $idMap[$h->project_id] ?? $h->project_id);
            }
        }
        foreach ($projects as $id => $row) {
            foreach ($row['control']?->data['tasks']['rows'] ?? [] as $task) {
                if (! empty($task['task_id'])) {
                    $taskMap->put($task['task_id'], $id);
                }
            }
        }
        foreach (Task::whereNotNull('custom_fields')->get(['id', 'custom_fields']) as $task) {
            $p = $task->custom_fields['executive_control_project_id'] ?? null;
            $a = $task->custom_fields['executive_control_activation_id'] ?? null;
            if ($p || $a) {
                $taskMap->put($task->id, $p ? ($idMap[$p] ?? $p) : -(int) $a);
            }
        }
        $taskMap = $taskMap->filter(fn ($pid) => $projects->has($pid));
        $rows = collect();
        foreach (['budget' => ProjectBudget::with('creator:id,name')->whereIn('task_id', $taskMap->keys())->get(), 'supplier_invoice' => SupplierInvoice::with('submitter:id,name')->whereIn('task_id', $taskMap->keys())->get(), 'claim' => PettyCashClaim::with('user:id,name')->whereIn('task_id', $taskMap->keys())->get()] as $kind => $records) {
            foreach ($records as $record) {
                $rows->push(['kind' => $kind, 'id' => $record->id, 'project_id' => $taskMap->get($record->task_id), 'task_id' => $record->task_id, 'title' => FinanceDashboard::canRead($viewer) ? ($kind === 'budget' ? $record->title : ($kind === 'supplier_invoice' ? 'Supplier invoice '.$record->invoice_number : 'Project reimbursement claim')) : ucwords(str_replace('_', ' ', $kind)).' processing', 'state' => $record->status, 'submitted_by' => ($record->creator ?? $record->submitter ?? $record->user)?->name, 'updated' => $record->updated_at, 'url' => route('portal.finance')]);
            }
        }

        return $rows;
    }

    public function work(User $viewer, Collection $events): Collection
    {
        $rows = PerformanceDependency::with(['upstream.leadStaff:id,name', 'downstream.leadStaff:id,name'])->get()->filter(fn ($d) => $events->contains('key', 'weekly:'.$d->downstream_id) || ($d->downstream?->source_task_id && $events->contains('key', 'task:'.$d->downstream->source_task_id)));

        return $rows->map(function ($d) use ($viewer) {
            $visible = fn ($item) => ($viewer->isCvoOrSuperAdmin() || (! $item || (! in_array($item->report_status, ['draft', 'submitted', 'returned']) && in_array($item->workflow_state, ['legacy', 'approved', 'verified', 'closed'])))) && (! in_array(User::normalizeDepartmentKey($item?->department ?? ''), ['hr_admin', 'finance']) || $viewer->isCvoOrSuperAdmin() || $viewer->canAuditHrAndFinance());
            $private = ! $visible($d->upstream) || ! $visible($d->downstream);

            return ['id' => $d->id, 'upstream' => $visible($d->upstream) ? $d->upstream?->campaign_name : 'Restricted department assignment', 'upstream_owner' => $d->upstream?->leadStaff?->name, 'upstream_state' => $d->upstream?->workflow_state, 'downstream' => $visible($d->downstream) ? $d->downstream?->campaign_name : 'Restricted department assignment', 'downstream_owner' => $d->downstream?->leadStaff?->name, 'downstream_key' => 'weekly:'.$d->downstream_id, 'task_key' => $d->downstream?->source_task_id ? 'task:'.$d->downstream->source_task_id : null, 'blocked' => $d->upstream && ! in_array($d->upstream->workflow_state, ['verified', 'closed']), 'impact' => $private ? 'Recorded cross-department dependency; confidential content remains restricted.' : $d->impact];
        });
    }
}
