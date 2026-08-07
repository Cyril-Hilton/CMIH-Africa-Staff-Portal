<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Region;
use App\Models\KeyDistributor;
use App\Models\Outlet;
use App\Models\Sku;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserLocation;
use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MerchandiserAdminController extends Controller
{
    /**
     * Live Tracking Dashboard
     */
    public function tracking(Request $request)
    {
        $activeMerchandisers = User::where('access_role', 'merchandiser')
            ->where('status', 'active')
            ->get();

        // Get latest location for each active merchandiser
        $latestLocations = [];
        foreach ($activeMerchandisers as $m) {
            $latest = MerchandiserLocation::where('user_id', $m->id)
                ->orderBy('recorded_at', 'desc')
                ->first();
            if ($latest) {
                $latestLocations[] = [
                    'merchandiser_id' => $m->id,
                    'name' => $m->name,
                    'phone' => $m->phone,
                    'latitude' => (float) $latest->latitude,
                    'longitude' => (float) $latest->longitude,
                    'recorded_at' => $latest->recorded_at->diffForHumans(),
                ];
            }
        }

        // Selected merchandiser path/breadcrumbs
        $selectedPath = [];
        $selectedUser = null;
        if ($request->has('merchandiser_id')) {
            $selectedUser = User::find($request->query('merchandiser_id'));
            if ($selectedUser) {
                $selectedPath = MerchandiserLocation::where('user_id', $selectedUser->id)
                    ->whereDate('recorded_at', Carbon::today())
                    ->orderBy('recorded_at', 'asc')
                    ->get()
                    ->map(function ($loc) {
                        return [
                            'latitude' => (float) $loc->latitude,
                            'longitude' => (float) $loc->longitude,
                            'time' => $loc->recorded_at->format('H:i'),
                        ];
                    });
            }
        }

        // Core Analytics metrics
        $totalVisits = DB::table('merchandiser_visits')->count();
        $totalOrders = DB::table('merchandiser_orders')->count();

        // Top ordering SKUs
        $topSkus = DB::table('merchandiser_order_items')
            ->join('skus', 'merchandiser_order_items.sku_id', '=', 'skus.id')
            ->select('skus.name', DB::raw('SUM(merchandiser_order_items.quantity) as total_qty'))
            ->groupBy('skus.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Store Visit movement trend (last 7 days)
        $rawVisits = DB::table('merchandiser_visits')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->get(['created_at']);
        
        $dailyVisits = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyVisits[$dateStr] = 0;
        }
        foreach ($rawVisits as $v) {
            $d = Carbon::parse($v->created_at)->format('Y-m-d');
            if (isset($dailyVisits[$d])) {
                $dailyVisits[$d]++;
            }
        }

        // Merchandiser performance leaderboard
        $leaderboard = User::where('access_role', 'merchandiser')
            ->where('status', 'active')
            ->withCount('merchandiserVisits')
            ->orderByDesc('merchandiser_visits_count')
            ->limit(10)
            ->get();

        return view('portal.merchandiser_admin.tracking', compact(
            'activeMerchandisers', 'latestLocations', 'selectedUser', 'selectedPath',
            'totalVisits', 'totalOrders', 'topSkus', 'dailyVisits', 'leaderboard'
        ));
    }

    /**
     * Pairings and Activations Panel
     */
    public function pairings()
    {
        $pendingMerchandisers = User::where('access_role', 'merchandiser')
            ->where('status', 'pending')
            ->get();

        $activeMerchandisers = User::where('access_role', 'merchandiser')
            ->where('status', 'active')
            ->get();

        $supervisors = User::where('access_role', 'manager')
            ->orWhere('position_title', 'Supervisor')
            ->orderBy('name')
            ->get();

        $tms = User::where('position_title', 'Territory Manager')->orderBy('name')->get();
        $dsrs = User::where('position_title', 'DSR')->orderBy('name')->get();
        $rsms = User::where('position_title', 'RSM')->orderBy('name')->get();

        $regions = Region::orderBy('name')->get();
        $kds = KeyDistributor::orderBy('name')->get();

        return view('portal.merchandiser_admin.pairings', compact(
            'pendingMerchandisers', 'activeMerchandisers', 'supervisors',
            'tms', 'dsrs', 'rsms', 'regions', 'kds'
        ));
    }

    /**
     * Pair and Activate Merchandiser
     */
    public function pair(Request $request, User $user)
    {
        if ($user->access_role !== 'merchandiser') {
            abort(403);
        }

        $validated = $request->validate([
            'supervisor_id' => ['required', 'exists:users,id'],
            'kd_id' => ['required', 'exists:key_distributors,id'],
            'region_id' => ['required', 'exists:regions,id'],
            'tm_id' => ['nullable', 'exists:users,id'],
            'dsr_id' => ['nullable', 'exists:users,id'],
            'rsm_id' => ['nullable', 'exists:users,id'],
        ]);

        $user->update(array_merge($validated, ['status' => 'active']));

        return back()->with('status', "Merchandiser {$user->name} successfully paired and activated!");
    }

    /**
     * SKU Configs Panel
     */
    public function skus()
    {
        $skus = Sku::orderBy('name')->get();
        return view('portal.merchandiser_admin.skus', compact('skus'));
    }

    /**
     * Add Sku
     */
    public function storeSku(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:skus,name'],
        ]);

        Sku::create($validated);

        return back()->with('status', 'SKU created successfully.');
    }

    /**
     * Delete Sku
     */
    public function destroySku(Sku $sku)
    {
        $sku->delete();
        return back()->with('status', 'SKU deleted successfully.');
    }

    /**
     * Key Distributors Config Panel
     */
    public function kds()
    {
        $kds = KeyDistributor::with('region')->orderBy('name')->get();
        $regions = Region::orderBy('name')->get();
        return view('portal.merchandiser_admin.kds', compact('kds', 'regions'));
    }

    /**
     * Add KD
     */
    public function storeKd(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'exists:regions,id'],
            'address' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        KeyDistributor::create($validated);

        return back()->with('status', 'Key Distributor registered successfully.');
    }

    /**
     * Update KD
     */
    public function updateKd(Request $request, KeyDistributor $kd)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'exists:regions,id'],
            'address' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        // Check if moving to a different region and check if we need to show reassignment wizard
        if ($kd->region_id != $validated['region_id']) {
            // Region change moves the KD, but TMs/Merchandisers mapped to it must remain.
            // Under normal conditions, changing region is allowed directly as KDs stay,
            // but let's notify the user if they want to audit assignments.
        }

        $kd->update($validated);

        return back()->with('status', 'Key Distributor updated successfully.');
    }

    /**
     * Check KD Dependents (for delete check)
     */
    public function checkKdDependents(KeyDistributor $kd)
    {
        $dependents = $this->getKdDependents($kd);

        return response()->json([
            'has_dependents' => count($dependents['merchandisers']) > 0 || 
                                count($dependents['tms']) > 0 || 
                                count($dependents['dsrs']) > 0 ||
                                count($dependents['outlets']) > 0,
            'dependents' => $dependents
        ]);
    }

    /**
     * Delete KD or trigger reassignment wizard
     */
    public function destroyKd(Request $request, KeyDistributor $kd)
    {
        $dependents = $this->getKdDependents($kd);
        $hasDependents = count($dependents['merchandisers']) > 0 || 
                         count($dependents['tms']) > 0 || 
                         count($dependents['dsrs']) > 0 ||
                         count($dependents['outlets']) > 0;

        if ($hasDependents) {
            // Reassignment target KD is required
            if (!$request->has('reassign_kd_id')) {
                return back()->withErrors([
                    'kd_error' => "Cannot delete KD: {$kd->name} has dependent outlets, merchandisers, DSRs, or TMs. Please reassign them first."
                ])->with([
                    'show_reassign_wizard_for' => $kd->id,
                    'dependents' => $dependents
                ]);
            }

            $request->validate([
                'reassign_kd_id' => ['required', 'exists:key_distributors,id', 'different:kd_id'],
            ]);

            $newKdId = $request->input('reassign_kd_id');

            DB::transaction(function () use ($kd, $newKdId) {
                // Reassign merchandisers, TMs, and DSRs mapped to this KD
                User::where('kd_id', $kd->id)->update(['kd_id' => $newKdId]);

                // Reassign outlets mapped to this KD
                Outlet::where('kd_id', $kd->id)->update(['kd_id' => $newKdId]);

                $kd->delete();
            });

            return redirect()->route('portal.merchandisers-admin.kds')->with('status', 'Dependents reassigned and Key Distributor deleted successfully.');
        }

        $kd->delete();

        return redirect()->route('portal.merchandisers-admin.kds')->with('status', 'Key Distributor deleted successfully.');
    }

    /**
     * Outlets Config Panel
     */
    public function outlets()
    {
        $outlets = Outlet::with('keyDistributor')->orderBy('name')->get();
        $kds = KeyDistributor::orderBy('name')->get();
        return view('portal.merchandiser_admin.outlets', compact('outlets', 'kds'));
    }

    /**
     * Add Outlet
     */
    public function storeOutlet(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:outlets,code'],
            'kd_id' => ['required', 'exists:key_distributors,id'],
            'channel_type' => ['required', 'in:SSM,GT'],
            'address' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        Outlet::create($validated);

        return back()->with('status', 'Outlet registered successfully.');
    }

    /**
     * Update Outlet (GPS coordinates locked for field, editable only by admin)
     */
    public function updateOutlet(Request $request, Outlet $outlet)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kd_id' => ['required', 'exists:key_distributors,id'],
            'channel_type' => ['required', 'in:SSM,GT'],
            'address' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $outlet->update($validated);

        return back()->with('status', 'Outlet updated successfully.');
    }

    /**
     * Delete Outlet
     */
    public function destroyOutlet(Outlet $outlet)
    {
        $outlet->delete();
        return back()->with('status', 'Outlet deleted successfully.');
    }

    /**
     * Helper to load dependents of a KD
     */
    private function getKdDependents(KeyDistributor $kd)
    {
        return [
            'merchandisers' => User::where('kd_id', $kd->id)->where('access_role', 'merchandiser')->get(['id', 'name']),
            'tms' => User::where('kd_id', $kd->id)->where('position_title', 'Territory Manager')->get(['id', 'name']),
            'dsrs' => User::where('kd_id', $kd->id)->where('position_title', 'DSR')->get(['id', 'name']),
            'outlets' => Outlet::where('kd_id', $kd->id)->get(['id', 'name']),
        ];
    }
}
