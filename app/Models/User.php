<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const MERCHANDISER_ROLE = 'merchandiser';
    public const MERCHANDISER_SUPERVISOR_ROLE = 'merchandiser_supervisor';
    public const BRAND_PROMOTER_ROLE = 'brand_promoter';
    public const MERCHANDISER_FIELD_ROLES = [
        self::MERCHANDISER_ROLE,
        self::MERCHANDISER_SUPERVISOR_ROLE,
    ];

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return  array<string, string>|string
     */
    public function routeNotificationForMail(\Illuminate\Notifications\Notification $notification): array|string
    {
        if ($notification instanceof \App\Notifications\MerchandiserResetPassword && $notification->requestedEmail()) {
            return $notification->requestedEmail();
        }

        // If it's a ResetPassword notification, send to contact_email
        if ($notification instanceof \Illuminate\Auth\Notifications\ResetPassword) {
            return $this->contact_email ?? $this->email;
        }

        // Default behavior (company email)
        return $this->email;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'contact_email',
        'phone',
        'profile_photo_path',
        'password',
        'access_role',
        'job_level',
        'permissions_matrix',
        'status',
        'leave_balance',
        'job_title',
        'position_title',
        'requested_position_title',
        'department',
        'requested_department',
        'birthday_month',
        'birthday_day',
        'date_of_birth',
        'start_date',
        'staff_id_number',
        'id_expires_at',
        'contract_expires_at',
        'line_manager_id',
        'id_card_sent_at',
        'last_login_at',
        'previous_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'must_reset_password',
        'last_seen_at',
        'mute_sounds',
        'requested_change_at',
        'salary',
        'salary_advance_min_monthly_deduction',
        'payroll_deductions',
        'payroll_rewards_bonus',
        'payroll_notes',
        'contract_path',
        'job_description_path',

        // Sensitive Personal details
        'residential_address',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relation',
        'bank_name',
        'bank_branch',
        'bank_account_name',
        'bank_account_number',
        'momo_number',
        'momo_name',
        'ssnit_number',
        'nationality_code',
        'identity_document_type',
        'national_id_type',
        'national_id_number',
        'national_id_front_path',
        'national_id_back_path',
        'ghana_card_number',
        'ghana_card_path',
        'ghana_card_front_path',
        'ghana_card_back_path',
        'passport_number',
        'passport_photo_path',

        // Promoter specifics
        'tshirt_size',
        'height',
        'languages_spoken',
        'operational_city',

        // Merchandiser portal relationships
        'supervisor_id',
        'kd_id',
        'region_id',
        'tm_id',
        'dsr_id',
        'rsm_id',
        'merchandiser_working_days',
        'merchandiser_daily_outlet_target',
        'merchandiser_outlet_frequency',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'start_date' => 'date',
            'date_of_birth' => 'date',
            'id_expires_at' => 'date',
            'contract_expires_at' => 'date',
            'last_login_at' => 'datetime',
            'previous_login_at' => 'datetime',
            'id_card_sent_at' => 'datetime',
            'must_reset_password' => 'boolean',
            'last_seen_at' => 'datetime',
            'mute_sounds' => 'boolean',
            'requested_change_at' => 'datetime',
            'permissions_matrix' => 'array',
            'leave_balance' => 'integer',
            'height' => 'decimal:2',
            'salary' => 'decimal:2',
            'salary_advance_min_monthly_deduction' => 'decimal:2',
            'payroll_deductions' => 'decimal:2',
            'payroll_rewards_bonus' => 'decimal:2',
            'merchandiser_working_days' => 'array',
            'merchandiser_daily_outlet_target' => 'integer',
        ];
    }

    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'line_manager_id');
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }

    public function visitorLogs(): HasMany
    {
        return $this->hasMany(VisitorLog::class, 'host_id');
    }

    public function assetLogs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(Update::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
                    ->withPivot('is_admin')
                    ->withTimestamps();
    }

    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function tasksCreated(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_by');
    }

    public function merchandiserOutletAssignments(): HasMany
    {
        return $this->hasMany(MerchandiserOutletAssignment::class);
    }

    public function assignedMerchandiserOutlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'merchandiser_outlet_user')
            ->withPivot(['assigned_by', 'assigned_at', 'visit_days'])
            ->withTimestamps();
    }

    public function brandStaffAssignments(): HasMany
    {
        return $this->hasMany(BrandStaffAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the user has full HR privileges (salaries, contracts, appraisals, approvals).
     */
    public function hasFullHrAccess(): bool
    {
        if ($this->access_role === 'super_admin') {
            return true;
        }

        if (in_array(strtolower(trim($this->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true)) {
            return true;
        }

        $isCvo = ($this->position_title === 'CVO' || $this->job_level === 'CVO' || strtolower(trim($this->position_title ?? '')) === 'cvo');
        if ($isCvo) {
            return true;
        }

        $dept = self::normalizeDepartmentKey($this->department);
        $isHrDept = $dept === 'hr_admin';
        
        $positionTitle = strtolower(trim((string) $this->position_title));
        $isManagerLevel = in_array($positionTitle, ['manager', 'department head', 'hr manager'], true)
            || in_array($this->job_level, ['manager'], true);

        return $isHrDept && $isManagerLevel;
    }

    /**
     * Check if user can view confidential all-staff payroll ledger.
     * Restricted strictly to: HR Manager, Finance Department staff, Admin, Super Admin, CVO.
     */
    public function canViewAllPayroll(): bool
    {
        // 1. Super Admin or Admin access role
        if ($this->access_role === 'super_admin' || $this->access_role === 'admin' || strtolower(trim((string) $this->job_level)) === 'super_admin') {
            return true;
        }

        // 2. CVO
        $positionTitle = strtolower(trim((string) $this->position_title));
        $jobLevel = strtolower(trim((string) $this->job_level));
        if ($positionTitle === 'cvo' || $jobLevel === 'cvo') {
            return true;
        }

        $dept = self::normalizeDepartmentKey($this->department);

        // 3. Finance department staff
        if ($dept === 'finance') {
            return true;
        }

        // 4. HR Manager
        $isHrDept = ($dept === 'hr_admin');
        $isManagerLevel = in_array($positionTitle, ['hr manager', 'manager', 'department head'], true)
            || in_array($jobLevel, ['manager'], true);

        return ($isHrDept && $isManagerLevel) || str_contains($positionTitle, 'hr manager');
    }

    /**
     * Check if the user can review and approve fleet transport requests.
     * Restricted to HR & Admin department staff (excluding transport), HR Manager, CVO, and Super Admin.
     */
    public function canApproveFleetRequests(): bool
    {
        if ($this->hasFullHrAccess()) {
            return true;
        }

        $dept = self::normalizeDepartmentKey($this->department);

        return in_array($dept, ['hr_admin', 'admin'], true);
    }

    public function canReviewIdentityDocuments(): bool
    {
        $position = strtolower(trim((string) $this->position_title));
        $jobLevel = strtolower(trim((string) $this->job_level));

        if ($this->access_role === 'super_admin'
            || $jobLevel === 'super_admin'
            || $position === 'cvo'
            || $jobLevel === 'cvo') {
            return true;
        }

        if (self::normalizeDepartmentKey($this->department) !== 'hr_admin') {
            return false;
        }

        return $this->access_role === 'manager'
            || $jobLevel === 'manager'
            || in_array($position, ['manager', 'hr manager', 'department head'], true);
    }

    public function canManageHrAnnouncements(): bool
    {
        if ($this->isCvoOrSuperAdmin() || $this->hasFullHrAccess()) {
            return true;
        }

        if ($this->isMerchandiserAccount()) {
            return false;
        }

        $department = self::normalizeDepartmentKey($this->department);

        return $department === 'hr_admin';
    }

    /**
     * Get the user's HR/Admin role level:
     * 1 = HR Manager / Head
     * 2 = HR Assistant
     * 3 = Administrator / Front Desk, Transport Officer, Supporting Staff / Handy Man (or standard staff)
     */
    public function hrLevel(): int
    {
        if ($this->hasFullHrAccess()) {
            return 1;
        }

        $dept = self::normalizeDepartmentKey($this->department);
        if ($dept !== 'hr_admin') {
            return 3;
        }

        // Under HR/Admin or Transport department but doesn't have full HR access
        $title = strtolower(trim($this->job_title ?? ''));
        $pos = strtolower(trim($this->position_title ?? ''));

        // Identify Level 3 roles: Administrator / Front Desk, Transport, Supporting Staff / Driver / Handyman
        $isLevel3 = str_contains($title, 'admin') 
            || str_contains($title, 'front desk') 
            || str_contains($title, 'reception')
            || str_contains($title, 'transport')
            || str_contains($title, 'driver')
            || str_contains($title, 'handy')
            || str_contains($title, 'support')
            || str_contains($title, 'courier')
            || str_contains($pos, 'support')
            || str_contains($title, 'field')
            || str_contains($title, 'officer');

        if ($isLevel3) {
            return 3;
        }

        // Default to Level 2 (HR Assistant) for other HR/Admin department staff
        return 2;
    }

    /**
     * Determine if a viewer can edit a target user's privileges.
     */
    public static function canEditUser(User $viewer, User $target): bool
    {
        // 1. superadmin is untouchable
        if ($target->access_role === 'super_admin') {
            return false;
        }

        // 2. superadmin edits managers and everyone
        if ($viewer->access_role === 'super_admin' || in_array(strtolower(trim($viewer->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true)) {
            return true;
        }

        // 3. cvo edits everyone except super admin
        $viewerIsCvo = ($viewer->position_title === 'CVO' || $viewer->job_level === 'CVO' || strtolower(trim($viewer->position_title ?? '')) === 'cvo');
        if ($viewerIsCvo) {
            return true;
        }

        // 4. HR edits everyone except superadmin and cvo
        if ($viewer->hasFullHrAccess()) {
            $targetIsCvo = ($target->position_title === 'CVO' || $target->job_level === 'CVO' || strtolower(trim($target->position_title ?? '')) === 'cvo');
            return ! $targetIsCvo;
        }

        // 5. managers edits their subordinates privileges
        $viewerIsManager = ($viewer->access_role === 'manager' || $viewer->job_level === 'manager');
        if ($viewerIsManager) {
            return $target->line_manager_id === $viewer->id;
        }

        return false;
    }

    /**
     * Get the HR-entered monthly salary.
     */
    public function monthlySalary(): float
    {
        if (isset($this->salary) && floatval($this->salary) > 0) {
            return (float) $this->salary;
        }

        return 0.0;
    }

    public function isIdentityDocumentExempt(): bool
    {
        $email = strtolower(trim((string) $this->email));
        $jobLevel = strtolower(trim((string) $this->job_level));

        return in_array($email, ['cyrilhilton@cmih.africa', 'cyril@cmih.africa'], true)
            || $this->access_role === 'super_admin'
            || $jobLevel === 'super_admin'
            || $email === 'cmihstaffs@cmih.africa';
    }

    public function requiresIdentityDocument(): bool
    {
        return false;
    }

    public function identityNationalityCode(): string
    {
        $nationality = strtoupper(trim((string) $this->nationality_code));

        if ($nationality !== '') {
            return $nationality;
        }

        return filled($this->ghana_card_number) ? 'GH' : '';
    }

    public function effectiveIdentityDocumentType(): string
    {
        if (in_array($this->identity_document_type, ['national_id', 'passport'], true)) {
            return $this->identity_document_type;
        }

        if (filled($this->national_id_number) || filled($this->ghana_card_number)) {
            return 'national_id';
        }

        return filled($this->passport_number) ? 'passport' : 'national_id';
    }

    public function effectiveNationalIdNumber(): ?string
    {
        return $this->national_id_number ?: $this->ghana_card_number;
    }

    public function nationalIdFrontDocumentPath(): ?string
    {
        return $this->national_id_front_path
            ?: $this->ghana_card_front_path
            ?: $this->ghana_card_path;
    }

    public function nationalIdBackDocumentPath(): ?string
    {
        return $this->national_id_back_path ?: $this->ghana_card_back_path;
    }

    public function hasCompleteIdentityDocument(): bool
    {
        $nationality = $this->identityNationalityCode();

        if ($nationality === '') {
            return false;
        }

        if ($this->effectiveIdentityDocumentType() === 'passport') {
            return filled($this->passport_number) && filled($this->passport_photo_path);
        }

        $hasNationalId = filled($this->effectiveNationalIdNumber())
            && filled($this->nationalIdFrontDocumentPath());

        if (self::nationalIdRequiresBack($nationality)) {
            $hasNationalId = $hasNationalId && filled($this->nationalIdBackDocumentPath());
        }

        return $hasNationalId;
    }

    public function hasRequiredIdentityDocument(): bool
    {
        return true;
    }

    public static function nationalityOptions(): array
    {
        return config('identity.nationalities', []);
    }

    public static function nationalIdLabelFor(?string $nationalityCode): string
    {
        $code = strtoupper(trim((string) $nationalityCode));

        $configuredLabel = config("identity.national_id_labels.{$code}");
        if (filled($configuredLabel)) {
            return $configuredLabel;
        }

        $country = config("identity.nationalities.{$code}");

        return filled($country)
            ? $country.' National ID or Government-issued Photo ID'
            : config('identity.default_national_id_label', 'National ID or Government-issued Photo ID');
    }

    public static function nationalIdRequiresBack(?string $nationalityCode): bool
    {
        return in_array(
            strtoupper(trim((string) $nationalityCode)),
            config('identity.national_id_back_required', []),
            true
        );
    }

    /** True if user was seen in the last 3 minutes */
    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(3));
    }

    /** Human-readable last seen string */
    public function lastSeenLabel(): string
    {
        if ($this->isOnline()) {
            return 'Online';
        }
        if (! $this->last_seen_at) {
            return 'Offline';
        }
        return 'Last seen ' . $this->last_seen_at->diffForHumans();
    }

    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : array_map('trim', explode(',', $roles));

        return in_array($this->access_role, $roleList, true) || $this->access_role === 'super_admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->access_role === 'super_admin'
            || strtolower(trim((string) $this->job_level)) === 'super_admin';
    }

    public function isCvoOrSuperAdmin(): bool
    {
        $position = strtolower(trim((string) $this->position_title));
        $level = strtolower(trim((string) $this->job_level));

        return $this->isSuperAdmin()
            || $position === 'cvo'
            || $level === 'cvo';
    }

    public function isCyrilHilton(): bool
    {
        return in_array(strtolower(trim((string) $this->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    }

    public function isLineManager(): bool
    {
        if ($this->isCvoOrSuperAdmin() || $this->isCyrilHilton()) {
            return true;
        }

        $position = strtolower(trim((string) $this->position_title));

        return $this->access_role === 'manager'
            || $this->job_level === 'manager'
            || in_array($position, ['manager', 'department head', 'assistant manager'], true)
            || $this->subordinates()->exists();
    }

    public function isOperationsDepartmentLead(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $department = self::normalizeDepartmentKey($this->department);

        if (! in_array($department, ['operations_projects', 'operations'], true)) {
            return false;
        }

        $title = strtolower(trim(implode(' ', array_filter([
            (string) $this->job_title,
            (string) $this->position_title,
            (string) $this->job_level,
        ]))));

        $isExplicitHead = str_contains($title, 'hod')
            || str_contains($title, 'head')
            || str_contains($title, 'lead')
            || str_contains($title, 'operations manager')
            || $this->position_title === 'Department Head';

        return $isExplicitHead && $this->isLineManager();
    }

    public function isWarehouseAssetCollaborator(): bool
    {
        return WarehouseAssetCollaborator::where('user_id', $this->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canEditWarehouseAssets(): bool
    {
        if ($this->isSuperAdmin() || $this->isOperationsDepartmentLead() || $this->isActingForOperationsDepartmentLead()) {
            return true;
        }

        return WarehouseAssetCollaborator::where('user_id', $this->id)
            ->where('is_active', true)
            ->where('can_edit', true)
            ->exists();
    }

    public function isActingForOperationsDepartmentLead(): bool
    {
        $delegatedManagerIds = $this->activeDelegatedManagerIds();

        if ($delegatedManagerIds === []) {
            return false;
        }

        return static::whereIn('id', $delegatedManagerIds)
            ->get()
            ->contains(fn (User $manager) => $manager->isOperationsDepartmentLead());
    }

    public function canOwnWarehouseAssets(): bool
    {
        return $this->canEditWarehouseAssets();
    }

    public function canExportWarehouseAssets(): bool
    {
        return $this->canOwnWarehouseAssets()
            || $this->isWarehouseAssetCollaborator()
            || $this->isCvoOrSuperAdmin();
    }

    /**
     * Get active approved leave application for this user if currently on leave today with a delegate line manager.
     */
    public function activeLeaveDelegation(): ?LeaveApplication
    {
        return LeaveApplication::where('user_id', $this->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->whereNotNull('delegate_line_manager_id')
            ->latest('id')
            ->first();
    }

    /**
     * Check if this user is currently acting as a relief line manager for a given manager (or any manager).
     */
    public function isActingLineManagerFor(int|User|null $manager = null): bool
    {
        $query = LeaveApplication::where('delegate_line_manager_id', $this->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with('user');

        if ($manager !== null) {
            $managerId = $manager instanceof User ? $manager->id : (int) $manager;
            $query->where('user_id', $managerId);
        }

        return $query->get()
            ->contains(fn (LeaveApplication $leave) => $leave->user?->isLineManager());
    }

    /**
     * Get IDs of line managers for whom this user is currently acting as a relief line manager today.
     */
    public function activeDelegatedManagerIds(): array
    {
        return LeaveApplication::where('delegate_line_manager_id', $this->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with('user')
            ->get()
            ->filter(fn (LeaveApplication $leave) => $leave->user?->isLineManager())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check if user is a line manager or currently acting as relief line manager for someone.
     */
    public function isEffectiveLineManager(): bool
    {
        return $this->isLineManager() || $this->isActingLineManagerFor();
    }

    /**
     * Check if this user is a peer line manager in the same department as the given manager.
     * A peer line manager can approve tasks/leaves on behalf of an absent colleague in the same dept.
     */
    public function isPeerLineManagerOf(int $managerId): bool
    {
        if ((int) $this->id === $managerId) {
            return false; // not a peer of yourself
        }

        if (! $this->isLineManager()) {
            return false;
        }

        $absentManager = static::find($managerId);
        if (! $absentManager) {
            return false;
        }

        $myDept      = static::normalizeDepartmentKey((string) $this->department);
        $theirDept   = static::normalizeDepartmentKey((string) $absentManager->department);

        return $myDept !== '' && $myDept === $theirDept;
    }

    public function mustRouteTaskCompletionToManager(): bool
    {
        return ! $this->isEffectiveLineManager();
    }

    public static function roleLabel(?string $role): string
    {
        $value = trim((string) $role);

        if ($value === '') {
            return 'Staff';
        }

        return [
            'staff' => 'Staff',
            'ops' => 'Operations',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'super_admin' => 'Super Admin',
            'merchandiser' => 'Merchandiser',
            'merchandiser_supervisor' => 'Merchandiser Supervisor',
        ][$value] ?? Str::of($value)->replace(['_', '-'], ' ')->squish()->title()->toString();
    }

    public static function departmentLabel(?string $department, string $fallback = 'Unassigned'): string
    {
        $value = trim((string) $department);

        if ($value === '') {
            return $fallback;
        }

        $canonical = self::normalizeDepartmentKey($value);

        $labels = [
            'hr_admin' => 'HR & Admin',
            'finance' => 'Finance',
            'client_relations' => 'Client Relations',
            'operations_projects' => 'Operations & Projects',
            'brands_marketing' => 'Brands & Marketing',
            'creatives' => 'Creatives',
        ];

        return $labels[$canonical] ?? Str::of($value)->replace(['_', '-'], ' ')->squish()->title()->toString();
    }

    public static function normalizeDepartmentKey(?string $department): string
    {
        $value = trim((string) $department);

        if ($value === '') {
            return '';
        }

        $key = Str::of($value)
            ->lower()
            ->replace(['&', '/', '-', '.'], ' ')
            ->replaceMatches('/\band\b/', ' ')
            ->replaceMatches('/\b(department|dept)\b/', ' ')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $aliases = [
            'admin' => 'hr_admin',
            'hr' => 'hr_admin',
            'hr_admin' => 'hr_admin',
            'hr_and_admin' => 'hr_admin',
            'hr_administration' => 'hr_admin',
            'human_resources' => 'hr_admin',
            'transport' => 'hr_admin',

            'finance' => 'finance',
            'accounts' => 'finance',
            'accounting' => 'finance',

            'client_service' => 'client_relations',
            'client_relation' => 'client_relations',
            'client_relations' => 'client_relations',
            'client_and_relations' => 'client_relations',
            'client' => 'client_relations',

            'operations' => 'operations_projects',
            'operation' => 'operations_projects',
            'ops' => 'operations_projects',
            'project' => 'operations_projects',
            'projects' => 'operations_projects',
            'operations_and_projects' => 'operations_projects',
            'operations_project' => 'operations_projects',
            'operations_projects' => 'operations_projects',

            'brand' => 'brands_marketing',
            'brands' => 'brands_marketing',
            'marketing' => 'brands_marketing',
            'brands_and_marketing' => 'brands_marketing',
            'brand_marketing' => 'brands_marketing',
            'brands_marketing' => 'brands_marketing',

            'creative' => 'creatives',
            'creative_department' => 'creatives',
            'creatives' => 'creatives',
        ];

        return $aliases[$key] ?? $key;
    }

    public static function departmentAliases(?string $department): array
    {
        $canonical = self::normalizeDepartmentKey($department);

        $aliases = [
            'hr_admin' => [
                'hr_admin', 'hr_and_admin', 'admin', 'transport', 'hr', 'human_resources',
                'HR Admin', 'HR & Admin', 'HR and Admin', 'Human Resources',
            ],
            'finance' => [
                'finance', 'accounts', 'accounting',
                'Finance', 'Finance Department', 'Accounts', 'Accounting',
            ],
            'client_relations' => [
                'client_relations', 'client_relation', 'client_service', 'client_and_relations', 'client',
                'Client Relations', 'Client Relation', 'Client Service',
            ],
            'operations_projects' => [
                'operations_projects', 'operations_project', 'operations_and_projects', 'operations', 'operation', 'ops', 'project', 'projects',
                'Operations Projects', 'Operations / Projects', 'Operations & Projects', 'Operations and Projects',
            ],
            'brands_marketing' => [
                'brands_marketing', 'brand_marketing', 'brands_and_marketing', 'brands', 'brand', 'marketing',
                'Brands Marketing', 'Brands & Marketing', 'Brands and Marketing', 'Brand Marketing',
            ],
            'creatives' => [
                'creatives', 'creative', 'creative_department',
                'Creatives', 'Creative', 'Creative Department',
            ],
        ];

        return collect($aliases[$canonical] ?? [$canonical, trim((string) $department)])
            ->push($canonical)
            ->push(trim((string) $department))
            ->flatMap(fn ($value) => [$value, strtolower((string) $value)])
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function scopeInternalStaff($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('access_role')
              ->orWhereNotIn('access_role', [
                  ...self::MERCHANDISER_FIELD_ROLES,
                  self::BRAND_PROMOTER_ROLE,
              ]);
        });
    }

    public function scopeMerchandisers($query)
    {
        return $query->whereIn('access_role', self::MERCHANDISER_FIELD_ROLES);
    }

    public function scopeMerchandiserSupervisors($query)
    {
        return $query->where('access_role', self::MERCHANDISER_SUPERVISOR_ROLE);
    }

    public function isMerchandiserAccount(): bool
    {
        return in_array($this->access_role, self::MERCHANDISER_FIELD_ROLES, true);
    }

    public function isBrandPromoterAccount(): bool
    {
        return $this->access_role === self::BRAND_PROMOTER_ROLE;
    }

    public function isMerchandiserSupervisor(): bool
    {
        return $this->access_role === self::MERCHANDISER_SUPERVISOR_ROLE;
    }

    public function nextBirthdayDate(?Carbon $from = null): ?Carbon
    {
        if (! $this->birthday_month || ! $this->birthday_day) {
            return null;
        }

        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $birthday = $this->birthdayDateForYear((int) $from->year);

        if (! $birthday) {
            return null;
        }

        if ($birthday->lt($from)) {
            $birthday = $this->birthdayDateForYear((int) $from->copy()->addYear()->year);
        }

        return $birthday;
    }

    private function birthdayDateForYear(int $year): ?Carbon
    {
        $month = (int) $this->birthday_month;
        $day = (int) $this->birthday_day;

        try {
            return Carbon::createSafe($year, $month, $day, 0, 0, 0)?->startOfDay();
        } catch (\Throwable) {
            if ($month === 2 && $day === 29) {
                return Carbon::create($year, 2, 28)->startOfDay();
            }

            return null;
        }
    }

    public function isBrandsTeamMember(): bool
    {
        $department = strtolower(trim((string) $this->department));

        return in_array($department, ['brands_marketing', 'brands', 'brand_marketing', 'brands & marketing'], true);
    }

    public function isMerchandiserPortalAdmin(): bool
    {
        return $this->hasRole(['admin', 'super_admin'])
            || $this->isBrandsTeamMember()
            || $this->isMerchandiserSupervisor();
    }

    public function profilePhotoUrl(): string
    {
        if ($this->profile_photo_path) {
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return asset('images/CMIH%20WEB%20ASSETS/Company%20logo/CMIH%20Logo_light%20theme.png');
    }

    public function idCardReady(): bool
    {
        return (bool) ($this->staff_id_number
            && $this->start_date
            && $this->id_expires_at
            && $this->department
            && $this->profile_photo_path);
    }

    public static function generateStaffIdNumber(?int $year = null): string
    {
        $year = $year ?? now()->year;
        $prefix = 'CMIH-'.$year.'-';

        $latest = static::query()
            ->where('staff_id_number', 'like', $prefix.'%')
            ->orderBy('staff_id_number', 'desc')
            ->value('staff_id_number');

        $sequence = 1;

        if ($latest) {
            $parts = explode('-', $latest);
            $suffix = end($parts);
            if (is_string($suffix) && ctype_digit($suffix)) {
                $sequence = (int) $suffix + 1;
            }
        }

        do {
            $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::where('staff_id_number', $candidate)->exists());

        return $candidate;
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function merchandiserKd(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }

    public function merchandiserRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function merchandiserTm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tm_id');
    }

    public function merchandiserDsr(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dsr_id');
    }

    public function merchandiserRsm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rsm_id');
    }

    public function merchandiserAttendances(): HasMany
    {
        return $this->hasMany(MerchandiserAttendance::class);
    }

    public function merchandiserLocations(): HasMany
    {
        return $this->hasMany(MerchandiserLocation::class);
    }

    public function merchandiserVisits(): HasMany
    {
        return $this->hasMany(MerchandiserVisit::class);
    }

    public function merchandiserOrders(): HasMany
    {
        return $this->hasMany(MerchandiserOrder::class);
    }
}
