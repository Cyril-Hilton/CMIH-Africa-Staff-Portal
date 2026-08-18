<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\TaskController as AdminTaskController;
use App\Http\Controllers\Admin\UpdateController as AdminUpdateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Brands\BrandsPlatformController;
use App\Http\Controllers\Admin\PortfolioController as AdminPortfolioController;
use App\Http\Controllers\Portal\AnnouncementController;
use App\Http\Controllers\Portal\AssetController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DirectoryController;
use App\Http\Controllers\Portal\MessageController;
use App\Http\Controllers\Portal\TaskController;
use App\Http\Controllers\Portal\UpdateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');


Route::view('/about', 'pages.about')->name('about');
Route::view('/services', 'pages.services')->name('services');
Route::get('/portfolio', [App\Http\Controllers\PortfolioController::class, 'index'])->name('portfolio');
Route::get('/portfolio/{album}', [App\Http\Controllers\PortfolioController::class, 'show'])->name('portfolio.show');
Route::post('/portfolio/pay', [App\Http\Controllers\PortfolioPaymentController::class, 'initialize'])->name('portfolio.pay');
Route::get('/portfolio/pay/callback', [App\Http\Controllers\PortfolioPaymentController::class, 'callback'])->name('portfolio.pay.callback');
Route::get('/news', [SiteController::class, 'news'])->name('news');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/privacy-policy', 'pages.privacy')->name('privacy');
Route::view('/terms-of-service', 'pages.terms')->name('terms');
Route::view('/legal-notice', 'pages.legal')->name('legal');
Route::view('/disclaimer', 'pages.disclaimer')->name('disclaimer');

Route::get('/shared/campaign/{token}', [\App\Http\Controllers\Portal\CampaignShareController::class, 'viewSharedCampaign'])->name('campaign.share.view');
Route::post('/shared/campaign/{token}/upload', [\App\Http\Controllers\Portal\CampaignShareController::class, 'uploadSharedPhoto'])
    ->middleware('throttle:20,1')
    ->name('campaign.share.upload');

// Public Survey Routes
Route::get('/surveys/{survey:slug}', [\App\Http\Controllers\SiteController::class, 'showSurvey'])->name('surveys.show');
Route::post('/surveys/{survey:slug}/submit', [\App\Http\Controllers\SiteController::class, 'submitSurvey'])
    ->middleware('throttle:10,1')
    ->name('surveys.submit');

Route::prefix('brands')->name('brands-platform.')->group(function () {
    Route::get('/', [BrandsPlatformController::class, 'index'])->name('index');
    Route::get('/notifications', [BrandsPlatformController::class, 'notifications'])
        ->middleware(['auth', 'active'])
        ->name('notifications');
    Route::post('/notifications/read-all', [BrandsPlatformController::class, 'markAllNotificationsAsRead'])
        ->middleware(['auth', 'active'])
        ->name('notifications.readAll');
    Route::get('/notifications/{notification}/read', [BrandsPlatformController::class, 'markNotificationAsRead'])
        ->middleware(['auth', 'active'])
        ->name('notifications.read');
    Route::get('/gallery', [BrandsPlatformController::class, 'gallery'])
        ->middleware(['auth', 'active'])
        ->name('gallery');
    Route::get('/client/report/{token}', [BrandsPlatformController::class, 'clientReport'])->name('client-report');
    Route::get('/{brand}/publications', [BrandsPlatformController::class, 'publications'])->name('publications');
    Route::get('/{brand}/activation', [BrandsPlatformController::class, 'activation'])->name('activation');
    Route::get('/{brand}/consumer', [BrandsPlatformController::class, 'consumer'])->name('consumer');
    Route::get('/{brand}', [BrandsPlatformController::class, 'show'])->name('show');
    Route::get('/{brand}/support-login', [BrandsPlatformController::class, 'showSupportLogin'])->name('support-login');
    Route::get('/{brand}/agency-login', [BrandsPlatformController::class, 'showAgencyLogin'])->name('agency-login');
    Route::post('/{brand}/consumer-entry', [BrandsPlatformController::class, 'storeConsumerEntry'])
        ->middleware('throttle:30,1')
        ->name('consumer-entry.store');
    Route::get('/{brand}/consumer-entry/{token}', [BrandsPlatformController::class, 'verifyConsumerEntry'])
        ->name('consumer-entry.verify');
    Route::post('/{brand}/consumer-entry/{token}', [BrandsPlatformController::class, 'completeConsumerVerification'])
        ->middleware('throttle:20,1')
        ->name('consumer-entry.complete');
});

