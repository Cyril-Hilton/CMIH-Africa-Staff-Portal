<?php

namespace App\Http\Controllers\Brands;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandActivation;
use App\Models\BrandConsumerEntry;
use App\Models\BrandFieldActivity;
use App\Models\BrandStaffAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandsPlatformController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->where('platform_status', 'active')
            ->withCount(['activations', 'consumerEntries', 'fieldActivities'])
            ->orderBy('name')
            ->get();

        $stats = [
            'brands' => $brands->count(),
            'live_activations' => BrandActivation::where('status', 'live')->count(),
            'consumer_entries' => BrandConsumerEntry::count(),
            'field_updates' => BrandFieldActivity::count(),
        ];

        return view('brands-platform.index', compact('brands', 'stats'));
    }

    public function show(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $activation = $this->primaryActivation($brand);
        $metrics = $this->brandMetrics($brand);

        return view('brands-platform.show', compact('brand', 'activation', 'metrics'));
    }

    public function agency(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);

        $activation = $this->primaryActivation($brand);
        $metrics = $this->brandMetrics($brand);
        $entriesByGender = $brand->consumerEntries()
            ->selectRaw("COALESCE(NULLIF(gender, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
        $entriesByAge = $brand->consumerEntries()
            ->selectRaw("COALESCE(NULLIF(age_band, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
        $locationPerformance = $brand->fieldActivities()
            ->selectRaw("COALESCE(NULLIF(location, ''), 'Unspecified') as label, SUM(units) as units, COUNT(*) as updates")
            ->groupBy('label')
            ->orderByDesc('units')
            ->take(12)
            ->get();
        $recentActivities = $brand->fieldActivities()
            ->with(['user', 'activation'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('brands-platform.agency', compact(
            'brand',
            'activation',
            'metrics',
            'entriesByGender',
            'entriesByAge',
            'locationPerformance',
            'recentActivities'
        ));
    }

    public function gallery(Request $request, ?string $brand = null): View
    {
        $selectedBrand = $brand ? $this->resolveBrand($brand) : null;

        if ($selectedBrand) {
            $this->guardBrandAccess($request->user(), $selectedBrand);
        } else {
            $this->guardPlatformAdmin($request->user());
        }

        $brands = Brand::query()
            ->where('platform_status', 'active')
            ->orderBy('name')
            ->get();

        $activities = BrandFieldActivity::with(['brand', 'activation', 'user'])
            ->when($selectedBrand, fn ($query) => $query->where('brand_id', $selectedBrand->id))
            ->whereNotNull('evidence_path')
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('brands-platform.gallery', compact('brands', 'selectedBrand', 'activities'));
    }

    public function admin(Request $request): View
    {
        $this->guardPlatformAdmin($request->user());

        $brands = Brand::query()
            ->where('platform_status', 'active')
            ->with(['staffAssignments.user', 'activations'])
            ->orderBy('name')
            ->get();
        $staff = User::internalStaff()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'access_role', 'job_title', 'position_title']);
        $assignments = BrandStaffAssignment::with(['brand', 'user', 'assigner'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('brands-platform.admin', compact('brands', 'staff', 'assignments'));
    }

    public function staffFeed(Request $request): JsonResponse
    {
        $this->guardPlatformAdmin($request->user());

        $staff = User::internalStaff()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'access_role', 'job_title', 'position_title'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'role' => $user->access_role,
                'title' => $user->position_title ?: $user->job_title,
            ]);

        return response()->json(['data' => $staff]);
    }

    public function storeAssignment(Request $request, string $brand): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $brand = $this->resolveBrand($brand);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'in:agency_staff,supporting_staff,brand_admin'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $staff = User::internalStaff()->whereKey($validated['user_id'])->firstOrFail();

        BrandStaffAssignment::updateOrCreate(
            [
                'brand_id' => $brand->id,
                'user_id' => $staff->id,
                'role' => $validated['role'],
            ],
            [
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
                'assigned_by' => $request->user()->id,
            ]
        );

        return back()->with('status', "{$staff->name} has been assigned to {$brand->name}.");
    }

    public function destroyAssignment(Request $request, BrandStaffAssignment $assignment): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $assignment->delete();

        return back()->with('status', 'Brand assignment removed.');
    }

    public function storeConsumerEntry(Request $request, string $brand): RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $activation = $this->primaryActivation($brand);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'age_band' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'result_type' => ['nullable', 'string', 'max:100'],
            'answers' => ['nullable', 'array'],
        ]);

        BrandConsumerEntry::create([
            ...$validated,
            'brand_id' => $brand->id,
            'brand_activation_id' => $activation?->id,
            'source' => $validated['source'] ?? 'consumer_capture',
        ]);

        if ($activation) {
            $activation->increment('actual_reach');
        }

        return back()->with('status', 'Consumer entry saved successfully.');
    }

    public function storeFieldActivity(Request $request, string $brand): RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);
        $activation = $this->primaryActivation($brand);

        $validated = $request->validate([
            'staff_role' => ['required', 'in:agency_staff,supporting_staff,promoter,sales_personnel'],
            'activity_type' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        $evidencePath = $request->hasFile('evidence')
            ? $request->file('evidence')->store('brand-activities', 'public')
            : null;

        BrandFieldActivity::create([
            ...$validated,
            'brand_id' => $brand->id,
            'brand_activation_id' => $activation?->id,
            'user_id' => $request->user()->id,
            'units' => $validated['units'] ?? 0,
            'evidence_path' => $evidencePath,
        ]);

        return back()->with('status', 'Field activity saved successfully.');
    }

    public function clientReport(string $token): View
    {
        $activation = BrandActivation::with(['brand', 'consumerEntries', 'fieldActivities.user'])
            ->where('client_share_token', $token)
            ->firstOrFail();
        $brand = $activation->brand;
        $metrics = $this->brandMetrics($brand);

        return view('brands-platform.client-report', compact('activation', 'brand', 'metrics'));
    }

    private function resolveBrand(string $brand): Brand
    {
        return Brand::query()
            ->where('slug', $brand)
            ->when(ctype_digit($brand), fn ($query) => $query->orWhere('id', (int) $brand))
            ->firstOrFail();
    }

    private function primaryActivation(Brand $brand): ?BrandActivation
    {
        return $brand->activations()
            ->where('status', 'live')
            ->latest()
            ->first()
            ?: $brand->activations()->latest()->first();
    }

    private function brandMetrics(Brand $brand): array
    {
        $activations = $brand->activations()->get();
        $target = (int) $activations->sum('target_reach');
        $consumerEntries = $brand->consumerEntries()->count();
        $reached = max((int) $activations->sum('actual_reach'), $consumerEntries);
        $activities = $brand->fieldActivities()->count();
        $units = (int) $brand->fieldActivities()->sum('units');
        $staff = $brand->staffAssignments()->where('is_active', true)->distinct('user_id')->count('user_id');

        return [
            'activations' => $activations->count(),
            'target' => $target,
            'reached' => $reached,
            'reach_rate' => $target > 0 ? round(min(100, ($reached / $target) * 100), 1) : 0.0,
            'consumer_entries' => $consumerEntries,
            'field_updates' => $activities,
            'units' => $units,
            'assigned_staff' => $staff,
        ];
    }

    private function guardPlatformAdmin(?User $user): void
    {
        if (! $user || ! $user->isCvoOrSuperAdmin()) {
            abort(403, 'Only the Brands Platform admin can manage brand assignments.');
        }
    }

    private function guardBrandAccess(?User $user, Brand $brand): void
    {
        if (! $user) {
            abort(403);
        }

        if ($user->isCvoOrSuperAdmin()) {
            return;
        }

        $hasAssignment = $brand->staffAssignments()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        abort_unless($hasAssignment, 403, 'You have not been assigned to this brand yet.');
    }
}
