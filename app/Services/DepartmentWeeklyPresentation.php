<?php
namespace App\Services;

use App\Models\{User, WeeklyConsolidatedItem};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Present saved reports directly; narrative figures are not promoted to verified KPI results. */
class DepartmentWeeklyPresentation
{
    public function build(string $department, ?string $from, ?string $to, array $filters = []): LengthAwarePaginator
    {
        $filters = \Illuminate\Support\Facades\Validator::make($filters, ['q'=>'nullable|string|max:200','status'=>'nullable|in:Planned,In Progress,Done,Blocked,Deferred','sort'=>'nullable|in:newest,oldest,title'])->validate();
        $query = WeeklyConsolidatedItem::with(['leadStaff', 'creator'])
            ->where('workflow_state', 'legacy')->whereIn('department', User::departmentAliases($department))
            ->when($from, fn ($q) => $q->whereDate('week_end', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('week_start', '<=', $to));
        $search = mb_substr(trim((string)($filters['q'] ?? '')), 0, 200);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (['client_name', 'campaign_name', 'deliverables', 'target_breakdown', 'achieved_breakdown', 'gap_breakdown', 'notes'] as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
                $q->orWhereHas('leadStaff', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
            });
        }
        if (in_array($filters['status'] ?? null, ['Planned', 'In Progress', 'Done', 'Blocked', 'Deferred'], true)) {
            $query->where('status', $filters['status']);
        }
        match ($filters['sort'] ?? 'newest') {
            'title' => $query->orderBy('campaign_name'),
            'oldest' => $query->orderBy('week_start'),
            default => $query->orderByDesc('week_start'),
        };
        return $query->orderByDesc('id')->paginate(8, ['*'], 'weekly_'.$department.'_page')->withQueryString()->appends(($department==='client_relations'?['client_reporting_view'=>'updates']:[])+(request()->routeIs('portal.brands','portal.operations','portal.finance')?['module_view'=>'weekly']:[]))->fragment('staff-weekly-performance');
    }
}