Route::middleware(['auth', 'active'])->prefix('brands')->name('brands-platform.')->group(function () {
    Route::get('/admin/console', [BrandsPlatformController::class, 'admin'])->name('admin');
    Route::get('/admin/staff-feed', [BrandsPlatformController::class, 'staffFeed'])->name('admin.staff-feed');
    Route::post('/admin/brands', [BrandsPlatformController::class, 'storeBrand'])->name('admin.brands.store');
    Route::post('/admin/{brand}/activations', [BrandsPlatformController::class, 'storeActivation'])->name('admin.activations.store');
    Route::post('/admin/{brand}/publications', [BrandsPlatformController::class, 'storePublication'])->name('admin.publications.store');
    Route::post('/admin/activations/{activation}/client-link', [BrandsPlatformController::class, 'generateClientLink'])->name('admin.client-link.generate');
    Route::post('/admin/{brand}/assignments', [BrandsPlatformController::class, 'storeAssignment'])->name('admin.assignments.store');
    Route::delete('/admin/assignments/{assignment}', [BrandsPlatformController::class, 'destroyAssignment'])->name('admin.assignments.destroy');
    Route::get('/{brand}/gallery', [BrandsPlatformController::class, 'gallery'])->name('brand-gallery');
    Route::get('/{brand}/agency', [BrandsPlatformController::class, 'agency'])->name('agency');
    Route::post('/{brand}/agency/publications', [BrandsPlatformController::class, 'storeAgencyPublication'])->name('agency.publications.store');
    Route::get('/{brand}/support', [BrandsPlatformController::class, 'support'])->name('support');
    Route::get('/{brand}/retail', [BrandsPlatformController::class, 'retail'])->name('retail');
    Route::get('/{brand}/export/{type}', [BrandsPlatformController::class, 'exportReport'])
        ->whereIn('type', ['current', 'daily', 'weekly', 'retail', 'promoter', 'consumer-insights', 'closeout'])
        ->name('export');
    Route::post('/{brand}/field-activity', [BrandsPlatformController::class, 'storeFieldActivity'])->name('field-activity.store');
    Route::post('/{brand}/clock-in', [BrandsPlatformController::class, 'clockIn'])->name('clock-in');
    Route::post('/{brand}/break-start', [BrandsPlatformController::class, 'startBreak'])->name('break-start');
    Route::post('/{brand}/break-end', [BrandsPlatformController::class, 'endBreak'])->name('break-end');
    Route::post('/{brand}/clock-out', [BrandsPlatformController::class, 'clockOut'])->name('clock-out');
    // Staff enrollment (CMIH API import)
    Route::post('/{brand}/team', [BrandsPlatformController::class, 'storeAgencyTeamMember'])->name('team.store');
    Route::put('/{brand}/team/{assignment}', [BrandsPlatformController::class, 'updateAgencyTeamMember'])->name('team.update');
    Route::delete('/{brand}/team/{assignment}', [BrandsPlatformController::class, 'archiveAgencyTeamMember'])->name('team.destroy');
    // Manual staff enrollment (promoters & retail terminal)
    Route::post('/{brand}/staff/enroll', [BrandsPlatformController::class, 'enrollStaff'])->name('staff.enroll');
    // Venue change (preserves history)
    Route::put('/{brand}/staff/{assignment}/venue', [BrandsPlatformController::class, 'updateStaffVenue'])->name('staff.update-venue');
    // Shift time adjustment (in-place update, no history row needed)
    Route::patch('/{brand}/staff/{assignment}/shift', [BrandsPlatformController::class, 'updateStaffShift'])->name('staff.update-shift');
    // Venue history (JSON endpoint for modal)
    Route::get('/{brand}/staff/{assignment}/history', [BrandsPlatformController::class, 'staffVenueHistory'])->name('staff.venue-history');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'active', 'clocked_in'])
    ->name('dashboard');

