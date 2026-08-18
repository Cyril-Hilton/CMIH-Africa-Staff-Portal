<?php

namespace App\Http\Controllers\Merchandiser;

use App\Http\Controllers\Controller;
use App\Jobs\SendMerchandiserComplianceMessage;
use App\Models\Announcement;
use App\Models\Appraisal;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\KeyDistributor;
use App\Models\LeaveApplication;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserComplianceQuery;
use App\Models\MerchandiserGoogleFormAssignment;
use App\Models\MerchandiserLocation;
use App\Models\MerchandiserOutletAssignment;
use App\Models\MerchandiserPcmClockin;
use App\Models\MerchandiserPjp;
use App\Models\MerchandiserPjpClockin;
use App\Models\MerchandiserPlanogram;
use App\Models\MerchandiserReport;
use App\Models\MerchandiserSupervisorAssignment;
use App\Models\MerchandiserVisit;
use App\Models\Notification;
use App\Models\Outlet;
use App\Models\PettyCashClaim;
use App\Models\PerfectStoreCategoryTarget;
use App\Models\PosmLedger;
use App\Models\Region;
use App\Models\SalaryAdvance;
use App\Models\Sku;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\MerchandiserRoutePlanner;
use App\Services\PerfectStoreCalculator;
use App\Services\PerfectStoreKpiService;
use App\Services\PerfectStoreFormTemplate;
use App\Support\MerchandiserClockWindows;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MerchandiserAdminHubController extends Controller
{
    /**
     * Guard: Only admin/super_admin can access these routes.
     */
    private function guardAdmin()
    {
        $user = auth()->user();
        if (! $user || ! $user->isMerchandiserPortalAdmin()) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Main Admin Hub Dashboard
     */
    public function dashboard(Request $request, ?string $adminTab = null)
    {
        $this->guardAdmin();
        $activeTab = $this->resolveAdminTab($request, $adminTab);

        // ── KPI Counts ─────────────────────────────────────────────────────────
        $totalMerchandisers   = User::merchandisers()->count();
        $activeMerchandisers  = User::merchandisers()->where('status', 'active')->count();
        $pendingMerchandisers = User::merchandisers()->where('status', 'pending')->count();
        $totalKds             = KeyDistributor::count();
        $totalOutlets         = Outlet::count();

        // Clock-in range for the dashboard KPI, chart, and PCM/PJP log review.
        $clockTimezone = 'Africa/Accra';
        [$clockFrom, $clockTo] = $this->clockInRange($request, $clockTimezone);
        $today = Carbon::today($clockTimezone)->toDateString();
        $clockFromInput = $clockFrom->toDateString();
        $clockToInput = $clockTo->toDateString();
        $clockRangeLabel = $this->clockRangeLabel($clockFrom, $clockTo);

        $todayPcmClockins = collect();
        $todayPjpClockins = collect();

        if ($activeTab === 'supervisors') {
            $todayPcmClockins = MerchandiserPcmClockin::with(['user', 'keyDistributor'])
                ->whereBetween('clocked_in_at', [$clockFrom, $clockTo])
                ->latest('clocked_in_at')
                ->take(25)
                ->get();
            $todayPjpClockins = MerchandiserPjpClockin::with(['user', 'pjp'])
                ->whereBetween('clocked_in_at', [$clockFrom, $clockTo])
                ->latest('clocked_in_at')
                ->take(25)
                ->get();
        }
        $clockAttendanceCount = MerchandiserAttendance::whereBetween('clock_in_time', [$clockFrom, $clockTo])->count();
        $clockPcmCount = MerchandiserPcmClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->count();
        $clockPjpCount = MerchandiserPjpClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->count();
        $todayClockins  = $clockAttendanceCount + $clockPcmCount + $clockPjpCount;

        // Pending approvals
        $pendingLeaves  = LeaveApplication::where('status', 'pending')->whereHas('user', fn($q) => $q->merchandisers())->count();
        $pendingClaims  = PettyCashClaim::where('status', 'pending')->whereHas('user', fn($q) => $q->merchandisers())->count();
        $pendingLoans   = SalaryAdvance::where('status', 'pending')->whereHas('user', fn($q) => $q->merchandisers())->count();
        $liveLocationCount = MerchandiserLocation::query()->distinct()->count('user_id');

        $attendanceChart = [];
        $topPerformers = collect();
        $perfectStoreSummary = PerfectStoreKpiService::emptySummary();
        $perfectStoreKdData = collect();
        $perfectStoreMerchandiserData = collect();
        $perfectStoreMilestones = collect();
        $categorySosData = collect();

        if (in_array($activeTab, ['overview', 'perfect-store', 'executive', 'category-kpi', 'user-performance'], true)) {
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();
            $perfectStoreSummary = app(PerfectStoreKpiService::class)->summary($clockFrom, $clockTo);

            // Compute Perfect Store KPI Calculator Breakdown
            $allKds = KeyDistributor::orderBy('name')->get();
            $recentVisits = MerchandiserVisit::with(['outlet.keyDistributor', 'visitSkus.sku', 'user.supervisor', 'user.merchandiserKd'])
                ->whereBetween('created_at', [$clockFrom->copy()->startOfDay(), $clockTo->copy()->endOfDay()])
                ->latest()
                ->get();

            $visitsByKdId = $recentVisits->groupBy(fn($v) => (int) ($v->outlet?->kd_id ?? 0));
            $perfectStoreKdData = $allKds->map(function (KeyDistributor $kd) use ($visitsByKdId) {
                $kdVisits = $visitsByKdId->get((int) $kd->id, collect());
                return \App\Services\PerfectStoreCalculator::computeKdMetrics($kd, $kdVisits);
            })->sortByDesc('overall_score')->values();

            $visitsByUserId = $recentVisits->groupBy(fn($v) => (int) $v->user_id);
            $activeMerchList = User::merchandisers()->where('status', 'active')->with(['supervisor', 'merchandiserKd'])->orderBy('name')->get();
            $perfectStoreMerchandiserData = $activeMerchList->map(function (User $merch) use ($visitsByUserId) {
                $userVisits = $visitsByUserId->get((int) $merch->id, collect());
                $metrics = \App\Services\PerfectStoreCalculator::computeMerchandiserMetrics($merch, $userVisits);
                $metrics['user_name'] = $merch->name;
                $metrics['supervisor_name'] = $merch->supervisor?->name ?? 'Unassigned';
                $metrics['kd_name'] = $merch->merchandiserKd?->name ?? 'Unassigned';
                return $metrics;
            })->sortByDesc('overall_score')->values();

            $perfectStoreMilestones = $recentVisits->take(15)->map(function (MerchandiserVisit $visit) {
                $metrics = \App\Services\PerfectStoreCalculator::computeStoreVisitMetrics($visit);
                return [
                    'visit_id' => $visit->id,
                    'outlet_name' => $visit->outlet?->name ?? 'Store #' . $visit->outlet_id,
                    'kd_name' => $visit->outlet?->keyDistributor?->name ?? 'KD N/A',
                    'merchandiser_name' => $visit->user?->name ?? 'Agent',
                    'created_at' => $visit->created_at->format('d M H:i'),
                    'facing_pct' => $metrics['facing_pct'],
                    'planogram_pct' => $metrics['planogram_pct'],
                    'sos_pct' => $metrics['sos_pct'],
                    'overall_score' => $metrics['overall_score'],
                    'status' => $metrics['status'],
                    'total_skus' => $metrics['total_skus'],
                    'actual_facings' => $metrics['actual_facings'],
                    'target_facings' => $metrics['target_facings'],
                ];
            })->values();

            $categorySosData = app(PerfectStoreKpiService::class)->categoryKpis($clockFrom, $clockTo);

            $chartStart = $clockFrom->copy()->startOfDay();
            $chartEnd = $clockTo->copy()->startOfDay();
            if ($chartStart->diffInDays($chartEnd) > 30) {
                $chartStart = $chartEnd->copy()->subDays(30);
            }

            for ($date = $chartStart->copy(); $date->lte($chartEnd); $date->addDay()) {
                $attendanceChart[$date->format('D d')] = MerchandiserAttendance::whereDate('clock_in_time', $date)->count()
                    + MerchandiserPcmClockin::whereDate('clocked_in_at', $date)->count()
                    + MerchandiserPjpClockin::whereDate('clocked_in_at', $date)->count();
            }

            $topPerformers = User::merchandisers()
                ->where('status', 'active')
                ->withCount(['merchandiserVisits' => fn($q) => $q->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])])
                ->orderByDesc('merchandiser_visits_count')
                ->take(10)
                ->get();
        }

        [$outletCreatedFrom, $outletCreatedTo] = $this->outletCreatedRange($request);
        $outletCreatedFromInput = $outletCreatedFrom?->toDateString();
        $outletCreatedToInput = $outletCreatedTo?->toDateString();
        $outletCreatedRangeLabel = $this->outletCreatedRangeLabel($outletCreatedFrom, $outletCreatedTo);
        $outletRegistrationDay = 'all';
        $outletDayLabels = $this->outletDayLabels();
        $kds = collect();
        $outletManagementKds = collect();
        $assignableOutlets = collect();

        if (in_array($activeTab, ['kds', 'forms', 'merchandisers', 'supervisors'], true)) {
            $kds = KeyDistributor::with(['region', 'outlets.registeredBy', 'outlets.assignedMerchandisers', 'merchandisers'])
                ->withCount('merchandisers')
                ->orderBy('name')
                ->get();

            $outletManagementKds = $kds->map(function (KeyDistributor $kd) use ($outletCreatedFrom, $outletCreatedTo) {
                $clone = clone $kd;
                $clone->setRelation(
                    'outlets',
                    $kd->outlets
                        ->filter(fn (Outlet $outlet) => $this->outletCreatedWithinRange($outlet, $outletCreatedFrom, $outletCreatedTo))
                        ->sortByDesc('created_at')
                        ->values()
                );

                return $clone;
            });
        }

        $merchandiserLocations = [];

        if ($activeTab === 'tracking') {
        // ── Latest locations for live tracking ────────────────────────────────
            $clockedInMerchandiserIds = collect()
            ->merge(MerchandiserAttendance::whereBetween('clock_in_time', [$clockFrom, $clockTo])->pluck('user_id'))
            ->merge(MerchandiserPcmClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->pluck('user_id'))
            ->merge(MerchandiserPjpClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip()
            ->all();
        $latestClockInsByUser = collect();

        foreach ([
            [MerchandiserAttendance::class, 'clock_in_time'],
            [MerchandiserPcmClockin::class, 'clocked_in_at'],
            [MerchandiserPjpClockin::class, 'clocked_in_at'],
        ] as [$model, $column]) {
            $model::query()
                ->select('user_id', DB::raw("MAX({$column}) as last_clocked_at"))
                ->whereBetween($column, [$clockFrom, $clockTo])
                ->groupBy('user_id')
                ->pluck('last_clocked_at', 'user_id')
                ->each(function ($timestamp, $userId) use ($latestClockInsByUser, $clockTimezone) {
                    if (! $timestamp) {
                        return;
                    }

                    $candidate = Carbon::parse($timestamp)->timezone($clockTimezone);
                    $current = $latestClockInsByUser->get((int) $userId);

                    if (! $current || $candidate->gt($current)) {
                        $latestClockInsByUser->put((int) $userId, $candidate);
                    }
                });
        }

        $latestLocationsByUser = MerchandiserLocation::query()
            ->joinSub(
                MerchandiserLocation::query()
                    ->select('user_id', DB::raw('MAX(recorded_at) as latest_recorded_at'))
                    ->groupBy('user_id'),
                'latest_locations',
                function ($join) {
                    $join->on('merchandiser_locations.user_id', '=', 'latest_locations.user_id')
                        ->on('merchandiser_locations.recorded_at', '=', 'latest_locations.latest_recorded_at');
                }
            )
            ->select('merchandiser_locations.*')
            ->get()
            ->keyBy('user_id');

        User::merchandisers()->where('status', 'active')->each(function ($m) use (&$merchandiserLocations, $clockedInMerchandiserIds, $latestClockInsByUser, $latestLocationsByUser) {
            $loc = $latestLocationsByUser->get($m->id);
            $latestClockIn = $latestClockInsByUser->get((int) $m->id);
            $merchandiserLocations[] = [
                'id'         => $m->id,
                'name'       => $m->name,
                'phone'      => $m->phone ?? 'N/A',
                'status'     => $m->status,
                'clocked_in' => array_key_exists((int) $m->id, $clockedInMerchandiserIds),
                'last_clock_in' => $latestClockIn ? $latestClockIn->format('d M Y, h:i A') : null,
                'latitude'   => $loc ? (float) $loc->latitude  : null,
                'longitude'  => $loc ? (float) $loc->longitude : null,
                'last_seen'  => $loc ? $loc->recorded_at->diffForHumans() : 'No GPS data',
            ];
        });
        }

        $coverageMonth = $request->input('coverage_month', now()->format('Y-m'));
        $coverageWeek = $request->input('coverage_week');
        $coverageStart = Carbon::createFromFormat('!Y-m', $coverageMonth)->startOfMonth();
        $coverageEnd = $coverageStart->copy()->endOfMonth();

        if ($coverageWeek && preg_match('/^(\d{4})-W(\d{2})$/', $coverageWeek, $weekMatch)) {
            $coverageStart = Carbon::now()->setISODate((int) $weekMatch[1], (int) $weekMatch[2])->startOfWeek();
            $coverageEnd = $coverageStart->copy()->endOfWeek();
        }

        $routeTimezone = 'Africa/Accra';
        [$routeFrom, $routeTo] = $this->routePlanningRange($request, $routeTimezone);
        $routeFromInput = $routeFrom->format('Y-m-d\TH:i');
        $routeToInput = $routeTo->format('Y-m-d\TH:i');

        $allMerchandisers = collect();
        $outletAssignmentMerchandisers = collect();

        if (in_array($activeTab, ['kds', 'forms', 'merchandisers', 'supervisors'], true)) {
            $coverageByUser = collect();

            if ($activeTab === 'merchandisers') {
                $coverageByUser = MerchandiserVisit::query()
                    ->select('user_id', DB::raw('count(distinct outlet_id) as covered_outlets'))
                    ->whereBetween('created_at', [$coverageStart, $coverageEnd])
                    ->groupBy('user_id')
                    ->pluck('covered_outlets', 'user_id');
            }

            $allMerchandisersQuery = User::merchandisers()
                ->with(['merchandiserKd', 'merchandiserRegion'])
                ->withCount('merchandiserVisits')
                ->orderBy('name');

            if ($activeTab === 'merchandisers') {
                $allMerchandisers = $allMerchandisersQuery
                    ->paginate(30, ['*'], 'merchandiser_page')
                    ->appends(array_merge($request->query(), ['tab' => $activeTab]));

                $allMerchandisers->getCollection()->each(function (User $merchandiser) use ($coverageByUser) {
                    $merchandiser->total_outlets_covered = (int) ($coverageByUser->get($merchandiser->id) ?? 0);
                });
            } else {
                $allMerchandisers = $allMerchandisersQuery->get();
                $allMerchandisers->each(fn (User $merchandiser) => $merchandiser->total_outlets_covered = 0);
            }
        }

        if ($activeTab === 'routes') {
            $outletAssignmentMerchandisers = User::merchandisers()
                ->with(['merchandiserKd', 'merchandiserRegion', 'assignedMerchandiserOutlets.keyDistributor', 'assignedMerchandiserOutlets.registeredBy'])
                ->where('status', 'active')
                ->orderBy('name')
                ->paginate(20, ['*'], 'outlet_assignment_page')
                ->appends(array_merge($request->query(), ['tab' => 'routes']));

            $outletAssignmentMerchandisers->getCollection()->each(function (User $merchandiser) use ($outletCreatedFrom, $outletCreatedTo) {
                $merchandiser->setRelation(
                    'assignedMerchandiserOutlets',
                    $merchandiser->assignedMerchandiserOutlets
                        ->filter(fn (Outlet $outlet) => $this->outletCreatedWithinRange($outlet, $outletCreatedFrom, $outletCreatedTo))
                        ->sortByDesc('created_at')
                        ->values()
                );
            });

            $visibleKdIds = $outletAssignmentMerchandisers->getCollection()
                ->pluck('kd_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $assignableOutlets = $visibleKdIds->isNotEmpty()
                ? $this->applyOutletCreatedRangeQuery(
                    Outlet::with(['keyDistributor', 'registeredBy', 'assignedMerchandisers'])
                        ->whereIn('kd_id', $visibleKdIds->all())
                        ->orderByDesc('created_at'),
                    $outletCreatedFrom,
                    $outletCreatedTo
                )->get()
                : collect();
        }

        // ── All POSM / Field Gear entries ──────────────────────────────────────
        $allAssetsTotal = PosmLedger::count();
        $allAssets = $activeTab === 'assets'
            ? PosmLedger::with('createdBy')
                ->orderByDesc('created_at')
                ->paginate(50, ['*'], 'asset_page')
                ->appends(array_merge($request->query(), ['tab' => 'assets']))
            : $this->emptyPaginator($request, 'asset_page', 50);

        // ── Regions ────────────────────────────────────────────────────────────
        $regions = in_array($activeTab, ['overview', 'kds', 'merchandisers'], true)
            ? Region::orderBy('name')->get()
            : collect();

        // ── Pending Approvals (for Notifications tab) ─────────────────────────
        $pendingLeavesList = $activeTab === 'notifications'
            ? LeaveApplication::with('user')
                ->where('status', 'pending')
                ->whereHas('user', fn($q) => $q->merchandisers())
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'leave_page')
                ->appends(array_merge($request->query(), ['tab' => 'notifications']))
            : $this->emptyPaginator($request, 'leave_page', 20);

        $pendingClaimsList = $activeTab === 'notifications'
            ? PettyCashClaim::with('user')
                ->where('status', 'pending')
                ->whereHas('user', fn($q) => $q->merchandisers())
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'claim_page')
                ->appends(array_merge($request->query(), ['tab' => 'notifications']))
            : $this->emptyPaginator($request, 'claim_page', 20);

        $pendingLoansList = $activeTab === 'notifications'
            ? SalaryAdvance::with('user')
                ->where('status', 'pending')
                ->whereHas('user', fn($q) => $q->merchandisers())
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'loan_page')
                ->appends(array_merge($request->query(), ['tab' => 'notifications']))
            : $this->emptyPaginator($request, 'loan_page', 20);

        $suspendedMerchandisers = User::merchandisers()->where('status', 'suspended')->count();

        // ── Visits by Key Distributor ──────────────────────────────────────────
        $visitsByKd = $activeTab === 'overview'
            ? DB::table('merchandiser_visits')
                ->join('outlets', 'merchandiser_visits.outlet_id', '=', 'outlets.id')
                ->join('key_distributors', 'outlets.kd_id', '=', 'key_distributors.id')
                ->select('key_distributors.name', DB::raw('count(*) as count'))
                ->groupBy('key_distributors.name')
                ->pluck('count', 'name')
                ->toArray()
            : [];

        // ── Asset Quantities by Item Name ──────────────────────────────────────
        $assetsByItem = $activeTab === 'overview'
            ? DB::table('posm_ledgers')
                ->select('item_name', DB::raw('sum(quantity_out) as total_qty'))
                ->groupBy('item_name')
                ->pluck('total_qty', 'item_name')
                ->toArray()
            : [];
        $outletsByRegion = $activeTab === 'overview'
            ? DB::table('outlets')
                ->leftJoin('key_distributors as kd', 'outlets.kd_id', '=', 'kd.id')
                ->leftJoin('regions as r', 'kd.region_id', '=', 'r.id')
                ->selectRaw("coalesce(r.name, 'Unassigned') as label, count(*) as total")
                ->groupBy(DB::raw("coalesce(r.name, 'Unassigned')"))
                ->orderByDesc('total')
                ->pluck('total', 'label')
                ->toArray()
            : [];
        $outletsByChannel = $activeTab === 'overview'
            ? DB::table('outlets')
                ->selectRaw("coalesce(nullif(channel_type, ''), 'Unspecified') as label, count(*) as total")
                ->groupBy(DB::raw("coalesce(nullif(channel_type, ''), 'Unspecified')"))
                ->orderByDesc('total')
                ->pluck('total', 'label')
                ->toArray()
            : [];
        $clockCoverageChart = ['Clocked in' => 0, 'Not clocked' => 0];
        if ($activeTab === 'overview') {
            $clockedUserIds = collect()
                ->merge(MerchandiserAttendance::whereBetween('clock_in_time', [$clockFrom, $clockTo])->pluck('user_id'))
                ->merge(MerchandiserPcmClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->pluck('user_id'))
                ->merge(MerchandiserPjpClockin::whereBetween('clocked_in_at', [$clockFrom, $clockTo])->pluck('user_id'))
                ->filter()
                ->unique()
                ->values();
            $activeClockedUsers = $clockedUserIds->isEmpty()
                ? 0
                : User::merchandisers()->where('status', 'active')->whereIn('id', $clockedUserIds)->count();
            $clockCoverageChart = [
                'Clocked in' => $activeClockedUsers,
                'Not clocked' => max($activeMerchandisers - $activeClockedUsers, 0),
            ];
        }

        // ── Recent Share Links ─────────────────────────────────────────────────
        $recentReports = $activeTab === 'overview'
            ? MerchandiserReport::where('created_by', auth()->id())
                ->orderByDesc('created_at')
                ->take(10)
                ->get()
            : collect();
        $clockSettings = MerchandiserClockWindows::visitSettings();
        $skuCount = Sku::count();
        $skuReferenceCount = Sku::whereNotNull('reference_image_path')->count();
        $skuCategories = $activeTab === 'skus'
            ? Sku::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
            : collect();
        $skus = $activeTab === 'skus'
            ? Sku::with('brand')
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(30, ['*'], 'sku_page')
                ->appends(array_merge($request->query(), ['tab' => 'skus']))
            : $this->emptyPaginator($request, 'sku_page', 30);
        $skuAiConfigured = filled(config('services.openai.api_key')) || filled(config('services.gemini.api_key'));

        $supervisorCount = User::merchandiserSupervisors()
            ->where('status', 'active')
            ->count();
        $supervisorCandidates = collect();
        $supervisorRoleSearch = trim((string) $request->query('supervisor_role_search', ''));
        $supervisorManageMerchandisers = $this->emptyPaginator($request, 'supervisor_role_page', 8);
        $currentUserCanUploadPjp = auth()->user()?->isMerchandiserSupervisor() ?? false;
        $supervisorIds = [];
        $supervisorStats = collect();
        $pjps = collect();
        $activePjpForCurrentUser = null;
        $currentUserPjpClockin = null;
        $complianceQueries = collect();

        if ($activeTab === 'supervisors') {
        $supervisorCandidates = User::merchandiserSupervisors()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $supervisorManageMerchandisers = User::merchandisers()
            ->with(['merchandiserKd', 'merchandiserRegion'])
            ->where('status', 'active')
            ->when($supervisorRoleSearch !== '', function ($query) use ($supervisorRoleSearch) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $supervisorRoleSearch) . '%';

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('contact_email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('merchandiserKd', fn ($kdQuery) => $kdQuery->where('name', 'like', $like))
                        ->orWhereHas('merchandiserRegion', fn ($regionQuery) => $regionQuery->where('name', 'like', $like));
                });
            })
            ->orderByRaw('CASE WHEN access_role = ? THEN 0 ELSE 1 END', [User::MERCHANDISER_SUPERVISOR_ROLE])
            ->orderBy('name')
            ->paginate(8, ['*'], 'supervisor_role_page')
            ->appends([
                'tab' => 'supervisors',
                'supervisor_role_search' => $supervisorRoleSearch,
            ]);
        $supervisorIds = $supervisorCandidates->pluck('id')->map(fn ($id) => (int) $id)->all();
        $supervisorStats = $supervisorCandidates->map(function (User $supervisor) use ($coverageStart, $coverageEnd) {
            $assignedMerchandiserIds = User::merchandisers()
                ->where('supervisor_id', $supervisor->id)
                ->pluck('id');
            $assignedKdIds = MerchandiserSupervisorAssignment::where('supervisor_id', $supervisor->id)
                ->whereNotNull('kd_id')
                ->pluck('kd_id')
                ->unique();

            return [
                'user' => $supervisor,
                'assigned_merchandisers' => $assignedMerchandiserIds->count(),
                'assigned_kds' => $assignedKdIds->count(),
                'clockins' => MerchandiserAttendance::whereIn('user_id', $assignedMerchandiserIds)
                    ->whereBetween('clock_in_time', [$coverageStart, $coverageEnd])
                    ->count(),
                'outlets_covered' => MerchandiserVisit::whereIn('user_id', $assignedMerchandiserIds)
                    ->whereBetween('created_at', [$coverageStart, $coverageEnd])
                    ->distinct('outlet_id')
                    ->count('outlet_id'),
            ];
        });
        $pjps = MerchandiserPjp::with(['supervisor', 'uploadedBy', 'clockins.user'])
            ->latest()
            ->take(40)
            ->get();
        $activePjpForCurrentUser = MerchandiserPjp::where('supervisor_id', auth()->id())
            ->whereIn('status', ['forwarded', 'active'])
            ->whereDate('week_start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('week_end')->orWhereDate('week_end', '>=', now());
            })
            ->latest()
            ->first();
        $currentUserPjpClockin = $activePjpForCurrentUser
            ? MerchandiserPjpClockin::where('pjp_id', $activePjpForCurrentUser->id)
                ->where('user_id', auth()->id())
                ->whereDate('clocked_in_at', $today)
                ->first()
            : null;
        $complianceQueries = MerchandiserComplianceQuery::with(['user', 'sender'])
            ->latest()
            ->take(30)
            ->get();
        }
        $todayForRoutes = Carbon::today($routeTimezone)->toDateString();
        $routeSidebarPending = MerchandiserOutletAssignment::where('status', 'planned')
            ->whereDate('assigned_date', '<=', $todayForRoutes)
            ->count();
        $routeAssignmentsTotal = 0;
        $routeAssignments = $this->emptyPaginator($request, 'route_page', 25);
        $routeSummary = [
            'total' => 0,
            'completed' => 0,
            'pending_today' => 0,
            'future_planned' => 0,
            'overdue' => 0,
            'pending' => $routeSidebarPending,
            'completion_rate' => 0,
        ];
        $routeDailyChart = ['labels' => [], 'total' => [], 'completed' => [], 'planned' => []];
        $routeStatusChart = ['labels' => ['Completed', 'Due Today', 'Future Planned', 'Missed/Overdue'], 'data' => [0, 0, 0, 0]];
        $routeMerchandiserStats = collect();
        $routeKdStats = collect();

        if ($activeTab === 'routes') {
        $routeAssignmentsQuery = $this->constrainRouteAssignmentWindow(
            MerchandiserOutletAssignment::with(['user.merchandiserKd', 'outlet.keyDistributor']),
            $routeFrom,
            $routeTo
        );
        $routeAssignmentsTotal = (clone $routeAssignmentsQuery)->count();
        $completedRouteCount = (clone $routeAssignmentsQuery)->where('status', 'completed')->count();
        $pendingTodayRouteCount = (clone $routeAssignmentsQuery)
            ->where('status', 'planned')
            ->whereDate('assigned_date', $todayForRoutes)
            ->count();
        $futureRouteCount = (clone $routeAssignmentsQuery)
            ->where('status', 'planned')
            ->whereDate('assigned_date', '>', $todayForRoutes)
            ->count();
        $overdueRouteCount = (clone $routeAssignmentsQuery)
            ->where('status', 'planned')
            ->whereDate('assigned_date', '<', $todayForRoutes)
            ->count();
        $routeAssignments = $routeAssignmentsQuery
            ->orderBy('assigned_date')
            ->orderBy('sequence')
            ->paginate(25, ['*'], 'route_page')
            ->appends(array_merge($request->query(), ['tab' => 'routes']));
        $routeSummary = [
            'total' => $routeAssignmentsTotal,
            'completed' => $completedRouteCount,
            'pending_today' => $pendingTodayRouteCount,
            'future_planned' => $futureRouteCount,
            'overdue' => $overdueRouteCount,
            'pending' => $pendingTodayRouteCount + $overdueRouteCount,
            'completion_rate' => $this->boundedPercent($completedRouteCount, $routeAssignmentsTotal),
        ];
        $routeDailyStats = $this->constrainRouteAssignmentWindow(DB::table('merchandiser_outlet_assignments'), $routeFrom, $routeTo)
            ->select(
                'assigned_date',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when status = 'planned' then 1 else 0 end) as planned")
            )
            ->groupBy('assigned_date')
            ->orderBy('assigned_date')
            ->get();
        $routeDailyChart = [
            'labels' => $routeDailyStats->map(fn ($row) => Carbon::parse($row->assigned_date)->format('d M'))->all(),
            'total' => $routeDailyStats->pluck('total')->map(fn ($value) => (int) $value)->all(),
            'completed' => $routeDailyStats->pluck('completed')->map(fn ($value) => (int) $value)->all(),
            'planned' => $routeDailyStats->pluck('planned')->map(fn ($value) => (int) $value)->all(),
        ];
        $routeStatusChart = [
            'labels' => ['Completed', 'Due Today', 'Future Planned', 'Missed/Overdue'],
            'data' => [
                $completedRouteCount,
                $pendingTodayRouteCount,
                $futureRouteCount,
                $overdueRouteCount,
            ],
        ];
        $routeMerchandiserStats = $this->constrainRouteAssignmentWindow(DB::table('merchandiser_outlet_assignments'), $routeFrom, $routeTo)
            ->join('users', 'merchandiser_outlet_assignments.user_id', '=', 'users.id')
            ->select('users.name')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when merchandiser_outlet_assignments.status = 'completed' then 1 else 0 end) as completed")
            ->selectRaw("sum(case when merchandiser_outlet_assignments.status = 'planned' and merchandiser_outlet_assignments.assigned_date < ? then 1 else 0 end) as overdue", [$todayForRoutes])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->take(10)
            ->get();
        $routeKdStats = $this->constrainRouteAssignmentWindow(DB::table('merchandiser_outlet_assignments'), $routeFrom, $routeTo)
            ->join('outlets', 'merchandiser_outlet_assignments.outlet_id', '=', 'outlets.id')
            ->join('key_distributors', 'outlets.kd_id', '=', 'key_distributors.id')
            ->select(
                'key_distributors.name',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when merchandiser_outlet_assignments.status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when merchandiser_outlet_assignments.status = 'planned' then 1 else 0 end) as planned")
            )
            ->groupBy('key_distributors.id', 'key_distributors.name')
            ->orderByDesc('total')
            ->take(10)
            ->get();
        }
        $googleFormsCount = MerchandiserGoogleFormAssignment::count();
        $planogramsCount = MerchandiserPlanogram::count();
        $googleForms = $activeTab === 'forms'
            ? MerchandiserGoogleFormAssignment::with(['assignedUser', 'outlet', 'keyDistributor', 'brand', 'campaign'])
                ->withCount(['submissions', 'nativeSubmissions'])
                ->latest()
                ->paginate(20, ['*'], 'google_form_page')
                ->appends(array_merge($request->query(), ['tab' => 'forms']))
            : $this->emptyPaginator($request, 'google_form_page', 20);
        $planograms = $activeTab === 'forms'
            ? MerchandiserPlanogram::latest()
                ->paginate(20, ['*'], 'planogram_page')
                ->appends(array_merge($request->query(), ['tab' => 'forms']))
            : $this->emptyPaginator($request, 'planogram_page', 20);
        $brandOptions = in_array($activeTab, ['forms', 'skus'], true)
            ? Brand::orderBy('name')->get()
            : collect();
        $campaignOptions = $activeTab === 'forms'
            ? Campaign::orderBy('name')->get()
            : collect();
        $perfectStoreGuides = [
            'SSM & LMT' => ['Skin Care horizontal/vertical standards', 'Comfort vertical/horizontal', 'OMO vertical/horizontal', 'Sunlight DWL vertical/horizontal', 'Bars visibility'],
            'LMT' => ['Wobbler', 'Shelf talker', 'Category divider', 'Gondola end', 'Parasite unit', 'Branded cart', 'FSU', 'Experience centre'],
            'SSM' => ['Wobbler', 'Shelf talker', 'Category divider', 'FSU'],
            'Cosmetics' => ['Body oils', 'Petroleum jelly', 'Lotions', 'Shelf branding', 'FSU', 'Countertop unit', 'Panel', 'Dangler', 'Poster'],
            'Pharmacy' => ['Oral care must-have SKUs', 'Skin cleansing must-have SKUs', 'Skin care must-have SKUs', 'Door cling', 'Dangler', 'FSU', 'Momo stand'],
        ];

        // ── ShelfWatch: Image Gallery ───────────────────────────────────────────
        $totalImagesCount = DB::table('merchandiser_visit_skus')->whereNotNull('photo_path')->count();
        $galleryImages    = collect();
        $galleryFilters   = [];
        if (in_array($activeTab, ['gallery'], true)) {
            $galleryQ = DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->join('outlets as o', 'o.id', '=', 'v.outlet_id')
                ->join('key_distributors as kd', 'kd.id', '=', 'o.kd_id')
                ->join('users as u', 'u.id', '=', 'v.user_id')
                ->join('skus as s', 's.id', '=', 'vs.sku_id')
                ->whereNotNull('vs.photo_path')
                ->select(
                    'vs.id', 'vs.photo_path', 'vs.created_at',
                    'o.name as outlet_name', 'o.channel_type',
                    'kd.name as kd_name',
                    'u.name as user_name', 'u.id as user_id',
                    's.name as sku_name', 's.category'
                )
                ->when($request->filled('filter_user'), fn($q) => $q->where('u.id', $request->filter_user))
                ->when($request->filled('filter_kd'), fn($q) => $q->where('kd.id', $request->filter_kd))
                ->when($request->filled('filter_outlet'), fn($q) => $q->where('o.id', $request->filter_outlet))
                ->when($request->filled('filter_category'), fn($q) => $q->where('s.category', $request->filter_category))
                ->when($request->filled('filter_channel'), fn($q) => $q->where('o.channel_type', $request->filter_channel))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('vs.created_at', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('vs.created_at', '<=', $request->date_to))
                ->orderByDesc('vs.created_at');
            $galleryImages  = $galleryQ->paginate(40, ['*'], 'gallery_page')->appends($request->query());
            $galleryFilters = [
                'users'      => User::merchandisers()->orderBy('name')->get(['id','name']),
                'kds'        => KeyDistributor::orderBy('name')->get(['id','name']),
                'outlets'    => Outlet::orderBy('name')->get(['id','name']),
                'categories' => Sku::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
                'channels'   => ['SSM', 'LMT', 'GT'],
            ];
        }

        // ── ShelfWatch: Executive Summary ──────────────────────────────────────
        $execScheduled  = 0;
        $execActual     = 0;
        $execCompliance = 0.0;
        $execActiveRate = 0.0;
        $execVisitTrend = ['labels' => [], 'scheduled' => [], 'actual' => []];
        $execImageValidity = ['labels' => [], 'valid' => [], 'invalid' => []];
        $execSkuCount   = $skuCount;
        if (in_array($activeTab, ['executive'], true)) {
            $execScheduled  = MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $coverageStart->toDateString())->whereDate('assigned_date', '<=', $coverageEnd->toDateString())->count();
            $execActual     = MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $coverageStart->toDateString())
                ->whereDate('assigned_date', '<=', $coverageEnd->toDateString())
                ->where(fn ($query) => $query
                    ->where('status', 'completed')
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('visit_id'))
                ->count();
            $execCompliance = $this->boundedPercent($execActual, $execScheduled);
            $totalMerch     = User::merchandisers()->where('status', 'active')->count();
            $activeUserIds = collect()
                ->merge(MerchandiserAttendance::whereBetween('clock_in_time', [$coverageStart, $coverageEnd])->pluck('user_id'))
                ->merge(MerchandiserPcmClockin::whereBetween('clocked_in_at', [$coverageStart, $coverageEnd])->pluck('user_id'))
                ->merge(MerchandiserPjpClockin::whereBetween('clocked_in_at', [$coverageStart, $coverageEnd])->pluck('user_id'))
                ->filter()
                ->unique()
                ->values();
            $activeMerch = $activeUserIds->isEmpty()
                ? 0
                : User::merchandisers()->where('status', 'active')->whereIn('id', $activeUserIds)->count();
            $execActiveRate = $this->boundedPercent($activeMerch, $totalMerch);
            // 7-day visit trend
            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $execVisitTrend['labels'][]    = $day->format('d M');
                $execVisitTrend['scheduled'][] = MerchandiserOutletAssignment::whereDate('assigned_date', $day->toDateString())->count();
                $execVisitTrend['actual'][]    = MerchandiserOutletAssignment::whereDate('assigned_date', $day->toDateString())
                    ->where(fn ($query) => $query
                        ->where('status', 'completed')
                        ->orWhereNotNull('completed_at')
                        ->orWhereNotNull('visit_id'))
                    ->count();
            }
            // Image validity by day
            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $execImageValidity['labels'][]  = $day->format('d M');
                $execImageValidity['valid'][]   = DB::table('merchandiser_visit_skus')->whereNotNull('photo_path')->whereDate('created_at', $day->toDateString())->count();
                $execImageValidity['invalid'][] = DB::table('merchandiser_visit_skus')->whereNull('photo_path')->whereDate('created_at', $day->toDateString())->count();
            }
        }

        // ── ShelfWatch: Category Level KPIs ───────────────────────────────────
        $categoryKpis = collect();
        $categoryTargets = collect();
        if (in_array($activeTab, ['category-kpi'], true)) {
            $categoryTargets = PerfectStoreCategoryTarget::orderBy('category')->get()->keyBy('category');
            $categoryKpis = app(PerfectStoreKpiService::class)->categoryKpis($coverageStart, $coverageEnd);
        }

        // ── Performance Command Center: Merchandisers & Supervisors (Daily, Weekly, Monthly, Yearly) ──
        $perfPeriod = (string) $request->query('perf_period', 'monthly');
        $perfRole   = (string) $request->query('perf_role', 'all');

        $perfStart = match ($perfPeriod) {
            'daily'   => now()->startOfDay(),
            'weekly'  => now()->startOfWeek(),
            'yearly'  => now()->startOfYear(),
            'custom'  => $request->filled('perf_from') ? Carbon::parse($request->perf_from)->startOfDay() : $coverageStart,
            default   => now()->startOfMonth(),
        };

        $perfEnd = match ($perfPeriod) {
            'daily'   => now()->endOfDay(),
            'weekly'  => now()->endOfWeek(),
            'yearly'  => now()->endOfYear(),
            'custom'  => $request->filled('perf_to') ? Carbon::parse($request->perf_to)->endOfDay() : $coverageEnd,
            default   => now()->endOfMonth(),
        };

        $userPerformance = collect();
        $supervisorPerformance = collect();
        $perfTrendChart = ['labels' => [], 'coverage' => [], 'facing' => [], 'planogram' => [], 'overall' => []];

        if (in_array($activeTab, ['user-performance', 'supervisors'], true)) {
            // 1. Merchandisers Performance Data
            $merchandisersList = User::merchandisers()
                ->where('status', 'active')
                ->with(['merchandiserKd', 'merchandiserRegion', 'supervisor'])
                ->get();

            $userPerformance = $merchandisersList->map(function ($merch) use ($perfStart, $perfEnd) {
                $scheduled = MerchandiserOutletAssignment::where('user_id', $merch->id)
                    ->whereDate('assigned_date', '>=', $perfStart->toDateString())
                    ->whereDate('assigned_date', '<=', $perfEnd->toDateString())
                    ->count();

                $completed = MerchandiserOutletAssignment::where('user_id', $merch->id)
                    ->whereDate('assigned_date', '>=', $perfStart->toDateString())
                    ->whereDate('assigned_date', '<=', $perfEnd->toDateString())
                    ->where(fn ($q) => $q->where('status', 'completed')->orWhereNotNull('completed_at')->orWhereNotNull('visit_id'))
                    ->count();

                $visits = MerchandiserVisit::with(['visitSkus.sku', 'outlet.keyDistributor'])
                    ->where('user_id', $merch->id)
                    ->whereBetween('created_at', [$perfStart, $perfEnd])
                    ->get();

                $metrics = PerfectStoreCalculator::computeMerchandiserMetrics($merch, $visits->groupBy('outlet_id'));

                return [
                    'user_id' => $merch->id,
                    'user_name' => $merch->name,
                    'role' => 'Merchandiser',
                    'supervisor_name' => $merch->supervisor?->name ?? 'Unassigned',
                    'kd_name' => $merch->merchandiserKd?->name ?? 'N/A',
                    'region_name' => $merch->merchandiserRegion?->name ?? 'N/A',
                    'scheduled_visits' => $scheduled,
                    'completed_assignments' => $completed,
                    'coverage_pct' => $this->boundedPercent($completed, $scheduled),
                    'facing_pct' => $metrics['facing_pct'],
                    'planogram_pct' => $metrics['planogram_pct'],
                    'sos_pct' => $metrics['sos_pct'],
                    'overall_score' => $metrics['overall_score'],
                    'status' => $metrics['status'],
                ];
            })->sortByDesc('overall_score')->values();

            // 2. Supervisor Accountability Performance Data
            $supervisorsList = User::merchandiserSupervisors()
                ->where('status', 'active')
                ->get();

            $supervisorPerformance = $supervisorsList->map(function ($sup) use ($userPerformance) {
                $assignedMerchs = $userPerformance->where('supervisor_name', $sup->name);
                $merchCount = $assignedMerchs->count();

                if ($merchCount === 0) {
                    return [
                        'supervisor_id' => $sup->id,
                        'supervisor_name' => $sup->name,
                        'role' => 'Supervisor',
                        'assigned_merchandisers' => 0,
                        'total_scheduled' => 0,
                        'total_completed' => 0,
                        'coverage_pct' => 0.0,
                        'facing_pct' => 0.0,
                        'planogram_pct' => 0.0,
                        'sos_pct' => 0.0,
                        'overall_score' => 0.0,
                        'status' => 'Needs Attention',
                    ];
                }

                $totScheduled = $assignedMerchs->sum('scheduled_visits');
                $totCompleted = $assignedMerchs->sum('completed_assignments');
                $avgFacing    = round($assignedMerchs->avg('facing_pct'), 2);
                $avgPlano     = round($assignedMerchs->avg('planogram_pct'), 2);
                $avgSos       = round($assignedMerchs->avg('sos_pct'), 2);
                $avgOverall   = round($assignedMerchs->avg('overall_score'), 2);

                $status = 'Needs Attention';
                if ($avgOverall >= 95.0 && $avgFacing >= 95.0 && $avgPlano >= 100.0) {
                    $status = 'Perfect Store';
                } elseif ($avgOverall >= 75.0) {
                    $status = 'On Track';
                }

                return [
                    'supervisor_id' => $sup->id,
                    'supervisor_name' => $sup->name,
                    'role' => 'Supervisor',
                    'assigned_merchandisers' => $merchCount,
                    'total_scheduled' => $totScheduled,
                    'total_completed' => $totCompleted,
                    'coverage_pct' => $this->boundedPercent($totCompleted, $totScheduled),
                    'facing_pct' => $avgFacing,
                    'planogram_pct' => $avgPlano,
                    'sos_pct' => $avgSos,
                    'overall_score' => $avgOverall,
                    'status' => $status,
                ];
            })->sortByDesc('overall_score')->values();

            // 3. Performance Trend Chart (Daily/Weekly/Monthly/Yearly)
            $stepCount = match ($perfPeriod) {
                'daily'   => 7, // Last 7 days
                'weekly'  => 6, // Last 6 weeks
                'yearly'  => 12,// 12 months
                default   => 4, // 4 weeks of month
            };

            for ($i = $stepCount - 1; $i >= 0; $i--) {
                $periodSubStart = match ($perfPeriod) {
                    'daily'   => now()->subDays($i)->startOfDay(),
                    'weekly'  => now()->subWeeks($i)->startOfWeek(),
                    'yearly'  => now()->subMonths($i)->startOfMonth(),
                    default   => now()->subWeeks($i)->startOfWeek(),
                };
                $periodSubEnd = match ($perfPeriod) {
                    'daily'   => now()->subDays($i)->endOfDay(),
                    'weekly'  => now()->subWeeks($i)->endOfWeek(),
                    'yearly'  => now()->subMonths($i)->endOfMonth(),
                    default   => now()->subWeeks($i)->endOfWeek(),
                };

                $label = match ($perfPeriod) {
                    'daily'   => $periodSubStart->format('d M'),
                    'weekly'  => 'W' . $periodSubStart->weekOfYear . ' (' . $periodSubStart->format('d M') . ')',
                    'yearly'  => $periodSubStart->format('M Y'),
                    default   => 'Week ' . ($stepCount - $i),
                };

                $sched = MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $periodSubStart->toDateString())
                    ->whereDate('assigned_date', '<=', $periodSubEnd->toDateString())
                    ->count();

                $comp = MerchandiserOutletAssignment::whereDate('assigned_date', '>=', $periodSubStart->toDateString())
                    ->whereDate('assigned_date', '<=', $periodSubEnd->toDateString())
                    ->where(fn ($q) => $q->where('status', 'completed')->orWhereNotNull('completed_at')->orWhereNotNull('visit_id'))
                    ->count();

                $cov = $this->boundedPercent($comp, $sched);

                $periodVisits = MerchandiserVisit::with(['visitSkus.sku', 'outlet.keyDistributor'])
                    ->whereBetween('created_at', [$periodSubStart, $periodSubEnd])
                    ->get();

                $facingArr = [];
                $planoArr  = [];
                $overallArr = [];

                foreach ($periodVisits->groupBy('outlet_id') as $group) {
                    $v = $group->first();
                    if ($v) {
                        $m = PerfectStoreCalculator::computeStoreVisitMetrics($v);
                        $facingArr[]  = $m['facing_pct'];
                        $planoArr[]   = $m['planogram_pct'];
                        $overallArr[] = $m['overall_score'];
                    }
                }

                $avgF = count($facingArr) ? round(array_sum($facingArr) / count($facingArr), 1) : 95.0;
                $avgP = count($planoArr) ? round(array_sum($planoArr) / count($planoArr), 1) : 100.0;
                $avgO = count($overallArr) ? round(array_sum($overallArr) / count($overallArr), 1) : 85.0;

                $perfTrendChart['labels'][]   = $label;
                $perfTrendChart['coverage'][] = $cov;
                $perfTrendChart['facing'][]   = $avgF;
                $perfTrendChart['planogram'][]= $avgP;
                $perfTrendChart['overall'][]  = $avgO;
            }
        }

        // ── ShelfWatch: Price & Promo ─────────────────────────────────────────
        $pricePromoData    = collect();
        $posmCompliance    = 0.0;
        $pricingCompliance = 0.0;
        if (in_array($activeTab, ['price-promo'], true)) {
            // POSM: visits that have at least one POSM photo = compliant
            $totalVisitsPP = MerchandiserVisit::whereBetween('created_at', [$coverageStart, $coverageEnd])->count();
            $withPosm = DB::table('merchandiser_visits as v')
                ->whereExists(fn($q) => $q->from('merchandiser_visit_skus as vs')->whereColumn('vs.visit_id', 'v.id')->whereNotNull('vs.photo_path'))
                ->whereBetween('v.created_at', [$coverageStart, $coverageEnd])
                ->count();
            $posmCompliance = $this->boundedPercent($withPosm, $totalVisitsPP);
            // Price compliance: visits where price was recorded
            $withPrice = DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->whereNotNull('vs.shelf_price')
                ->whereBetween('v.created_at', [$coverageStart, $coverageEnd])
                ->count();
            $totalSkuChecks = DB::table('merchandiser_visit_skus as vs')
                ->join('merchandiser_visits as v', 'v.id', '=', 'vs.visit_id')
                ->whereBetween('v.created_at', [$coverageStart, $coverageEnd])
                ->count();
            $pricingCompliance = $this->boundedPercent($withPrice, $totalSkuChecks);
            // By KD promo performance
            $pricePromoData = DB::table('merchandiser_visits as v')
                ->join('outlets as o', 'o.id', '=', 'v.outlet_id')
                ->join('key_distributors as kd', 'kd.id', '=', 'o.kd_id')
                ->whereBetween('v.created_at', [$coverageStart, $coverageEnd])
                ->select('kd.name as kd_name', DB::raw('count(*) as visits'),
                    DB::raw('sum(case when exists(select 1 from merchandiser_visit_skus vs where vs.visit_id = v.id and vs.photo_path is not null) then 1 else 0 end) as posm_visits'))
                ->groupBy('kd.id', 'kd.name')
                ->orderByDesc('visits')
                ->get()
                ->map(function ($row) {
                    $row->posm_rate = $this->boundedPercent($row->posm_visits, $row->visits);
                    return $row;
                });
        }

        return view('merchandisers.admin', compact(
            'activeTab',
            'totalMerchandisers', 'activeMerchandisers', 'pendingMerchandisers', 'suspendedMerchandisers',
            'totalKds', 'totalOutlets', 'todayClockins',
            'pendingLeaves', 'pendingClaims', 'pendingLoans',
            'liveLocationCount',
            'attendanceChart', 'topPerformers',
            'clockFromInput', 'clockToInput', 'clockRangeLabel',
            'perfectStoreSummary',
            'clockAttendanceCount', 'clockPcmCount', 'clockPjpCount',
            'kds', 'regions',
            'outletManagementKds', 'outletRegistrationDay', 'outletDayLabels',
            'outletCreatedFromInput', 'outletCreatedToInput', 'outletCreatedRangeLabel',
            'assignableOutlets', 'outletAssignmentMerchandisers',
            'merchandiserLocations',
            'allMerchandisers',
            'allAssets', 'allAssetsTotal',
            'pendingLeavesList', 'pendingClaimsList', 'pendingLoansList',
            'recentReports',
            'visitsByKd', 'assetsByItem',
            'outletsByRegion', 'outletsByChannel', 'clockCoverageChart',
            'clockSettings',
            'skus', 'skuCount', 'skuReferenceCount', 'skuCategories', 'skuAiConfigured',
            'coverageMonth', 'coverageWeek', 'coverageStart', 'coverageEnd',
            'todayPcmClockins', 'todayPjpClockins',
            'supervisorCandidates', 'supervisorCount', 'supervisorRoleSearch',
            'supervisorManageMerchandisers', 'supervisorIds', 'supervisorStats',
            'pjps', 'activePjpForCurrentUser', 'currentUserPjpClockin',
            'currentUserCanUploadPjp', 'complianceQueries',
            'routeAssignments', 'routeAssignmentsTotal', 'routeSummary',
            'routeFrom', 'routeTo', 'routeFromInput', 'routeToInput',
            'routeDailyChart', 'routeStatusChart', 'routeMerchandiserStats', 'routeKdStats',
            'googleForms', 'planograms', 'googleFormsCount', 'planogramsCount',
            'brandOptions', 'campaignOptions',
            'perfectStoreGuides',
            // ShelfWatch tabs
            'totalImagesCount', 'galleryImages', 'galleryFilters',
            'execScheduled', 'execActual', 'execCompliance', 'execActiveRate',
            'execVisitTrend', 'execImageValidity', 'execSkuCount',
            'categoryKpis', 'categoryTargets',
            'userPerformance', 'supervisorPerformance', 'perfPeriod', 'perfRole', 'perfTrendChart',
            'pricePromoData', 'posmCompliance', 'pricingCompliance',
            'perfectStoreKdData', 'perfectStoreMerchandiserData', 'perfectStoreMilestones', 'categorySosData'
        ));
    }

    private function resolveAdminTab(Request $request, ?string $adminTab): string
    {
        $tabs = [
            'overview', 'perfect-store', 'tracking', 'kds', 'routes', 'skus', 'forms',
            'merchandisers', 'supervisors', 'assets', 'notifications', 'settings',
            'gallery', 'executive', 'category-kpi', 'user-performance', 'price-promo',
        ];

        $candidate = $adminTab ?: (string) $request->query('tab', 'overview');

        return in_array($candidate, $tabs, true) ? $candidate : 'overview';
    }

    private function emptyPaginator(Request $request, string $pageName, int $perPage): LengthAwarePaginator
    {
        return (new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            LengthAwarePaginator::resolveCurrentPage($pageName),
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ]
        ))->appends($request->query());
    }

    public function storeSku(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skus,name'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'new_brand_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'new_category' => ['nullable', 'string', 'max:255'],
            'track_osa' => ['nullable', 'boolean'],
            'osa_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_npd' => ['nullable', 'boolean'],
            'npd_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_mhs' => ['nullable', 'boolean'],
            'mhs_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'facing_target' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_planogram' => ['nullable', 'boolean'],
            'sos_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reference_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'aliases' => ['nullable', 'string', 'max:1000'],
            'ai_reference_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = null;
        if ($request->hasFile('reference_image')) {
            $path = $request->file('reference_image')->store('sku-reference-images', 'public');
        }

        Sku::create([
            'name' => $validated['name'],
            'brand_id' => $this->resolveSkuBrandId($validated),
            'category' => $this->resolveSkuCategory($validated),
            'track_osa' => $request->boolean('track_osa', true),
            'osa_drop_size' => (int) ($validated['osa_drop_size'] ?? 1),
            'track_npd' => $request->boolean('track_npd'),
            'npd_drop_size' => (int) ($validated['npd_drop_size'] ?? 1),
            'track_mhs' => $request->boolean('track_mhs'),
            'mhs_drop_size' => (int) ($validated['mhs_drop_size'] ?? 1),
            'facing_target' => (int) ($validated['facing_target'] ?? 1),
            'track_planogram' => $request->boolean('track_planogram', true),
            'sos_target' => $validated['sos_target'] ?? null,
            'reference_image_path' => $path,
            'aliases' => $this->parseSkuAliases($validated['aliases'] ?? ''),
            'ai_reference_notes' => $validated['ai_reference_notes'] ?? null,
        ]);

        return back()->with('success', 'SKU added to the AI catalog.');
    }

    public function updateSku(Request $request, Sku $sku)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skus,name,' . $sku->id],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'new_brand_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'new_category' => ['nullable', 'string', 'max:255'],
            'track_osa' => ['nullable', 'boolean'],
            'osa_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_npd' => ['nullable', 'boolean'],
            'npd_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_mhs' => ['nullable', 'boolean'],
            'mhs_drop_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'facing_target' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'track_planogram' => ['nullable', 'boolean'],
            'sos_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reference_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'aliases' => ['nullable', 'string', 'max:1000'],
            'ai_reference_notes' => ['nullable', 'string', 'max:1000'],
            'remove_reference_image' => ['nullable', 'boolean'],
        ]);

        $path = $sku->reference_image_path;
        if ($request->boolean('remove_reference_image') && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }

        if ($request->hasFile('reference_image')) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $path = $request->file('reference_image')->store('sku-reference-images', 'public');
        }

        $sku->update([
            'name' => $validated['name'],
            'brand_id' => $this->resolveSkuBrandId($validated),
            'category' => $this->resolveSkuCategory($validated),
            'track_osa' => $request->boolean('track_osa'),
            'osa_drop_size' => (int) ($validated['osa_drop_size'] ?? 1),
            'track_npd' => $request->boolean('track_npd'),
            'npd_drop_size' => (int) ($validated['npd_drop_size'] ?? 1),
            'track_mhs' => $request->boolean('track_mhs'),
            'mhs_drop_size' => (int) ($validated['mhs_drop_size'] ?? 1),
            'facing_target' => (int) ($validated['facing_target'] ?? 1),
            'track_planogram' => $request->boolean('track_planogram'),
            'sos_target' => $validated['sos_target'] ?? null,
            'reference_image_path' => $path,
            'aliases' => $this->parseSkuAliases($validated['aliases'] ?? ''),
            'ai_reference_notes' => $validated['ai_reference_notes'] ?? null,
        ]);

        return back()->with('success', 'SKU AI reference updated.');
    }

    public function storeCategoryTarget(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'sos_target' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $target = PerfectStoreCategoryTarget::firstOrNew(['category' => trim($validated['category'])]);
        if (! $target->exists) {
            $target->created_by = $request->user()->id;
        }
        $target->fill([
            'sos_target' => $validated['sos_target'],
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()
            ->to(route('merchandisers.admin.tab', ['adminTab' => 'category-kpi']) . '#category-targets')
            ->with('success', 'Category SOS target saved.');
    }

    public function destroySku(Sku $sku)
    {
        $this->guardAdmin();

        if ($sku->reference_image_path) {
            Storage::disk('public')->delete($sku->reference_image_path);
        }

        $sku->delete();

        return back()->with('success', 'SKU removed from the AI catalog.');
    }

    private function resolveSkuBrandId(array $validated): ?int
    {
        $newBrandName = Str::squish((string) ($validated['new_brand_name'] ?? ''));

        if ($newBrandName !== '') {
            $existingBrand = Brand::whereRaw('LOWER(name) = ?', [Str::lower($newBrandName)])->first();

            if ($existingBrand) {
                return $existingBrand->id;
            }

            return Brand::create([
                'name' => $newBrandName,
                'logo_path' => '',
            ])->id;
        }

        return isset($validated['brand_id']) ? (int) $validated['brand_id'] : null;
    }

    private function resolveSkuCategory(array $validated): ?string
    {
        $category = Str::squish((string) ($validated['new_category'] ?? ''));

        if ($category === '') {
            $category = Str::squish((string) ($validated['category'] ?? ''));
        }

        return $category !== '' ? $category : null;
    }

    private function parseSkuAliases(?string $aliases): array
    {
        return collect(explode(',', (string) $aliases))
            ->map(fn ($alias) => trim($alias))
            ->filter()
            ->unique(fn ($alias) => strtolower($alias))
            ->values()
            ->all();
    }

    public function updateClockSettings(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate(MerchandiserClockWindows::visitValidationRules());

        MerchandiserClockWindows::persistVisitWindow($validated, (int) $request->user()->id);

        return back()->with('success', 'Merchandiser outlet visit window updated successfully.');
    }

    // ── KD Management ─────────────────────────────────────────────────────────

    public function storeKd(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'region_id'  => 'nullable|string',   // nullable — may be '__new__'
            'new_region' => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:500',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
        ], [
            'latitude.required' => 'Latitude is required before merchandisers can use PCM/KD clock-in.',
            'longitude.required' => 'Longitude is required before merchandisers can use PCM/KD clock-in.',
        ]);

        // Handle "Other — Add New Region"
        if ($request->input('region_id') === '__new__') {
            $regionName = trim($request->input('new_region', ''));
            if (empty($regionName)) {
                return back()->withErrors(['new_region' => 'Please enter a name for the new region.'])->withInput();
            }
            // Create region if it doesn't already exist (case-insensitive match)
            $region = Region::firstOrCreate(
                ['name' => $regionName],
                ['timezone' => 'Africa/Accra']
            );
            $validated['region_id'] = $region->id;
        }

        // Ensure we have a valid region ID at this point
        if (empty($validated['region_id'])) {
            return back()->withErrors(['region_id' => 'Please select or enter a region.'])->withInput();
        }

        unset($validated['new_region']); // not a DB column
        KeyDistributor::create($validated);
        return back()->with('success', 'Key Distributor added successfully.');
    }

    public function updateKd(Request $request, KeyDistributor $kd)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'region_id'                    => 'required|exists:regions,id',
            'address'                      => 'nullable|string|max:500',
            'latitude'                     => 'required|numeric|between:-90,90',
            'longitude'                    => 'required|numeric|between:-180,180',
            'sync_assigned_merchandisers'  => 'nullable|boolean',
            'assigned_merchandiser_ids'    => 'nullable|array',
            'assigned_merchandiser_ids.*'  => 'integer|exists:users,id',
        ], [
            'latitude.required' => 'Latitude is required before merchandisers can use PCM/KD clock-in.',
            'longitude.required' => 'Longitude is required before merchandisers can use PCM/KD clock-in.',
        ]);

        $assignedMerchandiserIds = collect($validated['assigned_merchandiser_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($kd, $validated, $request, $assignedMerchandiserIds) {
            $updateData = [
                'name'      => $validated['name'],
                'region_id' => $validated['region_id'],
                'address'   => $validated['address'] ?? null,
                'latitude'  => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ];

            $kd->update($updateData);

            if ($request->boolean('sync_assigned_merchandisers')) {
                $currentlyAssigned = User::merchandisers()
                    ->where('kd_id', $kd->id);

                if ($assignedMerchandiserIds->isEmpty()) {
                    $currentlyAssigned->update(['kd_id' => null]);
                } else {
                    $currentlyAssigned
                        ->whereNotIn('id', $assignedMerchandiserIds->all())
                        ->update(['kd_id' => null]);

                    User::merchandisers()
                        ->whereIn('id', $assignedMerchandiserIds->all())
                        ->update([
                            'kd_id'     => $kd->id,
                            'region_id' => $validated['region_id'],
                            'status'    => 'active',
                        ]);
                }
            }
        });

        return back()->with('success', 'Key Distributor updated.');
    }

    public function destroyKd(KeyDistributor $kd)
    {
        $this->guardAdmin();

        $dependents = [
            'merchandisers' => User::where('kd_id', $kd->id)->where('access_role', 'merchandiser')->get(['id', 'name']),
            'tms'           => User::where('kd_id', $kd->id)->where('position_title', 'Territory Manager')->get(['id', 'name']),
            'dsrs'          => User::where('kd_id', $kd->id)->where('position_title', 'DSR')->get(['id', 'name']),
            'outlets'       => Outlet::where('kd_id', $kd->id)->get(['id', 'name']),
        ];

        $hasDependents = $dependents['merchandisers']->isNotEmpty() ||
                         $dependents['tms']->isNotEmpty() ||
                         $dependents['dsrs']->isNotEmpty() ||
                         $dependents['outlets']->isNotEmpty();

        if ($hasDependents && ! request()->has('reassign_kd_id')) {
            return back()->withErrors([
                'kd_error' => "Cannot delete KD: {$kd->name} has dependent outlets or merchandisers. Please reassign them first."
            ])->with([
                'show_reassign_wizard_for' => $kd->id,
                'dependents'               => $dependents,
            ]);
        }

        if ($hasDependents && request()->has('reassign_kd_id')) {
            request()->validate([
                'reassign_kd_id' => ['required', 'exists:key_distributors,id'],
            ]);

            $newKdId = request('reassign_kd_id');

            DB::transaction(function () use ($kd, $newKdId) {
                User::where('kd_id', $kd->id)->update(['kd_id' => $newKdId]);
                Outlet::where('kd_id', $kd->id)->update(['kd_id' => $newKdId]);
                $kd->delete();
            });

            return redirect()->route('merchandisers.admin.tab', ['adminTab' => 'kds'])
                ->with('success', 'Dependents reassigned and Key Distributor deleted successfully.');
        }

        // Unlink merchandisers before deleting
        User::where('kd_id', $kd->id)->update(['kd_id' => null]);
        $kd->outlets()->delete();
        $kd->delete();

        return redirect()->route('merchandisers.admin.tab', ['adminTab' => 'kds'])
            ->with('success', 'Key Distributor removed.');
    }

    // ── Outlet Management ─────────────────────────────────────────────────────

    public function storeOutlet(Request $request)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'kd_id'     => ['required', 'exists:key_distributors,id'],
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:32', 'unique:outlets,code'],
            'channel_type' => ['nullable', 'in:SSM,GT'],
            'address'   => ['nullable', 'string', 'max:500'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $assignedUserIds = $this->validatedOutletAssigneeIds($validated['assigned_user_ids'] ?? [], (int) $validated['kd_id']);
        if ($assignedUserIds === null) {
            return back()->withErrors(['assigned_user_ids' => 'Selected merchandisers must already be assigned to this outlet KD.'])->withInput();
        }

        DB::transaction(function () use ($validated, $assignedUserIds) {
            $hasCoordinates = filled($validated['latitude'] ?? null) && filled($validated['longitude'] ?? null);
            $outlet = Outlet::create([
                'kd_id' => $validated['kd_id'],
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'channel_type' => $validated['channel_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'coordinates_locked_at' => $hasCoordinates ? now() : null,
                'coordinates_captured_by' => $hasCoordinates ? auth()->id() : null,
                'coordinates_source' => $hasCoordinates ? 'admin_manual' : null,
            ]);

            $this->syncOutletMerchandisers($outlet, $assignedUserIds);
        });

        return back()->with('success', 'Outlet added.');
    }

    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'kd_id'     => ['nullable', 'exists:key_distributors,id'],
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:32', 'unique:outlets,code,' . $outlet->id],
            'channel_type' => ['nullable', 'in:SSM,GT'],
            'address'   => ['nullable', 'string', 'max:500'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $kdId = (int) ($validated['kd_id'] ?? $outlet->kd_id);
        $assignedUserIds = $request->has('assigned_user_ids')
            ? $this->validatedOutletAssigneeIds($validated['assigned_user_ids'] ?? [], $kdId)
            : null;

        if ($assignedUserIds === null && $request->has('assigned_user_ids')) {
            return back()->withErrors(['assigned_user_ids' => 'Selected merchandisers must already be assigned to this outlet KD.'])->withInput();
        }

        DB::transaction(function () use ($validated, $outlet, $assignedUserIds, $request, $kdId) {
            $hasCoordinates = filled($validated['latitude'] ?? null) && filled($validated['longitude'] ?? null);
            $outlet->update([
                'kd_id' => $kdId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'channel_type' => $validated['channel_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'coordinates_locked_at' => $hasCoordinates ? now() : null,
                'coordinates_captured_by' => $hasCoordinates ? auth()->id() : null,
                'coordinates_source' => $hasCoordinates ? 'admin_manual' : null,
            ]);

            if ($request->has('assigned_user_ids')) {
                $this->syncOutletMerchandisers($outlet, $assignedUserIds ?? collect());
            }
        });

        return back()->with('success', 'Outlet updated.');
    }

    public function destroyOutlet(Outlet $outlet)
    {
        $this->guardAdmin();
        $outlet->delete();
        return back()->with('success', 'Outlet removed.');
    }

    public function assignOutlets(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
            'visit_days' => ['nullable', 'array'],
            'visit_days.*' => ['integer', 'between:1,7'],
        ]);

        $outletIds = collect($validated['outlet_ids'] ?? [])
            ->push($validated['outlet_id'] ?? null)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($outletIds->isEmpty()) {
            return back()->withErrors(['outlet_ids' => 'Select at least one outlet to assign.'])->withInput();
        }

        $user = User::merchandisers()->whereKey($validated['user_id'])->firstOrFail();
        if (! $user->kd_id) {
            return back()->withErrors(['user_id' => 'Assign this merchandiser to a KD before assigning outlets.'])->withInput();
        }

        $outlets = Outlet::whereIn('id', $outletIds->all())->get();
        if ($outlets->contains(fn (Outlet $outlet) => (int) $outlet->kd_id !== (int) $user->kd_id)) {
            return back()->withErrors(['outlet_ids' => 'Every selected outlet must belong to the merchandiser assigned KD.'])->withInput();
        }

        $visitDays = ! empty($validated['visit_days']) ? array_map('intval', $validated['visit_days']) : null;

        foreach ($outlets as $outlet) {
            $pivotData = [
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ];
            if ($visitDays !== null) {
                $pivotData['visit_days'] = json_encode($visitDays);
            }

            $outlet->assignedMerchandisers()->syncWithoutDetaching([
                $user->id => $pivotData,
            ]);
        }

        return back()->with('success', "{$outlets->count()} outlet(s) assigned to {$user->name}.");
    }

    public function assignRegisteredOutlets(Request $request)
    {
        $this->guardAdmin();

        [$outletCreatedFrom, $outletCreatedTo] = $this->outletCreatedRange($request);
        $assigned = 0;
        $skipped = 0;

        $this->applyOutletCreatedRangeQuery(
            Outlet::with('registeredBy')->whereNotNull('registered_by'),
            $outletCreatedFrom,
            $outletCreatedTo
        )->chunkById(250, function ($outlets) use (&$assigned, &$skipped) {
            $outlets->each(function (Outlet $outlet) use (&$assigned, &$skipped) {
                    $registeredBy = $outlet->registeredBy;

                    if (! $registeredBy?->isMerchandiserAccount() || (int) $registeredBy->kd_id !== (int) $outlet->kd_id) {
                        $skipped++;

                        return;
                    }

                    $outlet->assignedMerchandisers()->syncWithoutDetaching([
                        $registeredBy->id => [
                            'assigned_by' => auth()->id(),
                            'assigned_at' => now(),
                        ],
                    ]);
                    $assigned++;
                });
            });

        $label = $this->outletCreatedRangeLabel($outletCreatedFrom, $outletCreatedTo);

        return back()->with('success', "Assigned {$assigned} outlet(s) created {$label} to their creators. {$skipped} skipped because the creator/KD pairing did not match.");
    }

    public function unassignOutlet(Outlet $outlet, User $user)
    {
        $this->guardAdmin();

        $outlet->assignedMerchandisers()->detach($user->id);
        MerchandiserOutletAssignment::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->where('status', 'planned')
            ->whereDate('assigned_date', '>=', Carbon::today())
            ->delete();

        return back()->with('success', "{$outlet->name} was removed from {$user->name}'s assigned outlet list.");
    }

    // ── Merchandiser Management ───────────────────────────────────────────────

    public function suspendMerchandiser(User $user)
    {
        $this->guardAdmin();
        $user->update(['status' => 'suspended']);
        return back()->with('success', "{$user->name} has been suspended.");
    }

    public function activateMerchandiser(User $user)
    {
        $this->guardAdmin();
        $user->update(['status' => 'active']);
        return back()->with('success', "{$user->name} has been activated.");
    }

    public function reassignMerchandiser(Request $request, User $user)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'kd_id'     => 'nullable|exists:key_distributors,id',
            'region_id' => 'nullable|exists:regions,id',
        ]);
        $user->update($validated);
        return back()->with('success', "{$user->name} has been reassigned.");
    }

    public function pairMerchandiser(Request $request, User $user)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'kd_id'     => 'required|exists:key_distributors,id',
            'region_id' => 'required|exists:regions,id',
        ]);
        $user->update($validated + ['status' => 'active']);
        return back()->with('success', "{$user->name} paired and activated.");
    }

    public function updateRouteSettings(Request $request, User $user, MerchandiserRoutePlanner $routePlanner)
    {
        $this->guardAdmin();

        if (! $user->isMerchandiserAccount()) {
            abort(403, 'Route settings are only available for merchandisers.');
        }

        $validated = $request->validate([
            'merchandiser_working_days' => ['nullable', 'array'],
            'merchandiser_working_days.*' => ['integer', 'between:1,7'],
            'merchandiser_daily_outlet_target' => ['nullable', 'integer', 'min:1'],
            'merchandiser_outlet_frequency' => ['required', 'string', 'in:daily,weekly,biweekly,monthly'],
        ]);

        $user->update([
            'merchandiser_working_days' => collect($validated['merchandiser_working_days'] ?? [1, 2, 3, 4, 5])
                ->map(fn ($day) => (int) $day)
                ->unique()
                ->values()
                ->all(),
            'merchandiser_daily_outlet_target' => filled($validated['merchandiser_daily_outlet_target'] ?? null)
                ? (int) $validated['merchandiser_daily_outlet_target']
                : null,
            'merchandiser_outlet_frequency' => $validated['merchandiser_outlet_frequency'],
        ]);

        $created = $routePlanner->ensureWeek($user, Carbon::now($user->merchandiserRegion->timezone ?? 'Africa/Accra')->startOfWeek());

        return back()->with('success', "{$user->name}'s route settings were saved. {$created->count()} missing route rows were prepared for this week.");
    }

    public function generateRoutes(Request $request, MerchandiserRoutePlanner $routePlanner)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'week_start' => ['nullable', 'date'],
            'generate_from' => ['nullable', 'date'],
            'generate_to' => ['nullable', 'date'],
            'merchandiser_ids' => ['nullable', 'array'],
            'merchandiser_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $timezone = 'Africa/Accra';
        if (! empty($validated['generate_from']) || ! empty($validated['generate_to'])) {
            $rangeStart = $this->parseRouteDateTime($validated['generate_from'] ?? null, Carbon::now($timezone)->startOfWeek(), $timezone);
            $rangeEnd = $this->parseRouteDateTime($validated['generate_to'] ?? null, $rangeStart->copy()->endOfWeek(), $timezone);
        } elseif (! empty($validated['week_start'])) {
            $rangeStart = Carbon::parse($validated['week_start'], $timezone)->startOfWeek();
            $rangeEnd = $rangeStart->copy()->endOfWeek();
        } else {
            return back()->withErrors(['generate_from' => 'Select a route generation start date and time.'])->withInput();
        }

        if ($rangeEnd->lt($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $merchandisers = User::merchandisers()
            ->where('status', 'active')
            ->whereNotNull('kd_id')
            ->when(! empty($validated['merchandiser_ids']), fn ($query) => $query->whereIn('id', $validated['merchandiser_ids']))
            ->with('merchandiserRegion')
            ->get();

        $created = 0;
        foreach ($merchandisers as $merchandiser) {
            $created += $routePlanner->ensurePeriod($merchandiser, $rangeStart->copy(), $rangeEnd->copy())->count();
        }

        return redirect()
            ->route('merchandisers.admin.dashboard', [
                'tab' => 'routes',
                'route_from' => $rangeStart->format('Y-m-d\TH:i'),
                'route_to' => $rangeEnd->format('Y-m-d\TH:i'),
            ])
            ->with('success', "Route generation complete for {$merchandisers->count()} merchandiser(s). {$created} new assignment row(s) were created.");
    }

    public function storeGoogleForm(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'google_form_url' => ['nullable', 'url', 'max:1000'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'kd_id' => ['nullable', 'exists:key_distributors,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'channel_type' => ['nullable', 'string', 'max:16'],
            'google_enabled' => ['nullable', 'boolean'],
            'native_enabled' => ['nullable', 'boolean'],
            'native_template_key' => ['nullable', 'string', 'in:' . PerfectStoreFormTemplate::KEY],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $validated['google_enabled'] = $request->boolean('google_enabled', true);
        $validated['native_enabled'] = $request->boolean('native_enabled');
        $validated['native_template_key'] = $validated['native_enabled']
            ? ($validated['native_template_key'] ?: PerfectStoreFormTemplate::KEY)
            : null;

        if (! $validated['google_enabled'] && ! $validated['native_enabled']) {
            return back()->withErrors(['google_enabled' => 'Enable Google Form, native inbuilt form, or both.'])->withInput();
        }

        if ($validated['google_enabled'] && blank($validated['google_form_url'] ?? null)) {
            return back()->withErrors(['google_form_url' => 'Add the Google Form URL or disable Google Form access.'])->withInput();
        }

        MerchandiserGoogleFormAssignment::create($validated + ['created_by' => auth()->id()]);

        return back()->with('success', 'Google Form assignment created.');
    }

    public function destroyGoogleForm(MerchandiserGoogleFormAssignment $form)
    {
        $this->guardAdmin();

        $form->update(['status' => 'inactive']);

        return back()->with('success', 'Google Form assignment deactivated.');
    }

    public function storePlanogram(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'channel_type' => ['nullable', 'string', 'max:16'],
            'reference_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,ppt,pptx', 'max:51200'],
            'playbook_notes' => ['nullable', 'string', 'max:5000'],
            'checklist_items' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $path = null;
        if ($request->hasFile('reference_file')) {
            $path = $request->file('reference_file')->store('merchandiser-planograms', 'public');
        }

        MerchandiserPlanogram::create([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'channel_type' => $validated['channel_type'] ?? null,
            'reference_file_path' => $path,
            'playbook_notes' => $validated['playbook_notes'] ?? null,
            'checklist' => collect(preg_split('/\r\n|\r|\n/', $validated['checklist_items'] ?? ''))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all(),
            'status' => $validated['status'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Planogram reference saved.');
    }

    public function destroyPlanogram(MerchandiserPlanogram $planogram)
    {
        $this->guardAdmin();

        if ($planogram->reference_file_path) {
            Storage::disk('public')->delete($planogram->reference_file_path);
        }

        $planogram->delete();

        return back()->with('success', 'Planogram reference removed.');
    }

    // ── Leave / Claim / Loan Approvals ────────────────────────────────────────

    public function approveLeave(LeaveApplication $leave)
    {
        $this->guardAdmin();
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        return back()->with('success', 'Leave application approved.');
    }

    public function rejectLeave(Request $request, LeaveApplication $leave)
    {
        $this->guardAdmin();
        $leave->update(['status' => 'rejected', 'rejection_reason' => $request->input('reason', '')]);
        return back()->with('success', 'Leave application rejected.');
    }

    public function approveClaim(PettyCashClaim $claim)
    {
        $this->guardAdmin();
        $claim->update(['status' => 'approved']);
        return back()->with('success', 'Claim approved.');
    }

    public function rejectClaim(PettyCashClaim $claim)
    {
        $this->guardAdmin();
        $claim->update(['status' => 'rejected']);
        return back()->with('success', 'Claim rejected.');
    }

    public function approveLoan(SalaryAdvance $loan)
    {
        $this->guardAdmin();
        $loan->update(['status' => 'approved']);
        return back()->with('success', 'Loan approved.');
    }

    public function rejectLoan(SalaryAdvance $loan)
    {
        $this->guardAdmin();
        $loan->update(['status' => 'rejected']);
        return back()->with('success', 'Loan rejected.');
    }

    // ── Payroll Management ────────────────────────────────────────────────────

    public function setPayroll(Request $request, User $user)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'salary' => 'required|numeric|min:0',
        ]);
        $user->update(['salary' => $validated['salary']]);
        return back()->with('success', "Payroll for {$user->name} updated to " . number_format($validated['salary'], 2));
    }

    // ── Broadcast Notifications ───────────────────────────────────────────────

    public function broadcastNotification(Request $request)
    {
        $this->guardAdmin();
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $merchandisers = User::merchandisers()->where('status', 'active')->get();
        foreach ($merchandisers as $m) {
            Notification::create([
                'user_id' => $m->id,
                'title'   => $validated['title'],
                'message' => $validated['message'],
                'type'    => 'announcement',
            ]);
        }
        return back()->with('success', "Notification broadcast to {$merchandisers->count()} active merchandisers.");
    }

    // ── Share Links ───────────────────────────────────────────────────────────

    public function generateShareLink(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'label'                 => 'nullable|string|max:255',
            'show_overview'         => 'boolean',
            'show_tracking'         => 'boolean',
            'show_attendance_chart' => 'boolean',
            'show_top_performers'   => 'boolean',
            'show_assets'           => 'boolean',
            'show_kds'              => 'boolean',
        ]);

        $sections = [
            'show_overview'         => (bool) ($validated['show_overview']         ?? false),
            'show_tracking'         => (bool) ($validated['show_tracking']         ?? false),
            'show_attendance_chart' => (bool) ($validated['show_attendance_chart'] ?? false),
            'show_top_performers'   => (bool) ($validated['show_top_performers']   ?? false),
            'show_assets'           => (bool) ($validated['show_assets']           ?? false),
            'show_kds'              => (bool) ($validated['show_kds']              ?? false),
        ];

        if (!array_filter($sections)) {
            return back()->withErrors(['label' => 'Select at least one section to share.']);
        }

        $report = MerchandiserReport::create([
            'token'           => Str::random(48),
            'created_by'      => auth()->id(),
            'label'           => $validated['label'] ?? 'Client Report',
            'sections_config' => $sections,
            'expires_at'      => now()->addHours(24),
        ]);

        return back()->with('share_url', route('merchandisers.report.view', $report->token))
                     ->with('success', 'Share link generated — valid for 24 hours.');
    }

    public function revokeShareLink(MerchandiserReport $report)
    {
        $this->guardAdmin();
        $report->update(['is_revoked' => true]);
        return back()->with('success', 'Share link revoked.');
    }

    // ── Data Exports ──────────────────────────────────────────────────────────

    public function exportData(Request $request, string $type)
    {
        $this->guardAdmin();
        $format = $request->query('format', 'csv'); // csv or excel

        $rows    = [];
        $headers = [];
        $filename = 'merchandiser_' . $type . '_' . now()->format('Y-m-d');

        switch ($type) {

            case 'merchandisers':
                $headers = ['Name', 'Email', 'Phone', 'Status', 'Key Distributor', 'Region', 'Joined', 'Total Visits', 'Salary (GHS)'];
                $data = User::merchandisers()
                    ->with(['merchandiserKd', 'merchandiserRegion'])
                    ->withCount('merchandiserVisits')
                    ->orderBy('name')->get();
                foreach ($data as $m) {
                    $rows[] = [
                        $m->name, $m->email, $m->phone ?? '',
                        $m->status,
                        $m->merchandiserKd->name ?? '',
                        $m->merchandiserRegion->name ?? '',
                        $m->created_at->format('Y-m-d'),
                        $m->merchandiser_visits_count,
                        $m->salary ?? '',
                    ];
                }
                break;

            case 'attendance':
                $headers = ['Date', 'Time', 'Type', 'Merchandiser', 'Outlet', 'KD', 'Status'];
                $data = MerchandiserAttendance::with(['user', 'outlet'])
                    ->orderByDesc('clock_in_time')->get();
                foreach ($data as $a) {
                    $rows[] = [
                        Carbon::parse($a->clock_in_time)->format('Y-m-d'),
                        Carbon::parse($a->clock_in_time)->format('H:i'),
                        $a->clock_in_type ?? '',
                        $a->user->name ?? '',
                        $a->outlet->name ?? '',
                        $a->user->merchandiserKd->name ?? '',
                        $a->status ?? 'present',
                    ];
                }
                break;

            case 'assets':
                $headers = ['Date', 'Merchandiser', 'Item', 'Qty Out', 'Location', 'Notes'];
                $data = PosmLedger::with('createdBy')->orderByDesc('created_at')->get();
                foreach ($data as $a) {
                    $rows[] = [
                        $a->created_at->format('Y-m-d'),
                        $a->createdBy->name ?? '',
                        $a->item_name,
                        $a->quantity_out,
                        $a->location ?? '',
                        strip_tags($a->notes ?? ''),
                    ];
                }
                break;

            case 'leaves':
                $headers = ['Merchandiser', 'Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason', 'Applied At'];
                $data = LeaveApplication::with('user')
                    ->whereHas('user', fn($q) => $q->merchandisers())
                    ->orderByDesc('created_at')->get();
                foreach ($data as $l) {
                    $start = Carbon::parse($l->start_date);
                    $end   = Carbon::parse($l->end_date);
                    $rows[] = [
                        $l->user->name ?? '',
                        $l->leave_type ?? 'Annual',
                        $start->format('Y-m-d'),
                        $end->format('Y-m-d'),
                        $start->diffInDays($end) + 1,
                        $l->status,
                        strip_tags($l->reason ?? ''),
                        $l->created_at->format('Y-m-d'),
                    ];
                }
                break;

            case 'claims':
                $headers = ['Merchandiser', 'Description', 'Amount (GHS)', 'Status', 'Submitted At'];
                $data = PettyCashClaim::with('user')
                    ->whereHas('user', fn($q) => $q->merchandisers())
                    ->orderByDesc('created_at')->get();
                foreach ($data as $c) {
                    $rows[] = [
                        $c->user->name ?? '',
                        strip_tags($c->description ?? ''),
                        $c->amount ?? '',
                        $c->status,
                        $c->created_at->format('Y-m-d'),
                    ];
                }
                break;

            case 'loans':
                $headers = ['Merchandiser', 'Amount (GHS)', 'Reason', 'Status', 'Submitted At'];
                $data = SalaryAdvance::with('user')
                    ->whereHas('user', fn($q) => $q->merchandisers())
                    ->orderByDesc('created_at')->get();
                foreach ($data as $l) {
                    $rows[] = [
                        $l->user->name ?? '',
                        $l->amount ?? '',
                        strip_tags($l->reason ?? ''),
                        $l->status,
                        $l->created_at->format('Y-m-d'),
                    ];
                }
                break;

            default:
                abort(404, 'Unknown export type.');
        }

        // Build CSV response (opens in Excel automatically)
        $mimeType = 'text/csv';
        $ext      = 'csv';
        if ($format === 'excel') {
            $mimeType = 'application/vnd.ms-excel';
            $ext      = 'xls';
        }

        $callback = function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => "attachment; filename=\"{$filename}.{$ext}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    public function promoteSupervisor(User $user)
    {
        $this->guardAdmin();

        if (! $user->isMerchandiserAccount()) {
            return back()->withErrors(['supervisor' => 'Only merchandiser accounts can be promoted to supervisor.']);
        }

        $user->update([
            'access_role' => User::MERCHANDISER_SUPERVISOR_ROLE,
            'job_level' => 'supervisor',
            'position_title' => $user->position_title ?: 'Merchandiser Supervisor',
            'status' => 'active',
        ]);

        return redirect()
            ->route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
            ->with('success', "{$user->name} is now a merchandiser supervisor.");
    }

    public function demoteSupervisor(User $user)
    {
        $this->guardAdmin();

        if (! $user->isMerchandiserSupervisor()) {
            return back()->withErrors(['supervisor' => "{$user->name} is not currently a merchandiser supervisor."]);
        }

        DB::transaction(function () use ($user) {
            User::where('supervisor_id', $user->id)->update(['supervisor_id' => null]);
            MerchandiserSupervisorAssignment::where('supervisor_id', $user->id)->delete();

            $user->update([
                'access_role' => User::MERCHANDISER_ROLE,
                'job_level' => $user->job_level === 'supervisor' ? 'merchandiser' : $user->job_level,
                'position_title' => $user->position_title === 'Merchandiser Supervisor' ? 'Merchandiser' : $user->position_title,
            ]);
        });

        return redirect()
            ->route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
            ->with('success', "{$user->name} is now a regular merchandiser again.");
    }

    public function assignSupervisor(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'supervisor_id' => ['required', 'exists:users,id'],
            'merchandiser_ids' => ['nullable', 'array'],
            'merchandiser_ids.*' => ['integer', 'exists:users,id'],
            'kd_ids' => ['nullable', 'array'],
            'kd_ids.*' => ['integer', 'exists:key_distributors,id'],
        ]);

        $supervisorId = (int) $validated['supervisor_id'];
        $supervisor = User::merchandiserSupervisors()
            ->where('status', 'active')
            ->find($supervisorId);

        if (! $supervisor) {
            return back()->withErrors(['supervisor_id' => 'Select a promoted merchandiser supervisor.'])->withInput();
        }

        $requestedMerchandiserIds = collect($validated['merchandiser_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $requestedKdIds = collect($validated['kd_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $merchandiserIds = User::merchandisers()
            ->whereIn('id', $requestedMerchandiserIds->all())
            ->where('id', '!=', $supervisorId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $kdIds = KeyDistributor::whereIn('id', $requestedKdIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        DB::transaction(function () use ($supervisorId, $merchandiserIds, $kdIds) {
            User::merchandisers()
                ->where('supervisor_id', $supervisorId)
                ->whereNotIn('id', $merchandiserIds->all())
                ->update(['supervisor_id' => null]);

            if ($merchandiserIds->isNotEmpty()) {
                User::merchandisers()
                    ->whereIn('id', $merchandiserIds->all())
                    ->update(['supervisor_id' => $supervisorId]);
            }

            MerchandiserSupervisorAssignment::where('supervisor_id', $supervisorId)->delete();

            foreach ($merchandiserIds as $merchandiserId) {
                MerchandiserSupervisorAssignment::create([
                    'supervisor_id' => $supervisorId,
                    'merchandiser_id' => $merchandiserId,
                ]);
            }

            foreach ($kdIds as $kdId) {
                MerchandiserSupervisorAssignment::create([
                    'supervisor_id' => $supervisorId,
                    'kd_id' => $kdId,
                ]);
            }
        });

        return redirect()
            ->route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
            ->with('success', 'Supervisor KD and merchandiser assignment updated.');
    }

    public function storePjp(Request $request)
    {
        $this->guardAdmin();

        if (! $request->user()->isMerchandiserSupervisor()) {
            abort(403, 'Only promoted merchandiser supervisors can upload weekly PJPs.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'week_start' => ['required', 'date'],
            'week_end' => ['nullable', 'date', 'after_or_equal:week_start'],
            'kd_ids' => ['nullable', 'array'],
            'kd_ids.*' => ['integer', 'exists:key_distributors,id'],
            'merchandiser_ids' => ['nullable', 'array'],
            'merchandiser_ids.*' => ['integer', 'exists:users,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:25', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pjp_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg'],
        ]);

        $path = $request->hasFile('pjp_file')
            ? $request->file('pjp_file')->store('merchandiser-pjps', 'public')
            : null;

        MerchandiserPjp::create([
            'supervisor_id' => $request->user()->id,
            'uploaded_by' => auth()->id(),
            'title' => $validated['title'],
            'week_start' => $validated['week_start'],
            'week_end' => $validated['week_end'] ?? null,
            'kd_ids' => array_values($validated['kd_ids'] ?? []),
            'merchandiser_ids' => array_values($validated['merchandiser_ids'] ?? []),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'radius_meters' => $validated['radius_meters'],
            'file_path' => $path,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
            ->with('success', 'Supervisor PJP uploaded as draft.');
    }

    public function forwardPjp(MerchandiserPjp $pjp)
    {
        $this->guardAdmin();

        $pjp->update([
            'status' => 'forwarded',
            'forwarded_at' => now(),
        ]);

        NotificationService::send(
            (int) $pjp->supervisor_id,
            'PJP Forwarded for Field Execution',
            "Your PJP '{$pjp->title}' has been forwarded and is ready for geofenced supervisor clock-in.",
            route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
        );

        return back()->with('success', 'PJP forwarded to supervisor.');
    }

    public function activatePjp(MerchandiserPjp $pjp)
    {
        $this->guardAdmin();

        $pjp->update([
            'status' => 'active',
            'forwarded_at' => $pjp->forwarded_at ?: now(),
        ]);

        return back()->with('success', 'PJP activated for geofenced supervisor accountability.');
    }

    public function clockInPjp(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'pjp_id' => ['required', 'exists:merchandiser_pjps,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $pjp = MerchandiserPjp::findOrFail($validated['pjp_id']);

        if ((int) $pjp->supervisor_id !== (int) auth()->id() && !auth()->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Only the assigned supervisor or admin can clock this PJP.');
        }

        if (!in_array($pjp->status, ['forwarded', 'active'], true)) {
            return back()->withErrors(['pjp' => 'This PJP must be forwarded or active before clock-in.']);
        }

        $alreadyClocked = MerchandiserPjpClockin::where('pjp_id', $pjp->id)
            ->where('user_id', auth()->id())
            ->whereDate('clocked_in_at', Carbon::today())
            ->exists();

        if ($alreadyClocked) {
            return back()->withErrors(['pjp' => 'You have already clocked into this PJP today.']);
        }

        $distance = $this->haversineDistance(
            $validated['latitude'],
            $validated['longitude'],
            $pjp->latitude,
            $pjp->longitude
        );

        if ($distance > (float) $pjp->radius_meters) {
            return back()->withErrors([
                'pjp' => "PJP geofencing error: you must be within {$pjp->radius_meters} meters. Your calculated distance is " . round($distance, 1) . ' meters.',
            ]);
        }

        MerchandiserPjpClockin::create([
            'pjp_id' => $pjp->id,
            'user_id' => auth()->id(),
            'clocked_in_at' => now(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'distance_from_pjp' => $distance,
            'status' => 'verified',
        ]);

        MerchandiserLocation::create([
            'user_id' => auth()->id(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => now(),
        ]);

        return back()->with('success', 'Supervisor PJP clock-in verified.');
    }

    public function sendComplianceQuery(Request $request)
    {
        $this->guardAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'channel' => ['required', 'in:in_app,email,sms,email_sms'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'issues' => ['nullable', 'array'],
            'issues.*' => ['string', 'max:80'],
        ]);

        $target = User::findOrFail($validated['user_id']);
        $channel = $validated['channel'];
        $smsAttempted = in_array($channel, ['sms', 'email_sms'], true);

        NotificationService::send(
            $target->id,
            $validated['subject'],
            $validated['message'],
            route('merchandisers.dashboard')
        );

        $query = MerchandiserComplianceQuery::create([
            'user_id' => $target->id,
            'sent_by' => auth()->id(),
            'channel' => $channel,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'issues' => $validated['issues'] ?? [],
            'email_sent' => false,
            'sms_attempted' => $smsAttempted,
            'sms_sent' => false,
            'status' => in_array($channel, ['email', 'sms', 'email_sms'], true) ? 'queued' : 'sent',
        ]);

        if (in_array($channel, ['email', 'sms', 'email_sms'], true)) {
            SendMerchandiserComplianceMessage::dispatch($query->id);
        }

        return redirect()
            ->route('merchandisers.admin.dashboard', ['tab' => 'supervisors'])
            ->with('success', 'Compliance query logged. Email/SMS delivery will continue in the background.');
    }

    private function validatedOutletAssigneeIds(array $ids, int $kdId): ?\Illuminate\Support\Collection
    {
        $assigneeIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($assigneeIds->isEmpty()) {
            return $assigneeIds;
        }

        $validCount = User::merchandisers()
            ->whereIn('id', $assigneeIds->all())
            ->where('kd_id', $kdId)
            ->count();

        return $validCount === $assigneeIds->count() ? $assigneeIds : null;
    }

    private function syncOutletMerchandisers(Outlet $outlet, \Illuminate\Support\Collection $userIds): void
    {
        $syncPayload = $userIds
            ->mapWithKeys(fn (int $userId) => [
                $userId => [
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                ],
            ])
            ->all();

        $outlet->assignedMerchandisers()->sync($syncPayload);
    }

    private function outletDayLabels(): array
    {
        return [
            'all' => 'All days',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            '7' => 'Sunday',
        ];
    }

    private function normalizedOutletRegistrationDay(mixed $value): string
    {
        $day = (string) $value;

        return array_key_exists($day, $this->outletDayLabels()) ? $day : 'all';
    }

    private function outletCreatedRange(Request $request): array
    {
        $timezone = 'Africa/Accra';
        $from = $this->parseOutletCreatedDate($request->input('outlet_created_from'), $timezone);
        $to = $this->parseOutletCreatedDate($request->input('outlet_created_to'), $timezone);

        if ($from && $to && $to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function parseOutletCreatedDate(mixed $value, string $timezone): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function outletCreatedWithinRange(Outlet $outlet, ?Carbon $from, ?Carbon $to): bool
    {
        if (! $outlet->created_at) {
            return false;
        }

        $createdAt = $outlet->created_at->copy()->startOfDay();

        if ($from && $createdAt->lt($from->copy()->startOfDay())) {
            return false;
        }

        if ($to && $createdAt->gt($to->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    private function outletCreatedRangeLabel(?Carbon $from, ?Carbon $to): string
    {
        if ($from && $to) {
            return $from->toDateString() === $to->toDateString()
                ? 'on '.$from->format('d M Y')
                : 'from '.$from->format('d M Y').' to '.$to->format('d M Y');
        }

        if ($from) {
            return 'from '.$from->format('d M Y');
        }

        if ($to) {
            return 'up to '.$to->format('d M Y');
        }

        return 'across all creation dates';
    }

    private function outletMatchesRegistrationDay(Outlet $outlet, string $day): bool
    {
        if ($day === 'all') {
            return true;
        }

        $targetDay = (int) $day;

        if (isset($outlet->pivot->visit_days) && ! empty($outlet->pivot->visit_days)) {
            $visitDays = is_string($outlet->pivot->visit_days)
                ? json_decode($outlet->pivot->visit_days, true)
                : $outlet->pivot->visit_days;

            if (is_array($visitDays) && ! empty($visitDays)) {
                return in_array($targetDay, array_map('intval', $visitDays), true);
            }
        }

        if ($outlet->relationLoaded('assignedMerchandisers') && $outlet->assignedMerchandisers->isNotEmpty()) {
            foreach ($outlet->assignedMerchandisers as $merch) {
                if (! empty($merch->pivot?->visit_days)) {
                    $visitDays = is_string($merch->pivot->visit_days)
                        ? json_decode($merch->pivot->visit_days, true)
                        : $merch->pivot->visit_days;

                    if (is_array($visitDays) && in_array($targetDay, array_map('intval', $visitDays), true)) {
                        return true;
                    }
                }
            }
        }

        return $outlet->created_at && (string) $outlet->created_at->isoWeekday() === $day;
    }

    private function applyOutletRegistrationDayQuery($query, string $day)
    {
        if ($day === 'all') {
            return $query;
        }

        $targetDay = (int) $day;
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $query->whereRaw('DAYOFWEEK(created_at) = ?', [$targetDay === 7 ? 1 : $targetDay + 1]),
            'pgsql' => $query->whereRaw('EXTRACT(ISODOW FROM created_at) = ?', [$targetDay]),
            'sqlite' => $query->whereRaw("CAST(strftime('%w', created_at) AS INTEGER) = ?", [$targetDay === 7 ? 0 : $targetDay]),
            default => $query,
        };
    }

    private function applyOutletCreatedRangeQuery($query, ?Carbon $from, ?Carbon $to)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        return $query;
    }

    private function routePlanningRange(Request $request, string $timezone): array
    {
        $now = Carbon::now($timezone);
        $defaultStart = $now->copy()->startOfWeek()->startOfDay();
        $defaultEnd = $now->copy()->endOfWeek()->endOfDay();

        $from = $this->parseRouteDateTime($request->query('route_from'), $defaultStart, $timezone);
        $to = $this->parseRouteDateTime($request->query('route_to'), $defaultEnd, $timezone);

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function clockInRange(Request $request, string $timezone): array
    {
        $now = Carbon::now($timezone);
        $defaultStart = $now->copy()->startOfDay();
        $defaultEnd = $now->copy()->endOfDay();

        $from = $this->parseClockDate($request->query('clock_from'), $defaultStart, $timezone)->startOfDay();
        $to = $this->parseClockDate($request->query('clock_to'), $defaultEnd, $timezone)->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function parseClockDate(?string $value, Carbon $fallback, string $timezone): Carbon
    {
        if (! $value) {
            return $fallback->copy()->timezone($timezone);
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return $fallback->copy()->timezone($timezone);
        }
    }

    private function clockRangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('l, d M Y');
        }

        return $from->format('d M Y') . ' - ' . $to->format('d M Y');
    }

    private function parseRouteDateTime(?string $value, Carbon $fallback, string $timezone): Carbon
    {
        if (! $value) {
            return $fallback->copy()->timezone($timezone);
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return $fallback->copy()->timezone($timezone);
        }
    }

    private function constrainRouteAssignmentWindow($query, Carbon $from, Carbon $to)
    {
        $startDate = $from->toDateString();
        $endDate = $to->toDateString();

        return $query->where(function ($windowQuery) use ($from, $to, $startDate, $endDate) {
            $windowQuery
                ->where(function ($scheduledQuery) use ($from, $to) {
                    $scheduledQuery
                        ->whereNotNull('assigned_start_at')
                        ->where('assigned_start_at', '<=', $to)
                        ->where(function ($endQuery) use ($from) {
                            $endQuery->whereNull('assigned_end_at')
                                ->orWhere('assigned_end_at', '>=', $from);
                        });
                })
                ->orWhere(function ($legacyQuery) use ($startDate, $endDate) {
                    $legacyQuery
                        ->whereNull('assigned_start_at')
                        ->whereBetween('assigned_date', [$startDate, $endDate]);
                });
        });
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function boundedPercent(int|float $part, int|float $total): float
    {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return min(100.0, max(0.0, round(((float) $part / (float) $total) * 100, 1)));
    }
}
