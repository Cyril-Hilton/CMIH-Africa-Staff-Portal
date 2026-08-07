<?php

namespace App\Http\Controllers\Merchandiser;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeMerchandiserShelfPhoto;
use App\Models\User;
use App\Models\Region;
use App\Models\KeyDistributor;
use App\Models\Outlet;
use App\Models\Sku;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserGoogleFormAssignment;
use App\Models\MerchandiserGoogleFormSubmission;
use App\Models\MerchandiserLocation;
use App\Models\MerchandiserNativeFormSubmission;
use App\Models\MerchandiserOutletAssignment;
use App\Models\MerchandiserPcmClockin;
use App\Models\MerchandiserPlanogram;
use App\Models\MerchandiserVisit;
use App\Models\MerchandiserVisitSku;
use App\Models\MerchandiserOrder;
use App\Models\MerchandiserOrderItem;
use App\Models\SiteContent;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\SalaryAdvance;
use App\Models\Appraisal;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\PosmLedger;
use App\Models\Announcement;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\MerchandiserRoutePlanner;
use App\Services\PerfectStoreFormTemplate;
use App\Support\MerchandiserClockWindows;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MerchandiserController extends Controller
{
    /**
     * Show Choice Gateway
     */
    public function gateway()
    {
        if (Auth::check()) {
            if (Auth::user()->access_role === 'merchandiser') {
                return redirect()->route('merchandisers.dashboard');
            }
            if (Auth::user()->isMerchandiserPortalAdmin()) {
                return redirect()->route('merchandisers.admin.dashboard');
            }
            return redirect()->route('dashboard');
        }
        return view('merchandisers.gateway');
    }

    /**
     * Show Merchandiser Login
     */
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->access_role === 'merchandiser') {
                return redirect()->route('merchandisers.dashboard');
            }

            if ($user->isMerchandiserPortalAdmin()) {
                return redirect()->route('merchandisers.admin.dashboard');
            }

            return redirect()->route('dashboard');
        }
        return view('merchandisers.login');
    }

    /**
     * Handle Login Action
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email = strtolower($credentials['email']);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first()
            ?: User::whereRaw('LOWER(contact_email) = ?', [$email])->first();

        if ($user) {
            if ($user->status === 'pending') {
                return back()->withErrors(['email' => 'Your account is pending activation. Please contact your Brands Team admin.']);
            }
            if ($user->status === 'archived') {
                return back()->withErrors(['email' => 'This account has been archived.']);
            }
            if ($user->status === 'suspended') {
                return back()->withErrors(['email' => 'Your account is suspended.']);
            }
            if ($user->access_role !== 'merchandiser' && ! $user->isMerchandiserPortalAdmin()) {
                return back()->withErrors(['email' => 'This login form is for external Merchandisers and Brands Team merchandiser admins. Other staff should use the Staff Login page.']);
            }

            if (Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $request->boolean('remember'))) {
                $request->session()->regenerate();
                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                    'last_login_user_agent' => $request->userAgent()
                ]);
                // Brands/admin users go to the merch admin hub; external merchandisers to their dashboard.
                if ($user->isMerchandiserPortalAdmin()) {
                    return redirect()->route('merchandisers.admin.dashboard');
                }

                return redirect()->route('merchandisers.dashboard');
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Show Merchandiser Registration
     */
    public function showRegister()
    {
        return view('merchandisers.register');
    }

    /**
     * Handle Registration Action
     */
    public function register(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'contact_email' => Str::lower(trim((string) $request->input('contact_email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('users', 'contact_email'),
            ],
            'contact_email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'contact_email'),
                Rule::unique('users', 'email'),
            ],
            'phone' => ['required', 'string', 'max:32'],
            'date_of_birth' => ['required', 'date'],
            'password' => PasswordPolicy::confirmedRules(),
        ], [
            'email.unique' => 'This email is already attached to an existing account. Please log in or use forgot password instead.',
            'contact_email.unique' => 'This contact email is already attached to an existing account. Please log in or use forgot password instead.',
        ]);

        // Age constraint check
        $dob = Carbon::parse($validated['date_of_birth']);
        $age = $dob->age;
        if ($age < 18 || $age > 65) {
            return back()->withErrors(['date_of_birth' => 'External field merchandisers must be between 18 and 65 years old.'])->withInput();
        }

        $merchandiser = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'contact_email'  => $validated['contact_email'],
            'phone'          => $validated['phone'],
            'date_of_birth'  => $validated['date_of_birth'],
            'password'       => Hash::make($request->input('password')),
            'access_role'    => 'merchandiser',
            'position_title' => 'Merchandiser',
            'job_level'      => 'promoter',
            'status'         => 'pending',
            'leave_balance'  => 30,
            // No KD or region until admin assigns
            'kd_id'          => null,
            'region_id'      => null,
        ]);

        NotificationService::sendToMany(
            NotificationService::activeMerchandiserPortalAdminIds(),
            'New merchandiser registration needs approval',
            "{$merchandiser->name} has created a merchandiser account and needs Brands Team approval.",
            route('merchandisers.admin.dashboard')
        );

        return redirect()->route('merchandisers.login')->with('status', 'Registration complete. Your account is pending activation by a Brands Team Admin.');
    }

    /**
     * Dashboard View (Loads Outlets, Clockins, Leaves, Claims, Advances, Surveys, Appraisals, and Payroll details)
     */
    public function dashboard(Request $request, MerchandiserRoutePlanner $routePlanner)
    {
        $user = $request->user();

        // Brands/admin users have their own separate merchandiser admin hub.
        if ($user->isMerchandiserPortalAdmin() && ! $user->isMerchandiserAccount()) {
            return redirect()->route('merchandisers.admin.dashboard');
        }

        if (! $user->isMerchandiserAccount()) {
            return redirect()->route('dashboard');
        }

        if (!$user->kd_id || !$user->region_id) {
            return view('merchandisers.dashboard', [
                'outlets' => collect(),
                'attendances' => collect(),
                'clockWindows' => MerchandiserClockWindows::windows('Africa/Accra'),
                'error' => 'Your account is active but has not been paired with a Key Distributor (KD) or Region yet. Please ask an admin to pair your profile.'
            ]);
        }

        $timezone = $user->merchandiserRegion->timezone ?? 'Africa/Accra';
        $todayStart = Carbon::today($timezone)->startOfDay();
        $todayEnd = Carbon::today($timezone)->endOfDay();
        $currentIsoDay = (string) Carbon::today($timezone)->isoWeekday();

        $allOutlets = $routePlanner->routeableOutletsFor($user);

        // Day of week selector support: 'today', '1'..'7', or 'all'
        $requestedDay = (string) $request->query('day', 'today');
        $validDays = ['today', 'all', '1', '2', '3', '4', '5', '6', '7'];
        $selectedDay = in_array($requestedDay, $validDays, true) ? $requestedDay : 'today';

        $weekStart = Carbon::now($timezone)->startOfWeek();

        // Calculate schedule date for requested day filter
        if ($selectedDay === 'today') {
            $scheduleDate = Carbon::today($timezone);
        } elseif ($selectedDay === 'all') {
            $scheduleDate = Carbon::today($timezone);
        } else {
            $dayOffset = (int) $selectedDay - 1;
            $scheduleDate = $weekStart->copy()->addDays($dayOffset);
        }

        $todaysAssignments = $routePlanner->assignmentsForDate($user, $scheduleDate->copy());
        $scheduledOutlets = $todaysAssignments->pluck('outlet')->filter()->values();

        if ($selectedDay === 'all') {
            $outlets = $allOutlets;
        } else {
            $outlets = $scheduledOutlets;
        }

        // Calculate daily outlet counts for the weekly schedule tabs
        $dayOutletCounts = [];
        for ($d = 1; $d <= 7; $d++) {
            $dDate = $weekStart->copy()->addDays($d - 1);
            $dAssignments = $routePlanner->assignmentsForDate($user, $dDate);
            $dayOutletCounts[(string) $d] = $dAssignments->count();
        }
        $dayOutletCounts['all'] = $allOutlets->count();

        $dayLabels = [
            'today' => 'Today (' . Carbon::today($timezone)->format('D') . ')',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            '7' => 'Sunday',
            'all' => 'All Outlets',
        ];

        $attendances = MerchandiserAttendance::where('user_id', $user->id)
            ->whereBetween('clock_in_time', [$todayStart, $todayEnd])
            ->get()
            ->keyBy('clock_in_type');
        $clockWindows = MerchandiserClockWindows::windows($timezone);
        $visitedOutletIdsToday = MerchandiserVisit::where('user_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->pluck('outlet_id')
            ->unique();
        $pendingOutletsToday = $outlets
            ->reject(fn (Outlet $outlet) => $visitedOutletIdsToday->contains($outlet->id))
            ->values();
        $completedAssignmentsToday = $todaysAssignments
            ->filter(fn (MerchandiserOutletAssignment $assignment) => $assignment->status === 'completed'
                || $visitedOutletIdsToday->contains($assignment->outlet_id));
        $pcmClockinToday = MerchandiserPcmClockin::where('user_id', $user->id)
            ->whereBetween('clocked_in_at', [$todayStart, $todayEnd])
            ->latest('clocked_in_at')
            ->first();
        $monthStart = Carbon::now($timezone)->startOfMonth();
        $monthEnd = Carbon::now($timezone)->endOfMonth();
        $monthAttendances = MerchandiserAttendance::where('user_id', $user->id)
            ->whereBetween('clock_in_time', [$monthStart, $monthEnd]);
        $monthClockIns = (clone $monthAttendances)->count();
        $onTimeClockIns = (clone $monthAttendances)->where('status', 'on-time')->count();
        $merchMetrics = [
            'total_outlets' => $outlets->count(),
            'registered_outlets' => $allOutlets->count(),
            'assigned_outlets_today' => $todaysAssignments->count(),
            'completed_assignments_today' => $completedAssignmentsToday->count(),
            'outlets_visited_today' => $visitedOutletIdsToday->count(),
            'pending_outlets_today' => max($outlets->count() - $visitedOutletIdsToday->count(), 0),
            'clockins_today' => $attendances->count() + ($pcmClockinToday ? 1 : 0),
            'clockins_month' => $monthClockIns,
            'on_time_rate' => $monthClockIns > 0 ? round(($onTimeClockIns / $monthClockIns) * 100) : 0,
            'outlets_covered_month' => MerchandiserVisit::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->distinct('outlet_id')
                ->count('outlet_id'),
        ];
        $googleForms = $this->googleFormsForUser($user, null, $todayStart->copy());
        $googleFormCompletionIds = MerchandiserGoogleFormSubmission::where('user_id', $user->id)
            ->pluck('form_assignment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $nativeFormCompletionIds = MerchandiserNativeFormSubmission::where('user_id', $user->id)
            ->pluck('form_assignment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // HRM & Financial data
        $leaves = LeaveApplication::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $claims = PettyCashClaim::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $loans = SalaryAdvance::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $appraisals = Appraisal::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $inventory = PosmLedger::where('created_by', $user->id)->orderByDesc('created_at')->get();
        $surveys = Survey::where('status', 'active')->orderByDesc('created_at')->get();
        $announcements = Announcement::visibleTo($user)->with('user')->latest()->take(10)->get();
        $notifications = Notification::where('user_id', $user->id)->latest()->take(15)->get();

        // All active system users (for leave covering staff selector)
        $staffMembers = User::where('status', 'active')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        // Calculate dynamic payroll deductions for current month
        $payroll = self::calculatePayrollDetails($user, now()->year, now()->month);

        return view('merchandisers.dashboard', compact(
            'outlets', 'attendances', 'leaves', 'claims', 'loans',
            'appraisals', 'inventory', 'surveys', 'staffMembers', 'payroll',
            'announcements', 'notifications', 'clockWindows', 'merchMetrics',
            'pendingOutletsToday', 'pcmClockinToday', 'todaysAssignments',
            'googleForms', 'googleFormCompletionIds', 'nativeFormCompletionIds',
            'selectedDay', 'dayLabels', 'dayOutletCounts', 'currentIsoDay'
        ));
    }

    /**
     * Allow a field merchandiser to register outlets under their assigned KD.
     */
    public function storeOutlet(Request $request)
    {
        $user = $request->user();

        if (! $user->isMerchandiserAccount()) {
            abort(403, 'Only field merchandisers can add KD outlets from this portal.');
        }

        if (!$user->kd_id || !$user->region_id) {
            return back()->withErrors(['outlet' => 'Your account must be assigned to a KD and region before adding outlets.']);
        }

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['nullable', 'string', 'max:32', 'unique:outlets,code'],
                'channel_type' => ['required', 'in:SSM,GT'],
                'address' => ['nullable', 'string', 'max:500'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ],
            [
                'latitude.required' => 'Allow GPS access while adding the outlet so clock-in can be geofenced.',
                'longitude.required' => 'Allow GPS access while adding the outlet so clock-in can be geofenced.',
            ]
        );

        $validated['kd_id'] = $user->kd_id;
        $validated['code'] = $validated['code'] ?: $this->generateOutletCode($user->kd_id);

        DB::transaction(function () use ($validated, $user) {
            $outlet = Outlet::create([
                ...$validated,
                'registered_by' => $user->id,
                'coordinates_locked_at' => now(),
                'coordinates_captured_by' => $user->id,
                'coordinates_source' => 'staff_gps',
            ]);

            $outlet->assignedMerchandisers()->syncWithoutDetaching([
                $user->id => [
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                ],
            ]);
        });

        return redirect()->route('merchandisers.dashboard')->with('status', 'Outlet added successfully. Clock-in is now available for that outlet during the approved windows.');
    }

    public function updateOutletCoordinates(Request $request, Outlet $outlet)
    {
        $user = $request->user();

        if (! $user->isMerchandiserAccount()) {
            abort(403, 'Only field merchandisers can capture outlet coordinates from this portal.');
        }

        if (! $this->canServiceOutlet($user, $outlet)) {
            abort(403, 'This outlet is not assigned to you.');
        }

        if ($outlet->coordinates_locked_at) {
            return back()->withErrors(['outlet_id' => 'This outlet GPS has already been locked. Please ask an admin to correct it if it has moved.']);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Allow GPS access while standing at the outlet so the system can capture the correct coordinates.',
            'longitude.required' => 'Allow GPS access while standing at the outlet so the system can capture the correct coordinates.',
        ]);

        $outlet->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'coordinates_locked_at' => now(),
            'coordinates_captured_by' => $user->id,
            'coordinates_source' => 'staff_gps',
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', "GPS coordinates captured and locked for {$outlet->name}.");
    }

    private function generateOutletCode(int $kdId): string
    {
        do {
            $code = 'KD-' . $kdId . '-OUT-' . Str::upper(Str::random(6));
        } while (Outlet::where('code', $code)->exists());

        return $code;
    }

    private function canServiceOutlet(User $user, Outlet $outlet): bool
    {
        if ((int) $outlet->kd_id !== (int) $user->kd_id) {
            return false;
        }

        if ($outlet->assignedMerchandisers()->where('users.id', $user->id)->exists()) {
            return true;
        }

        if ((int) $outlet->registered_by === (int) $user->id) {
            return ! $outlet->assignedMerchandisers()->where('users.id', '!=', $user->id)->exists();
        }

        return false;
    }

    /**
     * Handle Clock-In
     */
    public function clockIn(Request $request, MerchandiserRoutePlanner $routePlanner)
    {
        $user = $request->user();
        $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'clock_in_type' => ['required', 'in:morning,midday,cob'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'client_recorded_at' => ['nullable', 'date'],
            'sync_token' => ['nullable', 'string', 'max:80'],
            'sync_source' => ['nullable', 'string', 'in:live,queued,offline_retry'],
        ]);

        $outlet = Outlet::findOrFail($request->input('outlet_id'));
        $clockInType = $request->input('clock_in_type');
        $userLat = $request->input('latitude');
        $userLng = $request->input('longitude');
        $syncToken = $request->input('sync_token');
        if ($syncToken) {
            $existingSync = MerchandiserAttendance::where('sync_token', $syncToken)->first();
            if ($existingSync) {
                return redirect()->route('merchandisers.dashboard')->with('status', 'Clock-in was already synced for ' . $existingSync->outlet->name . '.');
            }
        }

        if (! $this->canServiceOutlet($user, $outlet)) {
            abort(403, 'AccessDenied: This outlet is not assigned to you.');
        }

        // 1. Timezone Operational Window check
        $timezone = $outlet->keyDistributor->region->timezone ?? 'Africa/Accra';
        $localNow = Carbon::now($timezone);
        $clientRecordedAt = $this->clientRecordedAt($request, $timezone);
        $effectiveLocalTime = ($clientRecordedAt ?: $localNow)->copy()->timezone($timezone);
        $todayAssignments = $routePlanner->assignmentsForDate($user, $effectiveLocalTime->copy());

        if ($todayAssignments->isNotEmpty() && ! $todayAssignments->pluck('outlet_id')->contains((int) $outlet->id)) {
            abort(403, 'AccessDenied: This outlet is not on your assigned route for today.');
        }

        $windows = MerchandiserClockWindows::windows($timezone);

        $targetWindow = $windows[$clockInType];
        $graceMinutes = (int) SiteContent::getValue('merchandiser_clock_grace_minutes', 45);
        $windowEndWithGrace = $targetWindow['end_at']->copy()->addMinutes($graceMinutes);

        if ($effectiveLocalTime->lt($targetWindow['start_at']) || $effectiveLocalTime->gt($windowEndWithGrace)) {
            abort(403, 'AccessDenied: Window Closed. Clock-in is only allowed between ' . 
                $targetWindow['start_at']->format('g:i A') . ' and ' . $targetWindow['end_at']->format('g:i A') . '.');
        }

        $alreadyClocked = MerchandiserAttendance::where('user_id', $user->id)
            ->where('clock_in_type', $clockInType)
            ->whereBetween('clock_in_time', [
                $effectiveLocalTime->copy()->startOfDay(),
                $effectiveLocalTime->copy()->endOfDay(),
            ])
            ->first();

        if ($alreadyClocked) {
            return back()->withErrors(['clock_in_type' => 'You have already completed this clock-in checkpoint today.'])->withInput();
        }

        // 2. Geofence Distance check
        if (is_null($outlet->latitude) || is_null($outlet->longitude)) {
            return back()->withErrors(['outlet_id' => 'The target outlet coordinates are not registered. Please contact an admin.']);
        }

        $distance = $this->haversineDistance(
            $userLat, $userLng,
            $outlet->latitude, $outlet->longitude
        );

        $allowedRadius = (float) SiteContent::getValue('merchandiser_radius', 30);

        if ($distance > $allowedRadius) {
            return back()->withErrors([
                'outlet_id' => "Geofencing Error: You are too far from the outlet. You must be within {$allowedRadius} meters. Your calculated distance is " . round($distance, 1) . " meters."
            ])->withInput();
        }

        // 3. Save attendance record
        MerchandiserAttendance::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => $clockInType,
            'clock_in_time' => $clientRecordedAt ?: now(),
            'client_recorded_at' => $clientRecordedAt,
            'sync_token' => $syncToken,
            'sync_source' => $request->input('sync_source', $clientRecordedAt ? 'offline_retry' : 'live'),
            'synced_at' => now(),
            'latitude' => $userLat,
            'longitude' => $userLng,
            'distance_from_outlet' => $distance,
            'status' => $effectiveLocalTime->gt($targetWindow['end_at']) ? 'late-grace' : 'on-time'
        ]);

        // Log location for real-time tracking
        MerchandiserLocation::create([
            'user_id' => $user->id,
            'latitude' => $userLat,
            'longitude' => $userLng,
            'recorded_at' => now()
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', ucfirst($clockInType) . ' Clock-in recorded successfully for ' . $outlet->name . '!');
    }

    /**
     * Handle PCM/KD day clock-in before a merchandiser proceeds to outlets.
     */
    public function clockInPcm(Request $request)
    {
        $user = $request->user();

        if (! $user->isMerchandiserAccount()) {
            abort(403, 'Only field merchandisers can clock in at a PCM/KD location.');
        }

        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'client_recorded_at' => ['nullable', 'date'],
            'sync_token' => ['nullable', 'string', 'max:80'],
            'sync_source' => ['nullable', 'string', 'in:live,queued,offline_retry'],
        ]);

        if (!$user->kd_id) {
            return back()->withErrors(['pcm' => 'Your account must be assigned to a Key Distributor before PCM clock-in is available.']);
        }

        $kd = KeyDistributor::with('region')->findOrFail($user->kd_id);

        if (is_null($kd->latitude) || is_null($kd->longitude)) {
            return back()->withErrors(['pcm' => 'This KD does not have GPS coordinates yet. Ask a Brands Team admin to edit the KD location.']);
        }

        $timezone = $kd->region->timezone ?? 'Africa/Accra';
        $clientRecordedAt = $this->clientRecordedAt($request, $timezone);
        $effectiveLocalTime = ($clientRecordedAt ?: Carbon::now($timezone))->copy()->timezone($timezone);
        $todayStart = Carbon::today($timezone)->startOfDay();
        $todayEnd = Carbon::today($timezone)->endOfDay();
        $syncToken = $request->input('sync_token');

        if ($syncToken) {
            $existingSync = MerchandiserPcmClockin::where('sync_token', $syncToken)->first();
            if ($existingSync) {
                return redirect()->route('merchandisers.dashboard')->with('status', 'PCM/KD clock-in was already synced.');
            }
        }

        $alreadyClocked = MerchandiserPcmClockin::where('user_id', $user->id)
            ->where('kd_id', $kd->id)
            ->whereBetween('clocked_in_at', [
                $effectiveLocalTime->copy()->startOfDay(),
                $effectiveLocalTime->copy()->endOfDay(),
            ])
            ->exists();

        if ($alreadyClocked) {
            return back()->withErrors(['pcm' => 'You have already clocked in at your PCM/KD location today.']);
        }

        $distance = $this->haversineDistance(
            $request->input('latitude'),
            $request->input('longitude'),
            $kd->latitude,
            $kd->longitude
        );

        $allowedRadius = (float) SiteContent::getValue('merchandiser_pcm_radius', 150);

        if ($distance > $allowedRadius) {
            return back()->withErrors([
                'pcm' => "PCM geofencing error: you must be within {$allowedRadius} meters of {$kd->name}. Your calculated distance is " . round($distance, 1) . ' meters.',
            ])->withInput();
        }

        MerchandiserPcmClockin::create([
            'user_id' => $user->id,
            'kd_id' => $kd->id,
            'clocked_in_at' => $clientRecordedAt ?: now(),
            'client_recorded_at' => $clientRecordedAt,
            'sync_token' => $syncToken,
            'sync_source' => $request->input('sync_source', $clientRecordedAt ? 'offline_retry' : 'live'),
            'synced_at' => now(),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'distance_from_kd' => $distance,
            'status' => 'verified',
        ]);

        MerchandiserLocation::create([
            'user_id' => $user->id,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'recorded_at' => now(),
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', 'PCM/KD clock-in recorded successfully for ' . $kd->name . '.');
    }

    /**
     * Store Visit View
     */
    public function visit(Outlet $outlet, MerchandiserRoutePlanner $routePlanner)
    {
        $user = Auth::user();
        if ($user->kd_id !== $outlet->kd_id) {
            abort(403, 'AccessDenied: This outlet is not under your assigned Key Distributor.');
        }

        $timezone = $user->merchandiserRegion->timezone ?? 'Africa/Accra';
        $today = Carbon::today($timezone);
        $todayAssignments = $routePlanner->assignmentsForDate($user, $today->copy());

        if ($todayAssignments->isNotEmpty() && ! $todayAssignments->pluck('outlet_id')->contains((int) $outlet->id)) {
            abort(403, 'AccessDenied: This outlet is not on your assigned route for today.');
        }

        $skus = Sku::with('brand')->orderBy('name')->get();
        $googleForms = $this->googleFormsForUser($user, $outlet, $today->copy());
        $googleFormCompletionIds = MerchandiserGoogleFormSubmission::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->pluck('form_assignment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $nativeFormCompletionIds = MerchandiserNativeFormSubmission::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->pluck('form_assignment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $planograms = MerchandiserPlanogram::where('status', 'active')
            ->where(function ($query) use ($outlet) {
                $query->whereNull('channel_type')
                    ->orWhere('channel_type', $outlet->channel_type);
            })
            ->orderBy('title')
            ->get();
        $perfectStoreGuide = $this->perfectStoreGuideForChannel($outlet->channel_type);

        return view('merchandisers.visit', compact(
            'outlet',
            'skus',
            'googleForms',
            'googleFormCompletionIds',
            'nativeFormCompletionIds',
            'planograms',
            'perfectStoreGuide'
        ));
    }

    /**
     * Analyze a shelf photo and return AI-prefill SKU metrics.
     */
    public function analyzeVisitShelf(Request $request, Outlet $outlet)
    {
        $user = $request->user();
        if ($user->kd_id !== $outlet->kd_id) {
            abort(403);
        }

        $request->validate([
            'ai_shelf_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
        ]);

        if (! filled(config('services.openai.api_key')) && ! filled(config('services.gemini.api_key'))) {
            return response()->json([
                'job_status' => 'skipped',
                'status' => 'manual_fallback',
                'message' => 'AI detection is not configured. Continue with manual SKU entry.',
                'provider' => 'manual',
                'model' => null,
                'detections' => [],
                'review_required' => true,
            ]);
        }

        $token = (string) Str::uuid();
        $photoPath = $request->file('ai_shelf_photo')->store('merchandiser-ai-shelf-photos/pending', 'public');
        Cache::put(AnalyzeMerchandiserShelfPhoto::cacheKey($token), [
            'job_status' => 'queued',
            'status' => 'queued',
            'message' => 'AI shelf detection has started. Results will appear shortly.',
            'detections' => [],
        ], now()->addMinutes(30));

        AnalyzeMerchandiserShelfPhoto::dispatch($token, $photoPath, (int) $user->id);

        return response()->json([
            'job_status' => 'queued',
            'status' => 'queued',
            'token' => $token,
            'poll_url' => route('merchandisers.visit.ai-detect.status', ['outlet' => $outlet, 'token' => $token]),
            'message' => 'AI shelf detection is running. You can continue filling the form while it works.',
            'detections' => [],
        ], 202);
    }

    public function aiDetectionStatus(Request $request, Outlet $outlet, string $token)
    {
        $user = $request->user();
        if ($user->kd_id !== $outlet->kd_id) {
            abort(403);
        }

        $result = Cache::get(AnalyzeMerchandiserShelfPhoto::cacheKey($token));

        if (! $result) {
            return response()->json([
                'job_status' => 'expired',
                'status' => 'manual_fallback',
                'message' => 'AI detection result expired. Continue with manual SKU entry.',
                'detections' => [],
                'review_required' => true,
            ], 404);
        }

        return response()->json($result);
    }

    /**
     * Handle Store Visit Submission & Order Placement
     */
    public function storeVisit(Request $request, Outlet $outlet, MerchandiserRoutePlanner $routePlanner)
    {
        $user = $request->user();
        if ($user->kd_id !== $outlet->kd_id) {
            abort(403);
        }

        $request->validate([
            'branded_shelf_available' => ['required', 'boolean'],
            'hangers_available' => ['required', 'boolean'],
            'planogram_id' => ['nullable', 'exists:merchandiser_planograms,id'],
            'planogram_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'planogram_notes' => ['nullable', 'string', 'max:2000'],
            'planogram_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'sku_entry_mode' => ['required', 'string', 'in:manual,ai'],
            'ai_shelf_photo' => ['nullable', 'required_if:sku_entry_mode,ai', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'ai_predictions_json' => ['nullable', 'json'],
            'ai_detection_notes' => ['nullable', 'string', 'max:1000'],
            'skus' => ['required', 'array'],
            'skus.*.osa_quantity' => ['required', 'integer', 'min:0'],
            'skus.*.npd_present' => ['required', 'boolean'],
            'skus.*.facing' => ['required', 'integer', 'min:0'],
            'skus.*.share_of_shelf' => ['required', 'numeric', 'min:0', 'max:100'],
            'skus.*.planogram_compliant' => ['required', 'boolean'],
            'order_items' => ['nullable', 'array'],
            'order_items.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $skuEntryMode = $request->input('sku_entry_mode', 'manual');
        $aiPredictions = $this->decodedAiPredictions($request->input('ai_predictions_json'));
        $predictionsBySku = collect($aiPredictions['detections'] ?? [])->keyBy(fn ($detection) => (int) ($detection['sku_id'] ?? 0));
        $aiDetectionStatus = $skuEntryMode === 'ai'
            ? ($aiPredictions['status'] ?? 'pilot_photo_captured')
            : null;
        $aiDetectionCompleted = $skuEntryMode === 'ai' && in_array($aiDetectionStatus, ['completed', 'no_detection'], true);
        $aiShelfPhotoPath = null;
        if ($request->hasFile('ai_shelf_photo')) {
            $aiShelfPhotoPath = $request->file('ai_shelf_photo')->store('merchandiser-ai-shelf-photos', 'public');
        }
        $planogramPhotoPath = null;
        if ($request->hasFile('planogram_photo')) {
            $planogramPhotoPath = $request->file('planogram_photo')->store('merchandiser-planogram-photos', 'public');
        }
        $timezone = $user->merchandiserRegion->timezone ?? 'Africa/Accra';
        $today = Carbon::today($timezone);
        $routeAssignment = MerchandiserOutletAssignment::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->whereDate('assigned_date', $today->toDateString())
            ->first();

        $visit = MerchandiserVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'route_assignment_id' => $routeAssignment?->id,
            'branded_shelf_available' => $request->input('branded_shelf_available'),
            'hangers_available' => $request->input('hangers_available'),
            'planogram_id' => $request->input('planogram_id'),
            'planogram_score' => $request->input('planogram_score'),
            'planogram_notes' => $request->input('planogram_notes'),
            'planogram_photo_path' => $planogramPhotoPath,
            'sku_entry_mode' => $skuEntryMode,
            'ai_detection_status' => $aiDetectionStatus,
            'ai_shelf_photo_path' => $aiShelfPhotoPath,
            'ai_detection_payload' => $skuEntryMode === 'ai' ? [
                'source' => $aiDetectionCompleted ? (($aiPredictions['provider'] ?? 'ai') . '_shelf_analysis') : 'pilot_photo_upload',
                'provider' => $aiPredictions['provider'] ?? null,
                'manual_fallback_available' => true,
                'auto_detection_completed' => $aiDetectionCompleted,
                'model' => $aiPredictions['model'] ?? config('services.openai.vision_model'),
                'average_confidence' => $aiPredictions['average_confidence'] ?? null,
                'detections' => $aiPredictions['detections'] ?? [],
                'message' => $aiPredictions['message'] ?? null,
                'attempts' => $aiPredictions['attempts'] ?? [],
            ] : null,
            'ai_detection_notes' => $request->input('ai_detection_notes'),
            'ai_detection_review_required' => $skuEntryMode === 'ai' ? (bool) ($aiPredictions['review_required'] ?? true) : false,
            'ai_detection_completed_at' => $aiDetectionCompleted ? now() : null,
        ]);

        foreach ($request->input('skus') as $skuId => $metrics) {
            $aiPrediction = $predictionsBySku->get((int) $skuId, []);
            MerchandiserVisitSku::create([
                'visit_id' => $visit->id,
                'sku_id' => $skuId,
                'osa_quantity' => $metrics['osa_quantity'],
                'npd_present' => $metrics['npd_present'],
                'facing' => $metrics['facing'],
                'share_of_shelf' => $metrics['share_of_shelf'],
                'planogram_compliant' => $metrics['planogram_compliant'],
                'ai_predicted_quantity' => $aiPrediction['quantity'] ?? null,
                'ai_predicted_facing' => $aiPrediction['facing'] ?? null,
                'ai_predicted_share_of_shelf' => $aiPrediction['share_of_shelf'] ?? null,
                'ai_predicted_planogram_compliant' => $aiPrediction['planogram_compliant'] ?? null,
                'ai_confidence' => $aiPrediction['confidence'] ?? null,
                'ai_detection_boxes' => $aiPrediction['boxes'] ?? null,
                'ai_raw_detection' => $aiPrediction ?: null,
            ]);
        }

        $orderItems = array_filter($request->input('order_items', []));
        if (count($orderItems) > 0) {
            $order = MerchandiserOrder::create([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kd_id' => $outlet->kd_id,
                'status' => 'pending'
            ]);

            foreach ($orderItems as $skuId => $quantity) {
                MerchandiserOrderItem::create([
                    'order_id' => $order->id,
                    'sku_id' => $skuId,
                    'quantity' => $quantity,
                ]);
            }
        }

        $routePlanner->markCompleted($user, $outlet->id, $today->copy(), $visit->id);

        return redirect()->route('merchandisers.dashboard')->with('status', 'Visit report and orders for ' . $outlet->name . ' submitted successfully!');
    }

    public function completeGoogleForm(Request $request, MerchandiserGoogleFormAssignment $form, MerchandiserRoutePlanner $routePlanner)
    {
        if (! $form->google_enabled) {
            abort(404);
        }

        $user = $request->user();
        $validated = $request->validate([
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'response_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $outlet = null;
        if (! empty($validated['outlet_id'])) {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            if ((int) $outlet->kd_id !== (int) $user->kd_id) {
                abort(403);
            }
        }

        $matchingForms = $this->googleFormsForUser($user, $outlet, Carbon::today($user->merchandiserRegion->timezone ?? 'Africa/Accra'));
        if (! $matchingForms->contains('id', $form->id)) {
            abort(403, 'This form is not assigned to you for this outlet or period.');
        }

        MerchandiserGoogleFormSubmission::updateOrCreate(
            [
                'form_assignment_id' => $form->id,
                'user_id' => $user->id,
                'outlet_id' => $outlet?->id,
            ],
            [
                'submitted_at' => now(),
                'response_reference' => $validated['response_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($outlet) {
            $routePlanner->markCompleted(
                $user,
                $outlet->id,
                Carbon::today($user->merchandiserRegion->timezone ?? 'Africa/Accra')
            );
        }

        return back()->with('status', 'Form marked as completed.');
    }

    public function showNativeForm(
        Request $request,
        MerchandiserGoogleFormAssignment $form,
        PerfectStoreFormTemplate $template,
        MerchandiserRoutePlanner $routePlanner
    ) {
        $user = $request->user();
        $outlet = $this->nativeFormOutlet($request, $user);
        $this->authorizeNativeForm($user, $form, $outlet);

        $timezone = $user->merchandiserRegion->timezone ?? 'Africa/Accra';
        $todaysAssignments = $routePlanner->assignmentsForDate($user, Carbon::today($timezone));
        $todaysOutlets = $todaysAssignments->pluck('outlet')->filter()->values();

        if ($todaysOutlets->isNotEmpty()) {
            $todaysOutlets = Outlet::with('keyDistributor')
                ->whereIn('id', $todaysOutlets->pluck('id')->all())
                ->orderBy('name')
                ->get();
        } else {
            $todaysOutlets = Outlet::with('keyDistributor')
                ->where('kd_id', $user->kd_id)
                ->orderBy('name')
                ->get();
        }

        $existingSubmission = MerchandiserNativeFormSubmission::where('form_assignment_id', $form->id)
            ->where('user_id', $user->id)
            ->where('outlet_id', $outlet?->id)
            ->first();
        $systemDefaults = $template->defaultsFor($user, $outlet);
        $defaults = array_replace($existingSubmission?->answers ?? [], $systemDefaults);
        $outletDefaultMap = $todaysOutlets
            ->mapWithKeys(fn (Outlet $optionOutlet) => [
                $optionOutlet->id => $template->defaultsFor($user, $optionOutlet),
            ])
            ->all();

        return view('merchandisers.native-perfect-store', [
            'form' => $form,
            'outlet' => $outlet,
            'todaysOutlets' => $todaysOutlets,
            'schema' => $template->schema(),
            'sections' => $template->sections(),
            'defaults' => $defaults,
            'systemDefaultKeys' => array_keys($systemDefaults),
            'outletDefaultMap' => $outletDefaultMap,
            'questionMeta' => $template->questionMeta(),
            'existingSubmission' => $existingSubmission,
            'template' => $template,
        ]);
    }

    public function submitNativeForm(
        Request $request,
        MerchandiserGoogleFormAssignment $form,
        PerfectStoreFormTemplate $template,
        MerchandiserRoutePlanner $routePlanner
    ) {
        $user = $request->user();
        $outlet = $this->nativeFormOutlet($request, $user);
        $this->authorizeNativeForm($user, $form, $outlet);
        $rawAnswers = $request->input('answers', []);
        $rawAnswers = is_array($rawAnswers) ? $rawAnswers : [];
        $request->merge([
            'answers' => $template->applyTrustedDefaults($user, $outlet, $rawAnswers),
        ]);
        $validated = $request->validate($template->validationRules());

        $answers = $template->sanitizeAnswers(
            $template->applyTrustedDefaults($user, $outlet, $validated['answers'] ?? [])
        );

        MerchandiserNativeFormSubmission::updateOrCreate(
            [
                'form_assignment_id' => $form->id,
                'user_id' => $user->id,
                'outlet_id' => $outlet?->id,
            ],
            [
                'template_key' => $form->native_template_key,
                'answers' => $answers,
                'normalized_metrics' => $template->normalizedMetrics($answers),
                'source_google_form_url' => $form->google_form_url,
                'submitted_at' => now(),
            ]
        );

        if ($outlet) {
            $routePlanner->markCompleted(
                $user,
                $outlet->id,
                Carbon::today($user->merchandiserRegion->timezone ?? 'Africa/Accra')
            );
        }

        $message = 'Native Perfect Store audit saved successfully.';

        if ($outlet) {
            return redirect()->route('merchandisers.visit', $outlet)->with('status', $message);
        }

        return redirect()->route('merchandisers.dashboard')->with('status', $message);
    }

    private function decodedAiPredictions(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function googleFormsForUser(User $user, ?Outlet $outlet, Carbon $date)
    {
        return MerchandiserGoogleFormAssignment::with(['outlet', 'keyDistributor', 'brand', 'campaign', 'submissions' => function ($query) use ($user, $outlet) {
                $query->where('user_id', $user->id);
                if ($outlet) {
                    $query->where('outlet_id', $outlet->id);
                }
            }, 'nativeSubmissions' => function ($query) use ($user, $outlet) {
                $query->where('user_id', $user->id);
                if ($outlet) {
                    $query->where('outlet_id', $outlet->id);
                }
            }])
            ->activeForDate($date)
            ->where(function ($query) use ($user) {
                $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', $user->id);
            })
            ->where(function ($query) use ($user) {
                $query->whereNull('kd_id')->orWhere('kd_id', $user->kd_id);
            })
            ->where(function ($query) use ($outlet) {
                if (! $outlet) {
                    $query->whereNull('outlet_id');
                    return;
                }

                $query->whereNull('outlet_id')->orWhere('outlet_id', $outlet->id);
            })
            ->where(function ($query) use ($outlet) {
                if (! $outlet) {
                    return;
                }

                $query->whereNull('channel_type')->orWhere('channel_type', $outlet->channel_type);
            })
            ->orderBy('title')
            ->get();
    }

    private function perfectStoreGuideForChannel(?string $channel): array
    {
        $channelKey = strtoupper((string) $channel);

        $guides = [
            'LMT' => [
                'Planograms: Skin Care, Comfort, OMO, Sunlight DWL, and Bars.',
                'Visibility: wobbler, shelf talker, category divider, gondola end, parasite unit, branded cart, FSU, and experience centre.',
            ],
            'SSM' => [
                'Planograms: Skin Care, Comfort, OMO, Sunlight DWL, and Bars.',
                'Visibility: wobbler, shelf talker, category divider, and FSU.',
            ],
            'COSMETICS' => [
                'Layout: body oils, petroleum jelly, and lotions across available shelves.',
                'Visibility: shelf branding, FSU, countertop unit, panel, dangler, and poster.',
            ],
            'PHARMACY' => [
                'Must-have SKUs: Pepsodent oral care, Lifebuoy and Geisha skin cleansing, Vaseline petroleum jelly and lotion.',
                'Visibility: door cling, dangler, FSU, and MoMo stand.',
            ],
        ];

        return $guides[$channelKey] ?? [
            'Check product availability, facings, shelf share, pricing, POSM visibility, and planogram compliance.',
            'Record missing items, blocked visibility, low stock, and any corrections needed before leaving the outlet.',
        ];
    }

    private function nativeFormOutlet(Request $request, User $user): ?Outlet
    {
        if (! $request->filled('outlet_id')) {
            return null;
        }

        $outlet = Outlet::findOrFail($request->integer('outlet_id'));
        if ((int) $outlet->kd_id !== (int) $user->kd_id) {
            abort(403, 'This outlet is not assigned to your Key Distributor.');
        }

        return $outlet;
    }

    private function authorizeNativeForm(User $user, MerchandiserGoogleFormAssignment $form, ?Outlet $outlet): void
    {
        if (! $form->native_enabled || $form->native_template_key !== PerfectStoreFormTemplate::KEY) {
            abort(404);
        }

        $date = Carbon::today($user->merchandiserRegion->timezone ?? 'Africa/Accra');
        $matchingForms = $this->googleFormsForUser($user, $outlet, $date);

        if (! $matchingForms->contains('id', $form->id)) {
            abort(403, 'This native form is not assigned to you for this outlet or period.');
        }
    }

    private function clientRecordedAt(Request $request, string $timezone): ?Carbon
    {
        if (! $request->filled('client_recorded_at')) {
            return null;
        }

        try {
            $recordedAt = Carbon::parse($request->input('client_recorded_at'))->timezone($timezone);
        } catch (\Throwable) {
            return null;
        }

        $now = Carbon::now($timezone);
        if ($recordedAt->gt($now->copy()->addMinutes(5)) || $recordedAt->lt($now->copy()->subHours(18))) {
            return null;
        }

        return $recordedAt;
    }

    /**
     * Background Location Tracker API
     */
    public function locationPing(Request $request)
    {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        MerchandiserLocation::create([
            'user_id' => Auth::id(),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'recorded_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    /**
     * Update Profile & Banking Credentials
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'string', 'max:32'],
            'residential_address' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'momo_number' => ['nullable', 'string', 'max:32'],
            'momo_name' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'confirmed', ...PasswordPolicy::rules()],
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'residential_address' => $validated['residential_address'],
            'bank_name' => $validated['bank_name'],
            'bank_branch' => $validated['bank_branch'],
            'bank_account_name' => $validated['bank_account_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'momo_number' => $validated['momo_number'],
            'momo_name' => $validated['momo_name'],
        ]);

        if ($user->isDirty()) {
            $user->save();
        }

        return redirect()->route('merchandisers.dashboard')->with('status', 'Profile and banking credentials updated successfully.');
    }

    /**
     * Submit Leave Application
     */
    public function submitLeave(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['required', 'in:annual,sick,maternity,compassionate,unpaid'],
            'comments' => ['nullable', 'string', 'max:500'],
            'covering_staff_id' => ['required', 'exists:users,id', 'not_in:' . $user->id],
        ]);

        // Default supervisor acts as line manager
        $lineManagerId = $user->supervisor_id ?: User::where('access_role', 'super_admin')->first()->id;

        $leave = LeaveApplication::create(array_merge($validated, [
            'user_id' => $user->id,
            'line_manager_id' => $lineManagerId,
            'status' => 'pending'
        ]));

        NotificationService::sendApprovalNeededToMany(
            array_filter([(int) $lineManagerId]),
            'Merchandiser Leave Approval Needed',
            "{$user->name} submitted a {$validated['leave_type']} leave request that needs approval.",
            route('merchandisers.admin.dashboard'),
            $user->id
        );

        NotificationService::send(
            (int) $validated['covering_staff_id'],
            'Merchandiser Leave Cover Assigned',
            "{$user->name} selected you to cover duties for their leave request from {$leave->start_date->format('M d, Y')} to {$leave->end_date->format('M d, Y')}.",
            route('merchandisers.dashboard')
        );

        return redirect()->route('merchandisers.dashboard')->with('status', 'Leave application submitted successfully. Lateness/absence calculations for approved dates will be bypassed.');
    }

    /**
     * Submit Petty Cash Reimbursement Claim
     */
    public function submitClaim(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'max:8'],
            'description' => ['required', 'string', 'max:500'],
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        $path = $request->file('receipt')->store('receipts', 'local');

        $claim = PettyCashClaim::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'description' => $validated['description'],
            'receipt_path' => $path,
            'status' => 'pending'
        ]);

        NotificationService::sendApprovalNeededToMany(
            NotificationService::activeFinanceApproverIds($user->id),
            'Merchandiser Claim Approval Needed',
            "{$user->name} submitted a {$claim->currency} " . number_format((float) $claim->amount, 2) . " petty cash claim.",
            route('merchandisers.admin.dashboard'),
            $user->id
        );

        return redirect()->route('merchandisers.dashboard')->with('status', 'Petty Cash reimbursement claim submitted to finance.');
    }

    /**
     * Submit Salary Advance Request
     */
    public function submitLoan(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'repayment_style' => ['required', 'in:flat,monthly_deduction'],
            'monthly_deduction_amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Block if advance exceeds double the monthly base salary
        $limit = ($user->salary ?: 0) * 2;
        if ($validated['amount'] > $limit) {
            return back()->withErrors(['amount' => "Advance request cannot exceed double your monthly salary ({$limit})."]);
        }

        $loan = SalaryAdvance::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'repayment_style' => $validated['repayment_style'],
            'monthly_deduction_amount' => $validated['monthly_deduction_amount'],
            'reason' => $validated['reason'],
            'status' => 'pending'
        ]);

        NotificationService::sendApprovalNeededToMany(
            NotificationService::activeFinanceApproverIds($user->id),
            'Merchandiser Salary Advance Approval Needed',
            "{$user->name} requested a salary advance of GHS " . number_format((float) $loan->amount, 2) . ".",
            route('merchandisers.admin.dashboard'),
            $user->id
        );

        return redirect()->route('merchandisers.dashboard')->with('status', 'Salary advance request submitted successfully.');
    }

    /**
     * Submit Self-Appraisal Rating
     */
    public function submitAppraisal(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'feedback' => ['required', 'string', 'max:500'],
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        // Quarter determination
        $month = now()->month;
        $quarter = ceil($month / 3);

        Appraisal::create([
            'user_id' => $user->id,
            'quarter' => 'Q' . $quarter,
            'year' => now()->year,
            'self_assessment' => [
                'scores' => $validated['scores'],
                'feedback' => $validated['feedback']
            ],
            'status' => 'submitted'
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', 'Self-appraisal assessment scores submitted to supervisor.');
    }

    /**
     * Log Checkout of POSM / Inventory Items
     */
    public function submitInventory(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'quantity_out' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('inventory', 'public');
        }

        PosmLedger::create([
            'created_by' => $user->id,
            'item_name' => $validated['item_name'],
            'item_type' => 'Checkout',
            'client_brand' => 'CMIH Field Gear',
            'quantity_in' => 0,
            'quantity_out' => $validated['quantity_out'],
            'location' => $validated['location'],
            'notes' => $validated['notes'],
            'image_path' => $imagePath,
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', 'Field gear checkout logged successfully.');
    }

    /**
     * Store Administrative Survey (Merchandiser Portal Creation)
     */
    public function storeSurvey(Request $request)
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status'      => ['required', 'string', \Illuminate\Validation\Rule::in(['draft', 'published', 'closed'])],
            'is_anonymous'=> ['nullable', 'boolean'],
            'questions'   => ['nullable', 'array'],
            'questions.*.question_text' => ['required', 'string', 'max:255'],
            'questions.*.question_type' => ['required', 'string', \Illuminate\Validation\Rule::in(['short_text', 'paragraph', 'radio', 'checkbox', 'dropdown'])],
            'questions.*.options'       => ['nullable', 'array'],
            'questions.*.is_required'   => ['nullable', 'boolean'],
        ]);

        $slug = \Illuminate\Support\Str::slug($validated['title']) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5));

        while (Survey::where('slug', $slug)->exists()) {
            $slug = \Illuminate\Support\Str::slug($validated['title']) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5));
        }

        $survey = Survey::create([
            'created_by'   => auth()->id(),
            'title'        => $validated['title'],
            'slug'         => $slug,
            'description'  => $validated['description'] ?? null,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'status'       => $validated['status'],
        ]);

        if (!empty($validated['questions'])) {
            foreach ($validated['questions'] as $index => $q) {
                $options = null;
                if (in_array($q['question_type'], ['radio', 'checkbox', 'dropdown']) && !empty($q['options'])) {
                    $options = array_values(array_filter(array_map('trim', $q['options'])));
                }

                $survey->questions()->create([
                    'question_text' => $q['question_text'],
                    'question_type' => $q['question_type'],
                    'options'       => $options,
                    'is_required'   => !empty($q['is_required']),
                    'order'         => $index,
                ]);
            }
        }

        return redirect()->route('merchandisers.dashboard')->with('status', 'Survey created successfully.');
    }

    /**
     * Submit Active Administrative Survey Answers
     */
    public function submitSurveyResponse(Request $request, Survey $survey)
    {
        $user = $request->user();
        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'answers' => $validated['answers'],
            'ip_address' => $request->ip()
        ]);

        return redirect()->route('merchandisers.dashboard')->with('status', 'Thank you! Your response to survey "' . $survey->title . '" has been submitted.');
    }

    /**
     * Mark personal notification as read
     */
    public function markNotificationRead(Notification $notification, Request $request)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    /**
     * Handle Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('merchandisers.login');
    }

    /**
     * Dynamic lateness/absence deductions calculator based on calendar clock-in records
     */
    public static function calculatePayrollDetails(User $user, $year, $month)
    {
        $baseSalary = (float) ($user->salary ?: 0.00);
        if ($baseSalary <= 0) {
            return [
                'base_salary' => 0,
                'expected_slots' => 0,
                'leave_days_count' => 0,
                'missed_slots' => 0,
                'late_slots' => 0,
                'deductions' => 0,
                'net_pay' => 0,
                'work_rate' => 0
            ];
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        if ($endDate->isFuture()) {
            $endDate = Carbon::now();
        }

        $expectedDays = 0;
        $leaveDays = 0;
        $missedSlots = 0;
        $lateSlots = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $expectedDays++;

            // 1. Check if on approved leave
            $hasApprovedLeave = LeaveApplication::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            if ($hasApprovedLeave) {
                $leaveDays++;
                continue; // protected day!
            }

            // 2. Fetch slot check-ins
            $attendances = MerchandiserAttendance::where('user_id', $user->id)
                ->whereDate('clock_in_time', $date)
                ->get()
                ->keyBy('clock_in_type');

            $clockWindows = MerchandiserClockWindows::windows($user->merchandiserRegion->timezone ?? 'Africa/Accra', $date->copy());
            foreach (array_keys($clockWindows) as $slot) {
                if (!$attendances->has($slot)) {
                    $missedSlots++;
                } else {
                    $att = $attendances->get($slot);
                    $localTime = Carbon::parse($att->clock_in_time)->timezone($user->merchandiserRegion->timezone ?? 'Africa/Accra');
                    $isLate = $localTime->gt($clockWindows[$slot]['start_at']->copy()->addMinutes(5));

                    if ($isLate) {
                        $lateSlots++;
                    }
                }
            }
        }

        $expectedSlots = $expectedDays * 3;
        
        // 1% of base pay per missed slot, 0.5% per late slot
        $deductionPerMissed = $baseSalary * 0.01;
        $deductionPerLate = $baseSalary * 0.005;

        $totalDeductions = ($missedSlots * $deductionPerMissed) + ($lateSlots * $deductionPerLate);
        $totalDeductions = min($totalDeductions, $baseSalary);

        $netPay = $baseSalary - $totalDeductions;

        $actualPresent = $expectedSlots - $missedSlots - ($lateSlots * 0.5);
        $workRate = $expectedSlots > 0 ? round(($actualPresent / $expectedSlots) * 100, 1) : 100;

        return [
            'base_salary' => $baseSalary,
            'expected_slots' => $expectedSlots,
            'leave_days_count' => $leaveDays,
            'missed_slots' => $missedSlots,
            'late_slots' => $lateSlots,
            'deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'work_rate' => $workRate
        ];
    }

    /**
     * Haversine Distance Formula
     */
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
}
