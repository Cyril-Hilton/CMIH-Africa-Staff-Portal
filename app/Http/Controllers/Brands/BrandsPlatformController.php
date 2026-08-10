<?php

namespace App\Http\Controllers\Brands;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandActivation;
use App\Models\BrandActivityLog;
use App\Models\BrandConsumerEntry;
use App\Models\BrandFieldActivity;
use App\Models\BrandPublication;
use App\Models\BrandStaffAssignment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandsPlatformController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->where('platform_status', 'active')
            ->withCount(['activations', 'consumerEntries', 'fieldActivities', 'staffAssignments'])
            ->orderBy('name')
            ->get();

        $stats = [
            'brands' => $brands->count(),
            'live_activations' => BrandActivation::where('status', 'live')->count(),
            'consumer_entries' => BrandConsumerEntry::count(),
            'field_updates' => BrandFieldActivity::count(),
            'support_staff' => BrandStaffAssignment::where('is_active', true)->distinct('user_id')->count('user_id'),
        ];
        $recentPublications = BrandPublication::with('brand')
            ->where('status', 'published')
            ->latest('published_at')
            ->latest()
            ->take(6)
            ->get();

        return view('brands-platform.index', compact('brands', 'stats', 'recentPublications'));
    }

    public function show(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $activation = $this->primaryActivation($brand);
        $metrics = $this->brandMetrics($brand);
        $publications = $brand->publications()
            ->where('status', 'published')
            ->latest('published_at')
            ->latest()
            ->take(6)
            ->get();

        $this->logBrandActivity($request, $brand, $activation, 'page_view', 'public_brand');

        return view('brands-platform.show', compact('brand', 'activation', 'metrics', 'publications'));
    }

    public function agency(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);

        $activation = $this->primaryActivation($brand);
        $filters = $this->reportFilters($request);
        $metrics = $this->brandMetrics($brand, $activation, $filters);
        $entriesByGender = $this->consumerEntryQuery($brand, $activation, $filters)
            ->selectRaw("COALESCE(NULLIF(gender, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
        $entriesByAge = $this->consumerEntryQuery($brand, $activation, $filters)
            ->selectRaw("COALESCE(NULLIF(age_band, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
        $locationPerformance = $this->fieldActivityQuery($brand, $activation, $filters)
            ->selectRaw("COALESCE(NULLIF(location, ''), 'Unspecified') as label, SUM(units) as units, SUM(conversion_count) as conversions, COUNT(*) as updates")
            ->groupBy('label')
            ->orderByDesc('units')
            ->take(12)
            ->get();
        $recentActivities = $this->fieldActivityQuery($brand, $activation, $filters)
            ->with(['user', 'activation'])
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $leaderboard = $this->fieldActivityQuery($brand, $activation, $filters)
            ->with('user')
            ->selectRaw('user_id, SUM(units) as units, SUM(conversion_count) as conversions, COUNT(*) as updates')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('units')
            ->take(10)
            ->get();
        $consumerTrend = $this->dailyTrend($this->consumerEntryQuery($brand, $activation, $filters), 'created_at');
        $activityTrend = $this->dailyTrend($this->fieldActivityQuery($brand, $activation, $filters), 'created_at');
        $reportImages = $this->fieldActivityQuery($brand, $activation, $filters)
            ->with('user')
            ->whereNotNull('evidence_path')
            ->latest()
            ->take(12)
            ->get();
        $clientDurations = $this->clientLinkDurations();

        $this->logBrandActivity($request, $brand, $activation, 'page_view', 'agency_dashboard');

        return view('brands-platform.agency', compact(
            'brand',
            'activation',
            'metrics',
            'filters',
            'entriesByGender',
            'entriesByAge',
            'locationPerformance',
            'recentActivities',
            'leaderboard',
            'consumerTrend',
            'activityTrend',
            'reportImages',
            'clientDurations'
        ));
    }

    public function support(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);

        $activation = $this->primaryActivation($brand);
        $filters = $this->reportFilters($request);
        $metrics = $this->brandMetrics($brand, $activation, $filters);
        $assignedLocations = $this->assignedPlanLocationsFor($request->user(), $activation);
        $allowedRoles = $this->allowedStaffRolesFor($request->user(), $brand);
        $myActivities = $this->fieldActivityQuery($brand, $activation, $filters)
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $leaderboard = $this->fieldActivityQuery($brand, $activation, $filters)
            ->with('user')
            ->selectRaw('user_id, SUM(units) as units, SUM(conversion_count) as conversions, COUNT(*) as updates')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('units')
            ->take(10)
            ->get();

        $this->logBrandActivity($request, $brand, $activation, 'page_view', 'support_workspace');

        return view('brands-platform.support', compact('brand', 'activation', 'metrics', 'filters', 'myActivities', 'leaderboard', 'assignedLocations', 'allowedRoles'));
    }

    public function retail(Request $request, string $brand): View
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);

        $activation = $this->primaryActivation($brand);
        $filters = $this->reportFilters($request);
        $metrics = $this->brandMetrics($brand, $activation, $filters);
        $assignedLocations = $this->assignedPlanLocationsFor($request->user(), $activation);
        $redemptions = $this->fieldActivityQuery($brand, $activation, $filters)
            ->whereIn('activity_type', ['reward_redeemed', 'retail_update', 'retail_scan'])
            ->with('user')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $this->logBrandActivity($request, $brand, $activation, 'page_view', 'retail_workspace');

        return view('brands-platform.retail', compact('brand', 'activation', 'metrics', 'filters', 'redemptions', 'assignedLocations'));
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
            ->with([
                'staffAssignments.user',
                'activations' => fn ($query) => $query->latest(),
                'fieldActivities',
            ])
            ->withCount(['activations', 'consumerEntries', 'fieldActivities'])
            ->orderBy('name')
            ->get();
        $staff = User::internalStaff()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'access_role', 'job_title', 'position_title']);
        $assignments = BrandStaffAssignment::with(['brand', 'user', 'assigner'])
            ->latest()
            ->paginate(20, ['*'], 'assignments_page')
            ->withQueryString();
        $activityLogs = BrandActivityLog::with(['brand', 'activation', 'user'])
            ->latest()
            ->paginate(20, ['*'], 'logs_page')
            ->withQueryString();
        $roleProductivity = BrandFieldActivity::query()
            ->selectRaw('staff_role, COUNT(*) as updates, SUM(units) as units, SUM(conversion_count) as conversions')
            ->groupBy('staff_role')
            ->orderByDesc('updates')
            ->get();
        $availableStaff = $staff->count() - BrandStaffAssignment::query()
            ->where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');

        return view('brands-platform.admin', compact('brands', 'staff', 'assignments', 'activityLogs', 'roleProductivity', 'availableStaff'));
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

    public function storeBrand(Request $request): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'activation_name' => ['nullable', 'string', 'max:255'],
            'activation_type' => ['nullable', 'string', 'max:100'],
            'activation_description' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'target_reach' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'target_unit' => ['nullable', 'string', 'max:100'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(['publication', 'consumer_form', 'agency_reporting', 'coupons_rewards', 'geofence', 'retail_scanner', 'merchandising'])],
            'locations' => ['nullable', 'array'],
            'locations.*.name' => ['nullable', 'string', 'max:255'],
            'locations.*.target' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'locations.*.daily_target' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'locations.*.staff_ids' => ['nullable', 'array'],
            'locations.*.staff_ids.*' => ['integer', 'exists:users,id'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,svg', 'max:4096'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:6144'],
        ]);

        $slug = Str::slug($validated['name']);
        $brand = Brand::where('slug', $slug)->first();

        if (! $brand) {
            $brand = new Brand([
                'slug' => $slug,
                'logo_path' => 'images/logo/icon-192.png',
                'logo_dark_path' => 'images/logo/icon-192.png',
            ]);
        }

        $brand->fill([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'Other',
            'headline' => $validated['headline'] ?? null,
            'description' => $validated['description'] ?? null,
            'activation_name' => $validated['activation_name'] ?? null,
            'activation_type' => $validated['activation_type'] ?? null,
            'activation_description' => $validated['activation_description'] ?? null,
            'primary_color' => $validated['primary_color'] ?? '#e50914',
            'secondary_color' => $validated['secondary_color'] ?? '#ffffff',
            'platform_status' => 'active',
        ])->save();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brand-platform/logos', 'public');
            $brand->forceFill(['logo_path' => $path, 'logo_dark_path' => $path])->save();
        }

        if (! empty($validated['activation_name'])) {
            $activationPlan = $this->activationPlanPayload($validated);
            $activation = $brand->activations()->updateOrCreate(
                ['name' => $validated['activation_name']],
                [
                    'activation_type' => $validated['activation_type'] ?? 'activation',
                    'status' => 'live',
                    'starts_at' => $validated['starts_at'] ?? null,
                    'ends_at' => $validated['ends_at'] ?? null,
                    'target_reach' => $validated['target_reach'] ?? 0,
                    'target_unit' => $validated['target_unit'] ?? null,
                    'locations' => $this->normalizeActivationLocations($validated['locations'] ?? []),
                    'activation_plan' => $activationPlan,
                    'description' => $validated['activation_description'] ?? null,
                    'created_by' => $request->user()->id,
                ]
            );

            $this->syncActivationPlanAssignments($brand, $activationPlan, $request->user());

            if ($request->hasFile('banner')) {
                $activation->forceFill([
                    'banner_path' => $request->file('banner')->store('brand-platform/banners', 'public'),
                ])->save();
            }
        }

        $this->logBrandActivity($request, $brand, $brand->activations()->latest()->first(), 'brand_saved', 'admin');
        $this->notifyPlatformAdmins(
            'Brand plan saved',
            "{$brand->name} has a new or updated brand activation plan.",
            route('brands-platform.admin'),
            $request->user()->id
        );

        return back()->with('status', "{$brand->name} brand plan saved.");
    }

    public function storeActivation(Request $request, string $brand): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $brand = $this->resolveBrand($brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'activation_type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:draft,live,completed,paused,archived'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'target_reach' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'target_unit' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(['publication', 'consumer_form', 'agency_reporting', 'coupons_rewards', 'geofence', 'retail_scanner', 'merchandising'])],
            'locations' => ['nullable', 'array'],
            'locations.*.name' => ['nullable', 'string', 'max:255'],
            'locations.*.target' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'locations.*.daily_target' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'locations.*.staff_ids' => ['nullable', 'array'],
            'locations.*.staff_ids.*' => ['integer', 'exists:users,id'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:6144'],
        ]);

        $activationPlan = $this->activationPlanPayload($validated);
        $activation = $brand->activations()->updateOrCreate(
            ['name' => $validated['name']],
            [
                'activation_type' => $validated['activation_type'] ?? 'activation',
                'status' => $validated['status'],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'target_reach' => $validated['target_reach'] ?? 0,
                'target_unit' => $validated['target_unit'] ?? null,
                'locations' => $this->normalizeActivationLocations($validated['locations'] ?? []),
                'activation_plan' => $activationPlan,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        $this->syncActivationPlanAssignments($brand, $activationPlan, $request->user());

        if ($request->hasFile('banner')) {
            $activation->forceFill([
                'banner_path' => $request->file('banner')->store('brand-platform/banners', 'public'),
            ])->save();
        }

        $this->logBrandActivity($request, $brand, $activation, 'activation_saved', 'admin');
        $this->notifyAssignedBrandStaff(
            $brand,
            'Activation plan updated',
            "{$activation->name} has been updated for {$brand->name}.",
            route('brands-platform.agency', $brand->slug ?: $brand->id),
            $request->user()->id
        );

        return back()->with('status', "{$activation->name} activation plan saved.");
    }

    public function storePublication(Request $request, string $brand): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $brand = $this->resolveBrand($brand);

        $validated = $request->validate([
            'brand_activation_id' => ['nullable', 'integer', Rule::exists('brand_activations', 'id')->where('brand_id', $brand->id)],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        $publicationPayload = collect($validated)->except('image')->all();

        $publication = BrandPublication::create([
            ...$publicationPayload,
            'brand_id' => $brand->id,
            'published_at' => $validated['published_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('image')) {
            $publication->forceFill([
                'image_path' => $request->file('image')->store('brand-platform/publications', 'public'),
            ])->save();
        }

        $this->logBrandActivity($request, $brand, $publication->activation, 'publication_saved', 'admin');
        $this->notifyAssignedBrandStaff(
            $brand,
            'Brand publication posted',
            "{$brand->name}: {$publication->title}",
            route('brands-platform.show', $brand->slug ?: $brand->id),
            $request->user()->id
        );

        return back()->with('status', 'Brand publication saved.');
    }

    public function generateClientLink(Request $request, BrandActivation $activation): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());

        $validated = $request->validate([
            'duration' => ['required', Rule::in(array_keys($this->clientLinkDurations()))],
        ]);

        $activation->forceFill([
            'client_share_token' => Str::random(48),
            'client_share_expires_at' => now()->addSeconds($this->clientLinkDurations()[$validated['duration']]['seconds']),
        ])->save();

        $this->logBrandActivity($request, $activation->brand, $activation, 'client_link_generated', 'admin', [
            'duration' => $validated['duration'],
            'expires_at' => $activation->client_share_expires_at?->toIso8601String(),
        ]);
        $this->notifyAssignedBrandStaff(
            $activation->brand,
            'Client report link generated',
            "{$activation->brand->name} client view is available until {$activation->client_share_expires_at?->format('M d, Y H:i')}.",
            route('brands-platform.agency', $activation->brand->slug ?: $activation->brand->id),
            $request->user()->id
        );

        return back()->with('status', 'Temporary client report link generated.')
            ->with('client_link', route('brands-platform.client-report', $activation->client_share_token));
    }

    public function storeAssignment(Request $request, string $brand): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $brand = $this->resolveBrand($brand);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(BrandStaffAssignment::ROLES)],
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

        $this->logBrandActivity($request, $brand, null, 'staff_assigned', 'admin', [
            'staff_id' => $staff->id,
            'role' => $validated['role'],
        ]);
        NotificationService::send(
            $staff->id,
            'Brand access granted',
            "You have been assigned to {$brand->name} as ".Str::headline($validated['role']).'.',
            route('brands-platform.agency', $brand->slug ?: $brand->id)
        );

        return back()->with('status', "{$staff->name} has been assigned to {$brand->name}.");
    }

    public function destroyAssignment(Request $request, BrandStaffAssignment $assignment): RedirectResponse
    {
        $this->guardPlatformAdmin($request->user());
        $brand = $assignment->brand;
        $staffId = $assignment->user_id;
        NotificationService::send(
            $staffId,
            'Brand access removed',
            "Your {$brand?->name} brand assignment has been removed.",
            route('brands-platform.index')
        );
        $assignment->delete();

        $this->logBrandActivity($request, $brand, null, 'staff_unassigned', 'admin', ['staff_id' => $staffId]);

        return back()->with('status', 'Brand assignment removed.');
    }

    public function storeConsumerEntry(Request $request, string $brand): RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $activation = $this->primaryActivation($brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'age_band' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'result_type' => ['nullable', 'string', 'max:100'],
            'current_choice' => ['nullable', 'string', 'max:255'],
            'purchase_intent' => ['nullable', 'string', 'max:100'],
            'preferred_channel' => ['nullable', 'string', 'max:255'],
            'is_new_to_brand' => ['nullable', 'boolean'],
            'marketing_consent' => ['nullable', 'boolean'],
            'data_consent' => ['accepted'],
            'answers' => ['nullable', 'array'],
        ]);

        $otpCode = (string) random_int(100000, 999999);
        $entry = BrandConsumerEntry::create([
            ...$validated,
            'brand_id' => $brand->id,
            'brand_activation_id' => $activation?->id,
            'source' => $validated['source'] ?? 'consumer_capture',
            'marketing_consent' => (bool) ($validated['marketing_consent'] ?? false),
            'data_consent' => true,
            'verification_token' => Str::random(48),
            'otp_code' => $otpCode,
        ]);

        if ($activation) {
            $activation->increment('actual_reach');
        }

        $this->logBrandActivity($request, $brand, $activation, 'consumer_entry_created', 'consumer', [
            'entry_id' => $entry->id,
            'location' => $entry->location,
        ]);
        $this->notifyAssignedBrandStaff(
            $brand,
            'New consumer entry',
            "{$entry->name} was captured for {$brand->name}.",
            route('brands-platform.agency', $brand->slug ?: $brand->id)
        );

        return redirect()
            ->route('brands-platform.consumer-entry.verify', [$brand->slug ?: $brand->id, $entry->verification_token])
            ->with('status', 'Consumer entry saved. Enter the OTP to complete verification.')
            ->with('otp_preview', app()->environment('production') ? null : $otpCode);
    }

    public function verifyConsumerEntry(Request $request, string $brand, string $token): View|RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $entry = $brand->consumerEntries()
            ->where('verification_token', $token)
            ->firstOrFail();

        if ($entry->otp_verified_at) {
            return view('brands-platform.consumer-verify', compact('brand', 'entry'));
        }

        return view('brands-platform.consumer-verify', compact('brand', 'entry'));
    }

    public function completeConsumerVerification(Request $request, string $brand, string $token): RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $entry = $brand->consumerEntries()
            ->where('verification_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'otp_code' => ['required', 'string', 'max:12'],
        ]);

        if (! hash_equals((string) $entry->otp_code, (string) $validated['otp_code'])) {
            return back()->withErrors(['otp_code' => 'The verification code is not correct.'])->withInput();
        }

        if (! $entry->otp_verified_at) {
            $entry->forceFill([
                'otp_verified_at' => now(),
                'reward_code' => strtoupper(Str::slug(Str::limit($brand->name, 3, ''), '')).'-'.Str::upper(Str::random(8)),
            ])->save();
        }

        $this->logBrandActivity($request, $brand, $entry->activation, 'consumer_verified', 'consumer', [
            'entry_id' => $entry->id,
        ]);
        $this->notifyAssignedBrandStaff(
            $brand,
            'Consumer verified',
            "{$entry->name} completed OTP verification for {$brand->name}.",
            route('brands-platform.agency', $brand->slug ?: $brand->id)
        );

        return redirect()
            ->route('brands-platform.consumer-entry.verify', [$brand->slug ?: $brand->id, $entry->verification_token])
            ->with('status', 'Phone verified. Reward code issued.');
    }

    public function storeFieldActivity(Request $request, string $brand): RedirectResponse
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);
        $activation = $this->primaryActivation($brand);

        $validated = $request->validate([
            'staff_role' => ['required', Rule::in(BrandStaffAssignment::ROLES)],
            'activity_type' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'conversion_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'transaction_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        abort_unless(
            in_array($validated['staff_role'], $this->allowedStaffRolesFor($request->user(), $brand), true),
            403,
            'Your brand assignment does not allow this staff role.'
        );

        $evidencePath = $request->hasFile('evidence')
            ? $request->file('evidence')->store('brand-activities', 'public')
            : null;

        $activity = BrandFieldActivity::create([
            ...$validated,
            'brand_id' => $brand->id,
            'brand_activation_id' => $activation?->id,
            'user_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'recorded',
            'units' => $validated['units'] ?? 0,
            'conversion_count' => $validated['conversion_count'] ?? 0,
            'evidence_path' => $evidencePath,
        ]);

        $this->logBrandActivity($request, $brand, $activation, 'field_activity_created', 'field', [
            'activity_id' => $activity->id,
            'role' => $activity->staff_role,
            'activity_type' => $activity->activity_type,
        ]);
        $this->notifyAssignedBrandStaff(
            $brand,
            'Brand field update',
            "{$request->user()->name} recorded ".Str::headline($activity->activity_type)." for {$brand->name}.",
            route('brands-platform.agency', $brand->slug ?: $brand->id),
            $request->user()->id
        );

        return back()->with('status', 'Field activity saved successfully.');
    }

    public function exportReport(Request $request, string $brand, string $type): StreamedResponse
    {
        $brand = $this->resolveBrand($brand);
        $this->guardBrandAccess($request->user(), $brand);

        $activation = $this->primaryActivation($brand);
        $filters = $this->reportFilters($request);
        $filename = Str::slug($brand->name.'-'.$type.'-report-'.now()->format('Ymd-His')).'.csv';

        $this->logBrandActivity($request, $brand, $activation, 'report_exported', 'agency', ['type' => $type]);

        return Response::streamDownload(function () use ($brand, $activation, $filters, $type) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', Str::headline($type)]);
            fputcsv($handle, ['Brand', $brand->name]);
            fputcsv($handle, ['Activation', $activation?->name ?: 'All']);
            fputcsv($handle, []);

            if ($type === 'consumer-insights') {
                fputcsv($handle, ['Name', 'Phone', 'Age', 'Gender', 'Location', 'Current Choice', 'Intent', 'Preferred Channel', 'New To Brand', 'Marketing Consent', 'Verified At']);
                $this->consumerEntryQuery($brand, $activation, $filters)
                    ->latest()
                    ->chunk(200, function ($entries) use ($handle) {
                        foreach ($entries as $entry) {
                            fputcsv($handle, [
                                $entry->name,
                                $entry->phone,
                                $entry->age_band,
                                $entry->gender,
                                $entry->location,
                                $entry->current_choice,
                                $entry->purchase_intent,
                                $entry->preferred_channel,
                                $entry->is_new_to_brand ? 'Yes' : 'No',
                                $entry->marketing_consent ? 'Yes' : 'No',
                                $entry->otp_verified_at?->toDateTimeString(),
                            ]);
                        }
                    });
            } else {
                fputcsv($handle, ['Time', 'Staff', 'Role', 'Activity', 'Status', 'Location', 'Units', 'Conversions', 'Value', 'Reference', 'Evidence Image', 'Notes']);
                $this->fieldActivityQuery($brand, $activation, $filters)
                    ->with('user')
                    ->latest()
                    ->chunk(200, function ($activities) use ($handle) {
                        foreach ($activities as $activity) {
                            fputcsv($handle, [
                                $activity->created_at?->toDateTimeString(),
                                $activity->user?->name,
                                Str::headline($activity->staff_role),
                                Str::headline($activity->activity_type),
                                Str::headline($activity->status),
                                $activity->location,
                                $activity->units,
                                $activity->conversion_count,
                                $activity->transaction_value,
                                $activity->reference_code,
                                self::storageUrl($activity->evidence_path),
                                $activity->notes,
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function clientReport(string $token): View
    {
        $activation = BrandActivation::with(['brand', 'consumerEntries', 'fieldActivities.user'])
            ->where('client_share_token', $token)
            ->firstOrFail();

        abort_unless($activation->clientShareIsActive(), 404);

        $brand = $activation->brand;
        $metrics = $this->brandMetrics($brand, $activation);
        $reportImages = $activation->fieldActivities()
            ->with('user')
            ->whereNotNull('evidence_path')
            ->latest()
            ->take(12)
            ->get();

        return view('brands-platform.client-report', compact('activation', 'brand', 'metrics', 'reportImages'));
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

    private function brandMetrics(Brand $brand, ?BrandActivation $activation = null, array $filters = []): array
    {
        $activationQuery = $brand->activations();
        if ($activation) {
            $activationQuery->whereKey($activation->id);
        }

        $activations = $activationQuery->get();
        $target = (int) $activations->sum('target_reach');
        $consumerEntriesQuery = $this->consumerEntryQuery($brand, $activation, $filters);
        $fieldActivityQuery = $this->fieldActivityQuery($brand, $activation, $filters);
        $consumerEntries = (clone $consumerEntriesQuery)->count();
        $verifiedEntries = (clone $consumerEntriesQuery)->whereNotNull('otp_verified_at')->count();
        $fieldUpdates = (clone $fieldActivityQuery)->count();
        $units = (int) (clone $fieldActivityQuery)->sum('units');
        $conversions = (int) (clone $fieldActivityQuery)->sum('conversion_count');
        $reached = max((int) $activations->sum('actual_reach'), $consumerEntries);
        $staff = $brand->staffAssignments()->where('is_active', true)->distinct('user_id')->count('user_id');
        $highIntent = (clone $consumerEntriesQuery)
            ->whereIn('purchase_intent', ['Definitely', 'High intent', 'Very likely', 'Likely'])
            ->count();
        $newAudience = (clone $consumerEntriesQuery)->where('is_new_to_brand', true)->count();
        $marketingConsent = (clone $consumerEntriesQuery)->where('marketing_consent', true)->count();

        return [
            'activations' => $activations->count(),
            'target' => $target,
            'target_unit' => $activation?->target_unit ?: 'Consumer Actions',
            'reached' => $reached,
            'reach_rate' => $target > 0 ? round(min(100, ($reached / $target) * 100), 1) : 0.0,
            'consumer_entries' => $consumerEntries,
            'verified_entries' => $verifiedEntries,
            'verification_rate' => $consumerEntries > 0 ? round(($verifiedEntries / $consumerEntries) * 100, 1) : 0.0,
            'field_updates' => $fieldUpdates,
            'units' => $units,
            'conversions' => $conversions,
            'conversion_rate' => $consumerEntries > 0 ? round(($conversions / $consumerEntries) * 100, 1) : 0.0,
            'assigned_staff' => $staff,
            'high_intent_rate' => $consumerEntries > 0 ? round(($highIntent / $consumerEntries) * 100, 1) : 0.0,
            'new_audience_rate' => $consumerEntries > 0 ? round(($newAudience / $consumerEntries) * 100, 1) : 0.0,
            'marketing_consent_rate' => $consumerEntries > 0 ? round(($marketingConsent / $consumerEntries) * 100, 1) : 0.0,
        ];
    }

    private function consumerEntryQuery(Brand $brand, ?BrandActivation $activation = null, array $filters = [])
    {
        return $brand->consumerEntries()
            ->when($activation, fn ($query) => $query->where('brand_activation_id', $activation->id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to));
    }

    private function fieldActivityQuery(Brand $brand, ?BrandActivation $activation = null, array $filters = [])
    {
        return $brand->fieldActivities()
            ->when($activation, fn ($query) => $query->where('brand_activation_id', $activation->id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
    }

    private function reportFilters(Request $request): array
    {
        return [
            'from' => $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : null,
            'to' => $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : null,
            'status' => $request->filled('status') ? $request->input('status') : null,
        ];
    }

    private function dailyTrend($query, string $dateColumn): array
    {
        $rows = $query
            ->orderBy($dateColumn)
            ->get([$dateColumn])
            ->groupBy(fn ($row) => Carbon::parse($row->{$dateColumn})->format('M d'))
            ->map(fn ($rows) => $rows->count());

        return [
            'labels' => $rows->keys()->values()->all(),
            'data' => $rows->values()->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function normalizeActivationLocations(array $locations): array
    {
        return collect($locations)
            ->map(function ($location) {
                $name = trim((string) ($location['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'target' => (int) ($location['target'] ?? 0),
                    'daily_target' => (int) ($location['daily_target'] ?? 0),
                    'staff_ids' => collect($location['staff_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function activationPlanPayload(array $validated): array
    {
        $locations = $this->normalizeActivationLocations($validated['locations'] ?? []);
        $startsAt = ! empty($validated['starts_at']) ? Carbon::parse($validated['starts_at']) : null;
        $endsAt = ! empty($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : null;
        $days = $startsAt && $endsAt ? $startsAt->diffInDays($endsAt) + 1 : 0;
        $locationTarget = collect($locations)->sum('target');
        $dailyTarget = collect($locations)->sum('daily_target') * max(1, $days);

        return [
            'locations' => $locations,
            'modules' => collect($validated['modules'] ?? ['publication', 'consumer_form', 'agency_reporting'])
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'days' => $days,
            'location_target' => $locationTarget,
            'daily_target_total' => $dailyTarget,
            'assigned_staff_ids' => collect($locations)->flatMap(fn ($location) => $location['staff_ids'] ?? [])->unique()->values()->all(),
            'unallocated_target' => max(0, (int) ($validated['target_reach'] ?? 0) - $locationTarget),
        ];
    }

    private function syncActivationPlanAssignments(Brand $brand, array $plan, User $assigner): void
    {
        $staffIds = collect($plan['assigned_staff_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($staffIds as $staffId) {
            BrandStaffAssignment::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'user_id' => $staffId,
                    'role' => BrandStaffAssignment::ROLE_SUPPORT,
                ],
                [
                    'is_active' => true,
                    'notes' => 'Auto-assigned from activation execution plan.',
                    'assigned_by' => $assigner->id,
                ]
            );
        }

        if ($staffIds->isNotEmpty()) {
            NotificationService::sendToMany(
                $staffIds->all(),
                'Brand activation assignment',
                "You have been assigned to {$brand->name}'s activation execution plan.",
                route('brands-platform.support', $brand->slug ?: $brand->id)
            );
        }
    }

    private function assignedPlanLocationsFor(User $user, ?BrandActivation $activation): array
    {
        if (! $activation) {
            return [];
        }

        $locations = collect($activation->activation_plan['locations'] ?? []);

        if ($user->isCvoOrSuperAdmin()) {
            return $locations->values()->all();
        }

        return $locations
            ->filter(fn ($location) => collect($location['staff_ids'] ?? [])->map(fn ($id) => (int) $id)->contains((int) $user->id))
            ->values()
            ->all();
    }

    private function clientLinkDurations(): array
    {
        return [
            '1h' => ['label' => '1 hour', 'seconds' => 3600],
            '6h' => ['label' => '6 hours', 'seconds' => 21600],
            '24h' => ['label' => '24 hours', 'seconds' => 86400],
            '3d' => ['label' => '3 days', 'seconds' => 259200],
            '7d' => ['label' => '7 days', 'seconds' => 604800],
            '14d' => ['label' => '14 days', 'seconds' => 1209600],
            '30d' => ['label' => '30 days', 'seconds' => 2592000],
        ];
    }

    private function assignedRoles(User $user, Brand $brand): array
    {
        if ($user->isCvoOrSuperAdmin()) {
            return BrandStaffAssignment::ROLES;
        }

        return $brand->staffAssignments()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('role')
            ->all();
    }

    private function allowedStaffRolesFor(User $user, Brand $brand): array
    {
        $roles = $this->assignedRoles($user, $brand);

        if (in_array(BrandStaffAssignment::ROLE_ADMIN, $roles, true)) {
            return BrandStaffAssignment::ROLES;
        }

        if (in_array(BrandStaffAssignment::ROLE_SUPPORT, $roles, true)) {
            $roles = array_merge($roles, [
                BrandStaffAssignment::ROLE_PROMOTER,
                BrandStaffAssignment::ROLE_SALES,
            ]);
        }

        return array_values(array_unique($roles));
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

    private function logBrandActivity(Request $request, ?Brand $brand, ?BrandActivation $activation, string $action, string $context, array $metadata = []): void
    {
        if (! Schema::hasTable('brand_activity_logs')) {
            return;
        }

        BrandActivityLog::create([
            'brand_id' => $brand?->id,
            'brand_activation_id' => $activation?->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'context' => $context,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'metadata' => $metadata,
        ]);
    }

    private function notifyAssignedBrandStaff(Brand $brand, string $title, string $message, ?string $url = null, ?int $excludeUserId = null): void
    {
        $ids = $brand->staffAssignments()
            ->where('is_active', true)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->merge(NotificationService::activeSuperAdminIds($excludeUserId))
            ->when($excludeUserId, fn ($collection) => $collection->reject(fn ($id) => (int) $id === $excludeUserId))
            ->unique()
            ->values()
            ->all();

        NotificationService::sendToMany($ids, $title, $message, $url);
    }

    private function notifyPlatformAdmins(string $title, string $message, ?string $url = null, ?int $excludeUserId = null): void
    {
        NotificationService::sendToMany(
            NotificationService::activeSuperAdminIds($excludeUserId),
            $title,
            $message,
            $url
        );
    }

    public static function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
