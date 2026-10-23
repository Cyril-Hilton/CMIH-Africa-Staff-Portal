<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OperationsTracker
{
    public function validate(string $kind, array $data): void
    {
        foreach (config('operations_tracker.options') as $field => $options) {
            if (isset($data[$field]) && $data[$field] !== '' && ! in_array($data[$field], $options, true)) {
                throw ValidationException::withMessages(['data.'.$field => 'Select one of the listed values.']);
            }
        }
        foreach ([['starts_on', 'ends_on'], ['deployed_on', 'expected_return_on'], ['deployed_on', 'returned_on'], ['submitted_on', 'decided_on'], ['activity_on', 'shared_on']] as [$start,$end]) {
            if (! empty($data[$start]) && ! empty($data[$end]) && Carbon::parse($data[$end])->lt(Carbon::parse($data[$start]))) {
                throw ValidationException::withMessages(['data.'.$end => 'This date cannot precede '.$start.'.']);
            }
        }
        if (isset($data['progress_percent']) && $data['progress_percent'] > 100) {
            throw ValidationException::withMessages(['data.progress_percent' => 'Progress must be between 0 and 100.']);
        }
        if ($kind === 'posm_deployment' && isset($data['returned_quantity'])) {
            if ($data['returned_quantity'] < 0 || $data['returned_quantity'] > ($data['quantity'] ?? 0)) {
                throw ValidationException::withMessages(['data.returned_quantity' => 'Returned quantity must be between zero and the deployed quantity.']);
            }
            if ($data['returned_quantity'] > 0 && empty($data['returned_on'])) {
                throw ValidationException::withMessages(['data.returned_on' => 'Record the date of the latest return.']);
            }
            if (($data['return_status'] ?? '') === 'Returned' && $data['returned_quantity'] != ($data['quantity'] ?? 0)) {
                throw ValidationException::withMessages(['data.return_status' => 'Use Partially Returned until all units have been returned.']);
            }
            if (($data['return_status'] ?? '') === 'Partially Returned' && ! ($data['returned_quantity'] > 0 && $data['returned_quantity'] < ($data['quantity'] ?? 0))) {
                throw ValidationException::withMessages(['data.returned_quantity' => 'A partial return must be greater than zero and less than the deployed quantity.']);
            }
        }
        foreach (['asset_code', 'quantity', 'deployed_on'] as $field) {
            if ($kind === 'posm_deployment' && (! isset($data[$field]) || $data[$field] === '' || ($field === 'quantity' && $data[$field] <= 0))) {
                throw ValidationException::withMessages(['data.'.$field => 'A deployment needs an asset code, positive quantity and deployment date.']);
            }
        }
    }

    public function derived(string $kind, array $d): array
    {
        $days = function ($start, $end) use ($d) {
            return ! empty($d[$start]) && ! empty($d[$end]) ? (int) Carbon::parse($d[$start])->startOfDay()->diffInDays(Carbon::parse($d[$end])->startOfDay(), false) : null;
        };

        return match ($kind) {
            'activation' => ['duration_days' => ($n = $days('starts_on', 'ends_on')) === null ? null : $n + 1, 'calendar_state' => empty($d['starts_on']) || empty($d['ends_on']) ? 'Not assessed' : (Carbon::parse($d['starts_on'])->gt(today()) ? 'Upcoming' : (Carbon::parse($d['ends_on'])->lt(today()) ? 'Period ended — completion not verified' : 'Ongoing')), 'report_shared' => ! empty($d['report_shared_on'])],
            'posm_deployment' => ['outstanding_quantity' => isset($d['quantity'],$d['returned_quantity']) ? max(0, $d['quantity'] - $d['returned_quantity']) : null, 'days_deployed' => empty($d['deployed_on']) ? null : (int) Carbon::parse($d['deployed_on'])->startOfDay()->diffInDays(Carbon::parse($d['returned_on'] ?? today())->startOfDay(), false), 'return_overdue' => ! empty($d['expected_return_on']) && (isset($d['returned_quantity']) ? ($d['returned_quantity'] < ($d['quantity'] ?? 0)) : empty($d['returned_on'])) && ($d['return_status'] ?? null) !== 'Not Required' && Carbon::parse($d['expected_return_on'])->lt(today())],
            'client_approval' => ['days_pending' => empty($d['submitted_on']) ? null : (int) Carbon::parse($d['submitted_on'])->startOfDay()->diffInDays(Carbon::parse($d['decided_on'] ?? today())->startOfDay(), false)],
            'project_report' => ['days_overdue' => empty($d['due_on']) ? null : max(0, (int) Carbon::parse($d['due_on'])->startOfDay()->diffInDays(Carbon::parse($d['shared_on'] ?? today())->startOfDay(), false))],
            default => [],
        };
    }

    public function summary(Collection $rows): array
    {
        $rows = $rows->where('department', 'operations_projects')->whereNotNull('approved_at')->where('status', '!=', 'cancelled');
        $deployments = $rows->where('kind', 'posm_deployment');
        $reports = $rows->where('kind', 'project_report');

        return ['projects' => $rows->where('kind', 'project')->count(), 'activations' => $rows->where('kind', 'activation')->count(), 'deployed_quantity' => $deployments->filter(fn ($r) => ($r->data['deployment_status'] ?? null) === 'Deployed')->sum(fn ($r) => $r->data['quantity'] ?? 0), 'overdue_returns' => $deployments->filter(fn ($r) => $this->derived($r->kind, $r->data)['return_overdue'])->count(), 'reports_overdue' => $reports->filter(fn ($r) => empty($r->data['shared_on']) && ($this->derived($r->kind, $r->data)['days_overdue'] ?? 0) > 0)->count(), 'pending_approvals' => $rows->where('kind', 'client_approval')->filter(fn ($r) => ($r->data['approval_status'] ?? null) === 'Pending')->count(), 'open_issues' => $rows->where('kind', 'risk_issue')->filter(fn ($r) => in_array($r->data['issue_status'] ?? null, ['Open', 'In Progress']))->count(), 'approved_sources' => $rows->count()];
    }
}
