<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\BrandsReports;
use App\Services\WeeklyReportingHistory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeeklyReportCopyController extends Controller
{
    private function inputs(Request $request): array
    {
        $data = $request->validate([
            'department' => ['required', Rule::in(['brands_marketing', 'operations_projects', 'finance', 'hr_admin'])],
            'source_week' => 'required|date_format:Y-m-d',
            'target_week' => 'nullable|date_format:Y-m-d',
        ]);
        $user = $request->user();
        $allowed = $data['department'] === 'brands_marketing'
            ? BrandsReports::manager($user)
            : ($user->isCvoOrSuperAdmin() || ($user->isEffectiveLineManager() && User::normalizeDepartmentKey($user->department) === $data['department']));
        abort_unless($allowed, 403);

        return $data;
    }

    public function preview(Request $request, WeeklyReportingHistory $history)
    {
        $data = $this->inputs($request);
        $source = CarbonImmutable::parse($data['source_week'])->startOfWeek();
        $target = CarbonImmutable::parse($data['target_week'] ?? today())->startOfWeek();
        $rows = $history->weekSources($data['department'], $source->toDateString())->with(['leadStaff', 'creator'])->get();
        $existing = WeeklyConsolidatedItem::whereIn('report_series', $rows->pluck('report_series')->filter())
            ->whereDate('week_start', '>=', $target->toDateString())
            ->whereDate('week_start', '<=', $target->endOfWeek()->toDateString())
            ->pluck('report_series')->flip();
        $brands = $data['department'] === 'brands_marketing';

        return response()->json([
            'total' => $rows->count(),
            'source_week' => $source->toDateString(),
            'target_week' => $target->toDateString(),
            'items' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'title' => strip_tags(($brands ? ($row->client_name ?: $row->campaign_name) : $row->campaign_name) ?: $row->objective ?: 'Activity #'.$row->id),
                'client' => $brands ? ($row->custom_fields['brand'] ?? $row->campaign_name) : $row->client_name,
                'owner' => $row->leadStaff?->name ?? $row->creator?->name,
                'status' => $row->status,
                'existing' => $row->report_series && $existing->has($row->report_series),
                'selected' => $brands || !in_array($row->status, ['Done', 'Deferred'], true),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, WeeklyReportingHistory $history)
    {
        $data = $this->inputs($request);
        $values = $request->validate([
            'target_week' => 'required|date_format:Y-m-d',
            'items' => 'required|array|min:1|max:1000',
            'items.*' => 'required|integer|distinct',
            'origin' => 'nullable|in:dashboard,brands_module',
        ]);
        $result = $history->copyWeek($data['department'], $data['source_week'], $values['target_week'], $values['items'], $request->user()->id);
        $week = CarbonImmutable::parse($values['target_week'])->startOfWeek();
        $date = $week->toDateString();
        $query = ['weekly_department' => $data['department'], 'report_period' => 'week', 'report_date' => $date];
        $url = route('dashboard', $query).'#staff-weekly-performance';
        if ($data['department'] === 'brands_marketing') {
            $url = ($values['origin'] ?? null) === 'brands_module'
                ? route('portal.brands', ['module_view' => 'overview', 'brands_tab' => 'workstream', 'brands_period' => 'week', 'brands_date' => $date]).'#brands-reporting'
                : route('dashboard', $query + ['brands_tab' => 'workstream']).'#staff-weekly-performance';
        }

        $created = $result['created'];
        $existing = $result['existing'];
        $message = "{$created} new weekly ".($created === 1 ? 'draft' : 'drafts')." created. {$existing} existing ".($existing === 1 ? 'entry kept' : 'entries kept')." unchanged.";
        $request->session()->flash('status', $message);

        return response()->json($result + ['message' => $message, 'url' => $url]);
    }
}