Route::middleware(['auth', 'active', 'clocked_in'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/updates', [UpdateController::class, 'index'])->name('updates');
    Route::post('/updates', [UpdateController::class, 'store'])->name('updates.store');
    Route::get('/updates/{update}/edit', [UpdateController::class, 'edit'])->name('updates.edit');
    Route::patch('/updates/{update}', [UpdateController::class, 'update'])->name('updates.update');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/completion-review', [TaskController::class, 'completionReview'])->name('tasks.completion-review');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/notifications', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/notifications/{notification}/read', [AnnouncementController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [AnnouncementController::class, 'markAllAsRead'])->name('notifications.readAll');

    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');
    Route::post('/directory/{user}/privileges', [DirectoryController::class, 'updatePrivileges'])->name('directory.privileges');

    // Collaborative Workspace & Budgets Hub
    Route::get('/workspace', [\App\Http\Controllers\Portal\WorkspaceController::class, 'index'])->name('workspace.index');
    Route::get('/workspace/create', [\App\Http\Controllers\Portal\WorkspaceController::class, 'create'])->name('workspace.create');
    Route::post('/workspace', [\App\Http\Controllers\Portal\WorkspaceController::class, 'store'])->name('workspace.store');
    Route::get('/workspace/{workspace}', [\App\Http\Controllers\Portal\WorkspaceController::class, 'show'])->name('workspace.show');
    Route::get('/workspace/{workspace}/edit', [\App\Http\Controllers\Portal\WorkspaceController::class, 'edit'])->name('workspace.edit');
    Route::match(['put', 'patch'], '/workspace/{workspace}', [\App\Http\Controllers\Portal\WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspace/{workspace}', [\App\Http\Controllers\Portal\WorkspaceController::class, 'destroy'])->name('workspace.destroy');
    Route::post('/workspace/{workspace}/collaborators', [\App\Http\Controllers\Portal\WorkspaceController::class, 'updateCollaborators'])->name('workspace.collaborators');
    Route::post('/workspace/{workspace}/submit', [\App\Http\Controllers\Portal\WorkspaceController::class, 'submit'])->name('workspace.submit');
    Route::post('/workspace/{workspace}/action', [\App\Http\Controllers\Portal\WorkspaceController::class, 'action'])->name('workspace.action');
    Route::get('/workspace/{workspace}/export', [\App\Http\Controllers\Portal\WorkspaceController::class, 'export'])->name('workspace.export');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::match(['put', 'patch'], '/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::get('/messages/attachments/{message}', [MessageController::class, 'downloadAttachment'])->name('messages.attachment');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/send', [MessageController::class, 'sendMessage'])->name('messages.send');
    Route::post('/messages/groups/create', [MessageController::class, 'createGroup'])->name('messages.groups.create');
    Route::post('/messages/dms/start', [MessageController::class, 'startDm'])->name('messages.dms.start');
    Route::post('/messages/{conversation}/members/add', [MessageController::class, 'addMember'])->name('messages.members.add');
    Route::post('/messages/{conversation}/members/remove/{user}', [MessageController::class, 'removeMember'])->name('messages.members.remove');
    Route::post('/messages/edit/{message}', [MessageController::class, 'editMessage'])->name('messages.edit');
    Route::post('/messages/delete/{message}', [MessageController::class, 'deleteMessage'])->name('messages.delete');
    Route::post('/messages/forward/{message}', [MessageController::class, 'forwardMessage'])->name('messages.forward');
    Route::post('/messages/broadcast/create', [MessageController::class, 'createBroadcast'])->name('messages.broadcast.create');

    Route::post('/attendance/clock-in', [\App\Http\Controllers\Portal\AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [\App\Http\Controllers\Portal\AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::get('/attendance-performance/export', \App\Http\Controllers\Portal\AttendancePerformanceExportController::class)->name('attendance-performance.export');

    Route::get('/payroll', [\App\Http\Controllers\Portal\PayrollController::class, 'index'])->name('payroll');
    Route::post('/payroll/banking', [\App\Http\Controllers\Portal\PayrollController::class, 'updateBanking'])->name('payroll.banking');
    Route::post('/payroll/{user}/salary', [\App\Http\Controllers\Portal\PayrollController::class, 'updateSalary'])->name('payroll.salary');
    Route::post('/payroll/distribute', [\App\Http\Controllers\Portal\PayrollController::class, 'distributePayslips'])->name('payroll.distribute');
    Route::get('/payroll/payslips/{payslip}', [\App\Http\Controllers\Portal\PayrollController::class, 'downloadPayslip'])->name('payroll.payslip');
    Route::get('/payroll/export-register', [\App\Http\Controllers\Portal\PayrollController::class, 'exportPayrollRegister'])->name('payroll.export-register');
    Route::get('/payroll/export-staff-directory', [\App\Http\Controllers\Portal\PayrollController::class, 'exportStaffDirectory'])->name('payroll.export-staff-directory');
    Route::get('/payroll/documents/{user}/{type}', [\App\Http\Controllers\Portal\PayrollController::class, 'downloadDocument'])
        ->whereIn('type', [
            'contract',
            'job-description',
            'national-id-front',
            'national-id-back',
            'passport-document',
            'ghana-card-front',
            'ghana-card-back',
            'passport-photo',
        ])
        ->name('payroll.document');

    Route::get('/dropbox', [\App\Http\Controllers\Portal\DropboxController::class, 'index'])->name('dropbox');

    Route::get('/import/{table}', [\App\Http\Controllers\Portal\ImportController::class, 'showUploadForm'])->name('import.show');
    Route::post('/import/{table}/process', [\App\Http\Controllers\Portal\ImportController::class, 'processUpload'])->name('import.process');
    Route::post('/import/{table}/execute', [\App\Http\Controllers\Portal\ImportController::class, 'executeImport'])->name('import.execute');

    Route::get('/export/{table}', [\App\Http\Controllers\Portal\ExportController::class, 'export'])->name('export');

    Route::post('/share', [\App\Http\Controllers\Portal\ShareController::class, 'share'])->name('share');

    Route::get('/leaves', [\App\Http\Controllers\Portal\LeaveController::class, 'index'])->name('leaves');
    Route::post('/leaves', [\App\Http\Controllers\Portal\LeaveController::class, 'store'])->name('leaves.store');
    Route::post('/leaves/{leave}/approve', [\App\Http\Controllers\Portal\LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [\App\Http\Controllers\Portal\LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('/leaves/{leave}/return', [\App\Http\Controllers\Portal\LeaveController::class, 'returnForCorrection'])->name('leaves.return');
    Route::post('/leaves/{leave}/resubmit', [\App\Http\Controllers\Portal\LeaveController::class, 'resubmit'])->name('leaves.resubmit');
    Route::get('/fleet-requests', [\App\Http\Controllers\Portal\FleetRequestController::class, 'index'])->name('fleet-requests');
    Route::post('/fleet-requests', [\App\Http\Controllers\Portal\FleetRequestController::class, 'store'])->name('fleet-requests.store');
    Route::post('/fleet-requests/{fleetRequest}/resubmit', [\App\Http\Controllers\Portal\FleetRequestController::class, 'resubmit'])->name('fleet-requests.resubmit');
    Route::post('/fleet-requests/{fleetRequest}/action', [\App\Http\Controllers\Portal\FleetRequestController::class, 'action'])->name('fleet-requests.action');

    Route::post('/action-points', [\App\Http\Controllers\Portal\ActionPointController::class, 'store'])->name('action-points.store');
    Route::patch('/action-points/{actionPoint}', [\App\Http\Controllers\Portal\ActionPointController::class, 'update'])->name('action-points.update');
    Route::delete('/action-points/{actionPoint}', [\App\Http\Controllers\Portal\ActionPointController::class, 'destroy'])->name('action-points.destroy');


    Route::get('/id-card', [ProfileController::class, 'idCard'])->name('id-card');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::get('/notifications/poll', [\App\Http\Controllers\Portal\NotificationController::class, 'poll'])->name('notifications.poll');

     // Department Modules
    Route::get('/visitors', [\App\Http\Controllers\Portal\DepartmentController::class, 'visitors'])->name('visitors');
    Route::get('/hr', [\App\Http\Controllers\Portal\DepartmentController::class, 'hr'])->name('hr');
    Route::post('/hr/announcements', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeHrAnnouncement'])->name('hr.announcements.store');
    Route::post('/hr/staff/{user}/leave-balance', [\App\Http\Controllers\Portal\DepartmentController::class, 'updateLeaveBalance'])->name('hr.leave-balance.update');
    Route::post('/hr/visitors', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeVisitor'])->name('hr.visitors.store');
    Route::post('/hr/visitors/{visitor}/checkout', [\App\Http\Controllers\Portal\DepartmentController::class, 'checkoutVisitor'])->name('hr.visitors.checkout');
    Route::post('/hr/appraisals/metrics', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeAppraisalMetric'])->name('hr.appraisals.metrics.store');
    Route::delete('/hr/appraisals/metrics/{metric}', [\App\Http\Controllers\Portal\DepartmentController::class, 'destroyAppraisalMetric'])->name('hr.appraisals.metrics.destroy');

    Route::get('/finance', [\App\Http\Controllers\Portal\DepartmentController::class, 'finance'])->name('finance');
    Route::post('/finance/claims', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeClaim'])->name('finance.claims.store');
    Route::get('/finance/claims/{claim}/receipt', [\App\Http\Controllers\Portal\DepartmentController::class, 'downloadClaimReceipt'])->name('finance.claims.receipt');
    Route::post('/finance/claims/{claim}/{action}', [\App\Http\Controllers\Portal\DepartmentController::class, 'actionClaim'])->name('finance.claims.action');
    Route::post('/finance/claims/{claim}/resubmit', [\App\Http\Controllers\Portal\DepartmentController::class, 'resubmitClaim'])->name('finance.claims.resubmit');
    Route::get('/finance/advances', [\App\Http\Controllers\Portal\DepartmentController::class, 'advancesIndex'])->name('finance.advances.index');
    Route::post('/finance/advances', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeAdvance'])->name('finance.advances.store');
    Route::post('/finance/advances/{advance}/resubmit', [\App\Http\Controllers\Portal\DepartmentController::class, 'resubmitAdvance'])->name('finance.advances.resubmit');
    Route::post('/finance/advances/{advance}/finance-action', [\App\Http\Controllers\Portal\DepartmentController::class, 'financeActionAdvance'])->name('finance.advances.finance-action');
    Route::post('/finance/advances/{advance}/cvo-action', [\App\Http\Controllers\Portal\DepartmentController::class, 'cvoActionAdvance'])->name('finance.advances.cvo-action');

    Route::get('/operations', [\App\Http\Controllers\Portal\DepartmentController::class, 'operations'])->name('operations');
    Route::post('/operations/vendors', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeVendor'])->name('operations.vendors.store');
    Route::post('/operations/promoters', [\App\Http\Controllers\Portal\DepartmentController::class, 'storePromoter'])->name('operations.promoters.store');
    Route::post('/operations/assets/checkout', [\App\Http\Controllers\Portal\DepartmentController::class, 'checkoutAsset'])->name('operations.assets.checkout');
    Route::post('/operations/assets/{log}/checkin', [\App\Http\Controllers\Portal\DepartmentController::class, 'checkinAsset'])->name('operations.assets.checkin');

    Route::get('/brands', [\App\Http\Controllers\Portal\DepartmentController::class, 'brands'])->name('brands');
    Route::post('/brands/strategy', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeStrategy'])->name('brands.strategy.store');

    Route::get('/creative', [\App\Http\Controllers\Portal\DepartmentController::class, 'creative'])->name('creative');
    Route::post('/creative/briefs', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeBrief'])->name('creative.briefs.store');
    Route::post('/creative/files', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeDesignFile'])->name('creative.files.store');

    Route::post('/campaigns/{campaign}/generate-share', [\App\Http\Controllers\Portal\CampaignShareController::class, 'generateShareLink'])->name('campaigns.generate-share');
    Route::post('/campaigns', [\App\Http\Controllers\Portal\CampaignShareController::class, 'storeCampaign'])->name('campaigns.store');
    Route::patch('/campaigns/{campaign}', [\App\Http\Controllers\Portal\CampaignShareController::class, 'updateCampaign'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [\App\Http\Controllers\Portal\CampaignShareController::class, 'destroyCampaign'])->name('campaigns.destroy');

    // ── Phase 3: Task Reassignment & Custom Dashboard Columns ──────────────────
    Route::post('/tasks/{task}/reassign', [\App\Http\Controllers\Portal\TaskController::class, 'reassign'])->name('tasks.reassign');
    Route::get('/dashboard/live', [\App\Http\Controllers\Portal\DashboardController::class, 'live'])->name('dashboard.live');
    Route::post('/dashboard/columns', [\App\Http\Controllers\Portal\DashboardController::class, 'storeColumn'])->name('dashboard.columns.store');
    Route::post('/dashboard/weekly-consolidated', [\App\Http\Controllers\Portal\DashboardController::class, 'storeWeeklyConsolidated'])->name('dashboard.weekly-consolidated.store');
    Route::post('/dashboard/weekly-consolidated/columns', [\App\Http\Controllers\Portal\DashboardController::class, 'storeWeeklyConsolidatedColumn'])->name('dashboard.weekly-consolidated.columns.store');
    
    // ── Performance Awards Leaderboard & Locking ──────────────────────────────
    Route::get('/awards/standings', [\App\Http\Controllers\Portal\PerformanceAwardController::class, 'getStandings'])->name('awards.standings');
    Route::post('/awards/lock', [\App\Http\Controllers\Portal\PerformanceAwardController::class, 'lockAward'])->name('awards.lock');
    Route::post('/awards/resend-certificates', [\App\Http\Controllers\Portal\PerformanceAwardController::class, 'resendCertificates'])->name('awards.resend-certificates');
    Route::patch('/dashboard/columns/{column}', [\App\Http\Controllers\Portal\DashboardController::class, 'updateColumn'])->name('dashboard.columns.update');
    Route::delete('/dashboard/columns/{column}', [\App\Http\Controllers\Portal\DashboardController::class, 'destroyColumn'])->name('dashboard.columns.destroy');
    Route::patch('/dashboard/weekly-consolidated/columns/{column}', [\App\Http\Controllers\Portal\DashboardController::class, 'updateWeeklyConsolidatedColumn'])->name('dashboard.weekly-consolidated.columns.update');
    Route::delete('/dashboard/weekly-consolidated/columns/{column}', [\App\Http\Controllers\Portal\DashboardController::class, 'destroyWeeklyConsolidatedColumn'])->name('dashboard.weekly-consolidated.columns.destroy');
    Route::patch('/dashboard/weekly-consolidated/{item}', [\App\Http\Controllers\Portal\DashboardController::class, 'updateWeeklyConsolidated'])->name('dashboard.weekly-consolidated.update');
    Route::delete('/dashboard/weekly-consolidated/{item}', [\App\Http\Controllers\Portal\DashboardController::class, 'destroyWeeklyConsolidated'])->name('dashboard.weekly-consolidated.destroy');

    // ── Meeting Action Points ──────────────────────────────────────────────────
    Route::post('/action-points', [\App\Http\Controllers\Portal\ActionPointController::class, 'store'])->name('action-points.store');
    Route::patch('/action-points/{actionPoint}', [\App\Http\Controllers\Portal\ActionPointController::class, 'update'])->name('action-points.update');
    Route::delete('/action-points/{actionPoint}', [\App\Http\Controllers\Portal\ActionPointController::class, 'destroy'])->name('action-points.destroy');

    // ── Phase 3: Appraisal Pipeline (1-10 Scale) ──────────────────────────────
    Route::get('/appraisals', [\App\Http\Controllers\Portal\AppraisalController::class, 'index'])->name('appraisals.index');
    Route::post('/appraisals', [\App\Http\Controllers\Portal\AppraisalController::class, 'create'])->name('appraisals.create');
    Route::get('/appraisals/{appraisal}/self', [\App\Http\Controllers\Portal\AppraisalController::class, 'showSelfForm'])->name('appraisals.self.form');
    Route::post('/appraisals/{appraisal}/self', [\App\Http\Controllers\Portal\AppraisalController::class, 'submitSelf'])->name('appraisals.self.submit');
    Route::get('/appraisals/{appraisal}/manager', [\App\Http\Controllers\Portal\AppraisalController::class, 'showManagerForm'])->name('appraisals.manager.form');
    Route::post('/appraisals/{appraisal}/manager', [\App\Http\Controllers\Portal\AppraisalController::class, 'submitManager'])->name('appraisals.manager.submit');
    Route::get('/appraisals/{appraisal}/audit', [\App\Http\Controllers\Portal\AppraisalController::class, 'showAuditForm'])->name('appraisals.audit.form');
    Route::post('/appraisals/{appraisal}/audit', [\App\Http\Controllers\Portal\AppraisalController::class, 'submitAudit'])->name('appraisals.audit.submit');
    Route::post('/appraisals/{appraisal}/unlock', [\App\Http\Controllers\Portal\AppraisalController::class, 'unlock'])->name('appraisals.unlock');
    Route::get('/appraisals/reports/{user}', [\App\Http\Controllers\Portal\AppraisalController::class, 'report'])->name('appraisals.report');

    // ── Phase 3: HR Visitor Pre-Ticketing ─────────────────────────────────────
    Route::post('/hr/pre-tickets', [\App\Http\Controllers\Portal\DepartmentController::class, 'storePreTicket'])->name('hr.pre-tickets.store');
    Route::patch('/hr/pre-tickets/{ticket}/arrive', [\App\Http\Controllers\Portal\DepartmentController::class, 'markPreTicketArrived'])->name('hr.pre-tickets.arrive');

    // ── Phase 3: Phone & Vendor Directory ─────────────────────────────────────
    Route::post('/hr/directory', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeDirectoryEntry'])->name('hr.directory.store');
    Route::delete('/hr/directory/{entry}', [\App\Http\Controllers\Portal\DepartmentController::class, 'destroyDirectoryEntry'])->name('hr.directory.destroy');

    // ── Standalone Project Budgets CRUD & Collaborators ────────────────────────
    Route::get('/finance/budgets', [\App\Http\Controllers\Portal\BudgetController::class, 'index'])->name('finance.budgets.index');
    Route::get('/finance/budgets/create', [\App\Http\Controllers\Portal\BudgetController::class, 'create'])->name('finance.budgets.create');
    Route::post('/finance/budgets', [\App\Http\Controllers\Portal\BudgetController::class, 'store'])->name('finance.budgets.store');
    Route::get('/finance/budgets/{budget}', [\App\Http\Controllers\Portal\BudgetController::class, 'show'])->name('finance.budgets.show');
    Route::get('/finance/budgets/{budget}/edit', [\App\Http\Controllers\Portal\BudgetController::class, 'edit'])->name('finance.budgets.edit');
    Route::put('/finance/budgets/{budget}', [\App\Http\Controllers\Portal\BudgetController::class, 'update'])->name('finance.budgets.update');
    Route::delete('/finance/budgets/{budget}', [\App\Http\Controllers\Portal\BudgetController::class, 'destroy'])->name('finance.budgets.destroy');
    Route::post('/finance/budgets/{budget}/collaborators', [\App\Http\Controllers\Portal\BudgetController::class, 'updateCollaborators'])->name('finance.budgets.collaborators');
    Route::post('/finance/budgets/{budget}/submit', [\App\Http\Controllers\Portal\BudgetController::class, 'submit'])->name('finance.budgets.submit');
    Route::post('/finance/budgets/{budget}/action/{action}', [\App\Http\Controllers\Portal\BudgetController::class, 'action'])->name('finance.budgets.action');
    Route::post('/finance/budgets/{budget}/items', [\App\Http\Controllers\Portal\BudgetController::class, 'storeItem'])->name('finance.budgets.items.store');
    Route::delete('/finance/budgets/{budget}/items/{item}', [\App\Http\Controllers\Portal\BudgetController::class, 'destroyItem'])->name('finance.budgets.items.destroy');
    Route::get('/finance/budgets/{budget}/export', [\App\Http\Controllers\Portal\BudgetController::class, 'export'])->name('finance.budgets.export');
    Route::post('/finance/budgets/{budget}/import', [\App\Http\Controllers\Portal\BudgetController::class, 'import'])->name('finance.budgets.import');

    // ── Phase 3: Finance – Supplier Invoices ──────────────────────────────────
    Route::post('/finance/invoices', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeInvoice'])->name('finance.invoices.store');
    Route::get('/finance/invoices/{invoice}/attachment', [\App\Http\Controllers\Portal\DepartmentController::class, 'downloadInvoiceAttachment'])->name('finance.invoices.attachment');
    Route::post('/finance/invoices/{invoice}/action/{action}', [\App\Http\Controllers\Portal\DepartmentController::class, 'actionInvoice'])->name('finance.invoices.action');
    Route::post('/finance/invoices/{invoice}/resubmit', [\App\Http\Controllers\Portal\DepartmentController::class, 'resubmitInvoice'])->name('finance.invoices.resubmit');

    // ── Phase 3: Brands – POSM Ledger ─────────────────────────────────────────
    Route::post('/brands/posm', [\App\Http\Controllers\Portal\DepartmentController::class, 'storePosmEntry'])->name('brands.posm.store');
    Route::delete('/brands/posm/{entry}', [\App\Http\Controllers\Portal\DepartmentController::class, 'destroyPosmEntry'])->name('brands.posm.destroy');

    // ── Phase 3: Creative – Proofing Comments ─────────────────────────────────
    Route::post('/creative/briefs/{task}/comments', [\App\Http\Controllers\Portal\DepartmentController::class, 'storeCreativeComment'])->name('creative.comments.store');

    // ── CVO Command Centre ─────────────────────────────────────────────────────
    Route::get('/cvo', [\App\Http\Controllers\Portal\CVOController::class, 'dashboard'])->name('cvo');

    // ── CVO Financial Approval Actions ────────────────────────────────────────
    Route::post('/finance/claims/{claim}/cvo/{action}',   [\App\Http\Controllers\Portal\DepartmentController::class, 'cvoActionClaim'])->name('finance.claims.cvo.action');
    Route::post('/finance/budgets/{budget}/cvo/{action}', [\App\Http\Controllers\Portal\BudgetController::class, 'cvoAction'])->name('finance.budgets.cvo.action');
    Route::post('/finance/invoices/{invoice}/cvo/{action}', [\App\Http\Controllers\Portal\DepartmentController::class, 'cvoActionInvoice'])->name('finance.invoices.cvo.action');

    // Portal Surveys Management
    Route::get('/surveys', [\App\Http\Controllers\Portal\SurveyController::class, 'index'])->name('surveys.index');
    Route::get('/surveys/create', [\App\Http\Controllers\Portal\SurveyController::class, 'create'])->name('surveys.create');
    Route::post('/surveys', [\App\Http\Controllers\Portal\SurveyController::class, 'store'])->name('surveys.store');
    Route::get('/surveys/{survey}', [\App\Http\Controllers\Portal\SurveyController::class, 'show'])->name('surveys.show');
    Route::get('/surveys/{survey}/edit', [\App\Http\Controllers\Portal\SurveyController::class, 'edit'])->name('surveys.edit');
    Route::put('/surveys/{survey}', [\App\Http\Controllers\Portal\SurveyController::class, 'update'])->name('surveys.update');
    Route::delete('/surveys/{survey}', [\App\Http\Controllers\Portal\SurveyController::class, 'destroy'])->name('surveys.destroy');
    Route::get('/surveys/{survey}/export', [\App\Http\Controllers\Portal\SurveyController::class, 'export'])->name('surveys.export');
    Route::get('/surveys/{survey}/broadcast', [\App\Http\Controllers\Portal\SurveyController::class, 'broadcastCompose'])->name('surveys.broadcast');
    Route::post('/surveys/{survey}/broadcast', [\App\Http\Controllers\Portal\SurveyController::class, 'broadcastSend'])->name('surveys.broadcast.send');

    // Merchandiser Admin Panel
    Route::prefix('merchandisers-admin')->name('merchandisers-admin.')->group(function () {
        Route::redirect('/tracking', '/merchandisers/admin/hub/tracking')->name('tracking');
        
        Route::redirect('/pairings', '/merchandisers/admin/hub/merchandisers')->name('pairings');
        Route::post('/pairings/{user}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'pair'])->name('pair');
        
        Route::redirect('/skus', '/merchandisers/admin/hub/skus')->name('skus');
        Route::post('/skus', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'storeSku'])->name('skus.store');
        Route::put('/skus/{sku}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'updateSku'])->name('skus.update');
        Route::delete('/skus/{sku}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'destroySku'])->name('skus.destroy');

        
        Route::redirect('/kds', '/merchandisers/admin/hub/kds')->name('kds');
        Route::post('/kds', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'storeKd'])->name('kds.store');
        Route::put('/kds/{kd}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'updateKd'])->name('kds.update');
        Route::get('/kds/{kd}/check-dependents', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'checkKdDependents'])->name('kds.check-dependents');
        Route::delete('/kds/{kd}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'destroyKd'])->name('kds.destroy');
        
        Route::redirect('/outlets', '/merchandisers/admin/hub/kds')->name('outlets');
        Route::post('/outlets', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'storeOutlet'])->name('outlets.store');
        Route::put('/outlets/{outlet}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'updateOutlet'])->name('outlets.update');
        Route::delete('/outlets/{outlet}', [\App\Http\Controllers\Portal\MerchandiserAdminController::class, 'destroyOutlet'])->name('outlets.destroy');
    });

});



Route::middleware(['auth', 'active', 'role:admin,super_admin', 'clocked_in'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        Route::post('/users/{user}/permissions', [AdminUserController::class, 'updatePermissions'])->name('users.permissions');
        Route::post('/users/{user}/department', [AdminUserController::class, 'updateDepartment'])->name('users.department');
        Route::post('/users/{user}/id-expiry', [AdminUserController::class, 'updateIdExpiry'])->name('users.id-expiry');
        Route::post('/users/{user}/reset', [AdminUserController::class, 'resetCredentials'])->name('users.reset');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::post('/users/{user}/approve-profile', [AdminUserController::class, 'approveProfileChange'])->name('users.approve-profile');
        Route::post('/users/{user}/reject-profile', [AdminUserController::class, 'rejectProfileChange'])->name('users.reject-profile');

        Route::get('/content', [AdminContentController::class, 'index'])->name('content');
        Route::post('/content', [AdminContentController::class, 'update'])->name('content.update');

        Route::get('/tasks', [AdminTaskController::class, 'index'])->name('tasks');
        Route::post('/tasks', [AdminTaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}/edit', [AdminTaskController::class, 'edit'])->name('tasks.edit');
        Route::patch('/tasks/{task}', [AdminTaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [AdminTaskController::class, 'destroy'])->name('tasks.destroy');

        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');

        Route::get('/updates', [AdminUpdateController::class, 'index'])->name('updates');
        Route::get('/updates/{update}/edit', [AdminUpdateController::class, 'edit'])->name('updates.edit');
        Route::patch('/updates/{update}', [AdminUpdateController::class, 'update'])->name('updates.update');
        Route::delete('/updates/{update}', [AdminUpdateController::class, 'destroy'])->name('updates.destroy');

        Route::get('/events', [AdminEventController::class, 'index'])->name('events');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
        Route::patch('/events/{event}', [AdminEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->name('events.destroy');

        // Portfolio
        Route::get('/portfolio', [AdminPortfolioController::class, 'index'])->name('portfolio');
        Route::post('/portfolio', [AdminPortfolioController::class, 'store'])->name('portfolio.store');
        Route::get('/portfolio/{album}', [AdminPortfolioController::class, 'edit'])->name('portfolio.edit');
        Route::patch('/portfolio/{album}', [AdminPortfolioController::class, 'update'])->name('portfolio.update');
        Route::delete('/portfolio/{album}', [AdminPortfolioController::class, 'destroy'])->name('portfolio.destroy');
        Route::post('/portfolio/{album}/upload', [AdminPortfolioController::class, 'upload'])->name('portfolio.upload');
        Route::delete('/portfolio/image/{image}', [AdminPortfolioController::class, 'destroyImage'])->name('portfolio.image.destroy');
        Route::get('/portfolio-payments', [\App\Http\Controllers\Admin\PortfolioPaymentController::class, 'index'])->name('portfolio-payments');

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

        // Brands
        Route::get('/brands', [\App\Http\Controllers\Admin\BrandController::class, 'index'])->name('brands');
        Route::post('/brands', [\App\Http\Controllers\Admin\BrandController::class, 'store'])->name('brands.store');
        Route::delete('/brands/{brand}', [\App\Http\Controllers\Admin\BrandController::class, 'destroy'])->name('brands.destroy');
    });

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Merchandisers sub-portal client routes
Route::prefix('merchandisers')->name('merchandisers.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'gateway'])->name('portal');
    Route::get('/login', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'login'])->name('login.store');
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])->defaults('portal', 'merchandisers')->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])->defaults('portal', 'merchandisers')->name('password.email');
    Route::get('/register', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'showRegister'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'register'])->name('register.store');
    
    Route::middleware(['auth', 'active', 'identity_docs'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'dashboard'])->name('dashboard');
        Route::post('/outlets', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'storeOutlet'])->name('outlets.store');
        Route::patch('/outlets/{outlet}/coordinates', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'updateOutletCoordinates'])->name('outlets.coordinates.update');
        Route::post('/pcm-clock-in', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'clockInPcm'])->name('pcm-clock-in');
        Route::post('/clock-in', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'clockOut'])->name('clock-out');
        Route::get('/visit/{outlet}', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'visit'])->name('visit');
        Route::post('/visit/{outlet}/ai-detect', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'analyzeVisitShelf'])->name('visit.ai-detect');
        Route::get('/visit/{outlet}/ai-detect/{token}', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'aiDetectionStatus'])->name('visit.ai-detect.status');
        Route::post('/visit/{outlet}', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'storeVisit'])->name('visit.store');
        Route::post('/location-ping', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'locationPing'])->name('location-ping');
        Route::post('/logout', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'logout'])->name('logout');
        
        // HRM & Financial sub-portal submissions
        Route::patch('/profile/update', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'updateProfile'])->name('profile.update');
        Route::post('/leaves', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitLeave'])->name('leaves.store');
        Route::post('/claims', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitClaim'])->name('claims.store');
        Route::post('/loans', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitLoan'])->name('loans.store');
        Route::post('/appraisals', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitAppraisal'])->name('appraisals.store');
        Route::post('/inventory', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitInventory'])->name('inventory.store');
        Route::post('/surveys', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'storeSurvey'])->name('surveys.store');
        Route::post('/surveys/{survey}/respond', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitSurveyResponse'])->name('surveys.respond');
        Route::post('/google-forms/{form}/complete', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'completeGoogleForm'])->name('google-forms.complete');
        Route::get('/native-forms/{form}', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'showNativeForm'])->name('native-forms.show');
        Route::post('/native-forms/{form}', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'submitNativeForm'])->name('native-forms.submit');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Merchandiser\MerchandiserController::class, 'markNotificationRead'])->name('notifications.read');

        // ── Merchandiser Admin Hub (admin/super_admin only) ─────────────────────
        Route::middleware(['role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'dashboard'])->name('dashboard');
            Route::get('/hub/{adminTab}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'dashboard'])
                ->whereIn('adminTab', ['overview', 'tracking', 'kds', 'routes', 'skus', 'forms', 'merchandisers', 'supervisors', 'assets', 'notifications', 'settings', 'gallery', 'executive', 'category-kpi', 'user-performance', 'price-promo'])
                ->name('tab');
            Route::get('/merchandisers', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'merchandisers'])->name('merchandisers');
            Route::post('/merchandisers/{user}/suspend', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'suspendMerchandiser'])->name('merchandisers.suspend');
            Route::post('/merchandisers/{user}/activate', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'activateMerchandiser'])->name('merchandisers.activate');
            Route::post('/merchandisers/{user}/reassign', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'reassignMerchandiser'])->name('merchandisers.reassign');
            Route::post('/merchandisers/{user}/promote-supervisor', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'promoteSupervisor'])->name('merchandisers.promote-supervisor');
            Route::post('/supervisors/{user}/demote', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'demoteSupervisor'])->name('supervisors.demote');
            Route::post('/supervisors/assign', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'assignSupervisor'])->name('supervisors.assign');
            Route::post('/supervisors/pjp-clock-in', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'clockInPjp'])->name('supervisors.pjp-clock-in');
            Route::post('/pjps', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storePjp'])->name('pjps.store');
            Route::post('/pjps/{pjp}/forward', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'forwardPjp'])->name('pjps.forward');
            Route::post('/pjps/{pjp}/activate', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'activatePjp'])->name('pjps.activate');
            Route::post('/compliance-queries', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'sendComplianceQuery'])->name('compliance-queries.store');
            Route::post('/kds', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storeKd'])->name('kds.store');
            Route::put('/kds/{kd}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'updateKd'])->name('kds.update');
            Route::delete('/kds/{kd}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'destroyKd'])->name('kds.destroy');
            Route::post('/outlets', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storeOutlet'])->name('outlets.store');
            Route::put('/outlets/{outlet}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'updateOutlet'])->name('outlets.update');
            Route::delete('/outlets/{outlet}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'destroyOutlet'])->name('outlets.destroy');
            Route::post('/outlet-assignments', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'assignOutlets'])->name('outlet-assignments.store');
            Route::post('/outlet-assignments/registered', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'assignRegisteredOutlets'])->name('outlet-assignments.registered');
            Route::delete('/outlets/{outlet}/assignments/{user}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'unassignOutlet'])->name('outlet-assignments.destroy');
            Route::post('/skus', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storeSku'])->name('skus.store');
            Route::put('/skus/{sku}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'updateSku'])->name('skus.update');
            Route::delete('/skus/{sku}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'destroySku'])->name('skus.destroy');
            Route::post('/category-targets', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storeCategoryTarget'])->name('category-targets.store');
            Route::post('/pairings/{user}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'pairMerchandiser'])->name('pairings.pair');
            Route::post('/routes/generate', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'generateRoutes'])->name('routes.generate');
            Route::post('/merchandisers/{user}/route-settings', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'updateRouteSettings'])->name('merchandisers.route-settings');
            Route::post('/google-forms', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storeGoogleForm'])->name('google-forms.store');
            Route::delete('/google-forms/{form}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'destroyGoogleForm'])->name('google-forms.destroy');
            Route::post('/planograms', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'storePlanogram'])->name('planograms.store');
            Route::delete('/planograms/{planogram}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'destroyPlanogram'])->name('planograms.destroy');
            Route::post('/leaves/{leave}/approve', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'approveLeave'])->name('leaves.approve');
            Route::post('/leaves/{leave}/reject', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'rejectLeave'])->name('leaves.reject');
            Route::post('/claims/{claim}/approve', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'approveClaim'])->name('claims.approve');
            Route::post('/claims/{claim}/reject', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'rejectClaim'])->name('claims.reject');
            Route::post('/loans/{loan}/approve', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'approveLoan'])->name('loans.approve');
            Route::post('/loans/{loan}/reject', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'rejectLoan'])->name('loans.reject');
            Route::post('/payroll/{user}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'setPayroll'])->name('payroll.set');
            Route::post('/clock-settings', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'updateClockSettings'])->name('clock-settings.update');
            Route::post('/notifications/broadcast', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'broadcastNotification'])->name('notifications.broadcast');
            // Share & Export
            Route::post('/share', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'generateShareLink'])->name('share.generate');
            Route::post('/share/{report}/revoke', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'revokeShareLink'])->name('share.revoke');
            Route::get('/export/{type}', [\App\Http\Controllers\Merchandiser\MerchandiserAdminHubController::class, 'exportData'])->name('export');
        });
    });
});

// Public shareable report link (no auth required)
Route::get('/merchandisers/report/{token}', [\App\Http\Controllers\Merchandiser\MerchandiserReportController::class, 'show'])->name('merchandisers.report.view');

require __DIR__.'/auth.php';
