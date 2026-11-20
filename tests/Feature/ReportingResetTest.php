<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attendance;
use App\Models\CrmPipelineProject;
use App\Models\PhoneDirectory;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\ClientRelationsCentre;
use App\Services\ReportingReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 16:00:00'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
    }

    private function actor(): User
    {
        return User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'super_admin']);
    }

    private function seedReport(User $u): array
    {
        $task = Task::create(['title' => 'Original daily task', 'assigned_to' => $u->id, 'assigned_by' => $u->id, 'department' => 'creatives', 'status' => 'In Progress', 'progress' => 50, 'due_on' => '2026-11-20']);
        $weekly = WeeklyConsolidatedItem::create(['department' => 'client_relations', 'campaign_name' => 'Old weekly reporting', 'client_name' => 'Original Client', 'lead_staff_id' => $u->id, 'created_by' => $u->id, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'status' => 'In Progress', 'deliverables' => 'Old reported outcome', 'report_series' => 'same-report-series']);
        $project = CrmPipelineProject::create(['client_name' => 'Original Client', 'project_name' => 'Old pipeline project', 'stage' => 'project_ongoing', 'owner_id' => $u->id, 'created_by' => $u->id, 'updated_by' => $u->id, 'source_period_confirmed' => true, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22']);

        return compact('task', 'weekly', 'project');
    }

    public function test_archive_hides_reporting_keeps_exact_operational_records_and_retains_account_names_only(): void
    {
        $u = $this->actor();
        $report = $this->seedReport($u);
        Asset::create(['name' => 'Retained warehouse asset', 'asset_tag' => 'SAFE-ASSET', 'type' => 'Equipment', 'condition' => 'Good', 'status' => 'available', 'is_warehouse_tracked' => true, 'warehouse_quantity' => 9]);
        PhoneDirectory::create(['name' => 'Retained vendor', 'category' => 'vendor', 'is_vendor' => true, 'phone' => '0200000000']);
        Attendance::create(['user_id' => $u->id, 'clock_in_at' => '2026-11-20 08:30:00', 'daily_objective' => 'Retained attendance objective', 'status' => 'On Time']);
        $service = app(ReportingReset::class);
        $protected = $service->protectedState();
        $raw = (array) DB::table('tasks')->find($report['task']->id);
        $batch = $service->archive($u, 'Test approved reporting reset', 'private-backup.sqlite');
        $this->assertSame($protected, $service->protectedState());
        $this->assertSame(0, Task::count());
        $this->assertSame(0, WeeklyConsolidatedItem::count());
        $this->assertSame(0, CrmPipelineProject::count());
        $archived = (array) DB::table('tasks')->find($report['task']->id);
        $this->assertSame($batch['id'], $archived['reporting_archive_batch_id']);
        unset($raw['reporting_archive_batch_id'],$archived['reporting_archive_batch_id']);
        $this->assertSame($raw, $archived);
        $this->assertSame(1, Task::withReportingArchive()->count());
        $this->assertSame(1, DB::table('crm_account_directory')->count());
        $data = app(ClientRelationsCentre::class)->build(['period' => 'year', 'date' => '2026-11-20', 'account_scope' => 'all'], $u);
        $this->assertTrue(collect($data['client_options'])->contains('name', 'Original Client'));
        $this->assertFalse(collect($data['client_options'])->contains('engaged', true));
    }

    public function test_fresh_entries_can_use_the_same_reporting_scope_without_restoring_old_values(): void
    {
        $u = $this->actor();
        $reports = $this->seedReport($u);
        app(ReportingReset::class)->archive($u, 'New reporting cycle', 'backup.sqlite');
        $copy = WeeklyConsolidatedItem::create(['department' => 'client_relations', 'campaign_name' => 'Fresh weekly reporting', 'deliverables' => 'New reported work', 'client_name' => 'Original Client', 'lead_staff_id' => $u->id, 'created_by' => $u->id, 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'status' => 'Planned', 'report_series' => 'same-report-series']);
        $this->assertSame(1, WeeklyConsolidatedItem::count());
        $this->assertNotSame($reports['weekly']->id, $copy->id);
        $this->assertSame(2, DB::table('weekly_consolidated_items')->count());
        $this->assertSame(0, CrmPipelineProject::count());
    }

    public function test_archived_data_is_read_only_and_downloadable_only_by_existing_audit_roles(): void
    {
        $u = $this->actor();
        $reports = $this->seedReport($u);
        $batch = app(ReportingReset::class)->archive($u, 'Approved reset', 'backup.sqlite');
        $this->actingAs($u)->get(route('portal.reporting-archives'))->assertOk()->assertSee('Approved reset')->assertSee('Excel');
        $this->get(route('portal.reporting-archives.download', [$batch['id'], 'tasks', 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get(route('portal.reporting-archives.download', [$batch['id'], 'tasks', 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('portal.reporting-archives.download', [$batch['id'], 'users', 'xlsx']))->assertNotFound();
        $this->get(route('portal.tasks.show', $reports['task']->id))->assertNotFound();
        $staff = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'access_role' => 'staff']);
        $this->actingAs($staff)->get(route('portal.reporting-archives'))->assertForbidden();
        $this->get(route('portal.reporting-archives.download', [$batch['id'], 'tasks', 'pdf']))->assertForbidden();
    }

    public function test_automatic_backfill_does_not_recreate_old_mega_table_tasks_or_change_retained_attendance(): void
    {
        $u = $this->actor();
        \App\Services\AutoClockService::handleForUser($u, today());
        $service = app(ReportingReset::class);
        $service->archive($u, 'Fresh start', 'backup.sqlite');
        $attendance = DB::table('attendance')->get()->toJson();
        $count = DB::table('tasks')->count();
        \App\Services\AutoClockService::backfillCurrentMonthForUser($u);
        $this->assertSame(0, Task::count());
        $this->assertSame($count, DB::table('tasks')->count());
        $this->assertSame($attendance, DB::table('attendance')->get()->toJson());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 10:00:00'));
        \App\Services\AutoClockService::handleForUser($u, today());
        $this->assertSame(1, Task::count());
    }

    public function test_immutable_published_reports_archive_without_mutation_and_allow_a_fresh_reporting_cycle(): void
    {
        $u = $this->actor();
        $data = ['user_id' => $u->id, 'frequency' => 'weekly', 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'payload' => json_encode(['original' => 'Published result']), 'sha256' => hash('sha256', 'original'), 'created_at' => now()];
        $id = DB::table('performance_report_packs')->insertGetId($data);
        $original = (array) DB::table('performance_report_packs')->find($id);
        $service = app(ReportingReset::class);
        $batch = $service->archive($u, 'Immutable report reset', 'backup.sqlite');
        $this->assertSame($original, (array) DB::table('performance_report_packs')->find($id));
        $this->assertSame(0, \App\Models\PerformanceReportPack::count());
        $this->actingAs($u)->get(route('portal.reporting-archives.download', [$batch['id'], 'performance_report_packs', 'xlsx']))->assertOk();
        DB::table('performance_report_packs')->insert($data);
        $this->assertSame(1, \App\Models\PerformanceReportPack::count());
        $this->assertSame(2, DB::table('performance_report_packs')->count());
        try {
            DB::table('performance_report_packs')->where('id', $id)->update(['sha256' => 'modified']);
            $this->fail('Published audit contents must remain immutable.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('immutable', $error->getMessage());
        }
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('performance_report_packs')->insert($data);
    }

    public function test_fresh_creative_weekly_entries_can_link_after_the_archive_without_reimporting_old_work(): void
    {
        $u = $this->actor();
        $this->seedReport($u);
        app(ReportingReset::class)->archive($u, 'Fresh creative reporting', 'backup.sqlite');
        $importer = app(\App\Services\Creative\CreativeLegacyImport::class);
        $this->assertSame(0, $importer->run());
        WeeklyConsolidatedItem::create(['department' => 'creatives', 'campaign_name' => 'Fresh creative weekly brief', 'lead_staff_id' => $u->id, 'created_by' => $u->id, 'deliverables' => 'New work after reset', 'week_start' => '2026-11-16', 'week_end' => '2026-11-22', 'status' => 'Planned']);
        $this->assertSame(1, $importer->run());
        $this->assertSame('Fresh creative weekly brief', \App\Models\CreativeRequest::sole()->title);
        $this->assertSame(0, $importer->run());
    }

    public function test_account_directory_excludes_rollup_targets_and_unconfirmed_import_variants(): void
    {
        $u = $this->actor();
        $records = $this->seedReport($u);
        $variant = $records['project']->replicate();
        $variant->client_name = 'Unconfirmed historical spelling';
        $variant->source_period_confirmed = false;
        $variant->save();
        \App\Models\CrmAccountTarget::create(['reference' => 'rollup-test', 'client_name' => 'All accounts · 2026 staff pipeline', 'owner_id' => $u->id, 'frequency' => 'yearly', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'target_amount' => 100]);
        $reset = app(ReportingReset::class);
        $this->assertSame(['Original Client'], $reset->accounts()->all());
        $batch = $reset->archive($u, 'Actual account names only', 'backup.sqlite');
        $this->assertSame(['Original Client'], $reset->accounts(false, $batch['id'])->all());
        $this->assertSame(1, DB::table('crm_account_directory')->count());
    }

    public function test_restore_is_exact_and_old_reporting_cards_are_absent_from_all_department_views(): void
    {
        $u = $this->actor();
        $this->seedReport($u);
        $service = app(ReportingReset::class);
        $batch = $service->archive($u, 'Reset', 'backup.sqlite');
        foreach (['client_relations', 'operations_projects', 'hr_admin', 'finance', 'creatives', 'brands_marketing'] as $department) {
            $this->actingAs($u)->get(route('dashboard', ['weekly_department' => $department, 'report_date' => '2026-11-16', 'report_period' => 'week']))->assertOk()->assertDontSee('Reporting History &amp; Review Status', false)->assertDontSee('Earlier Saved Weekly Reports')->assertDontSee('Weekly Team Updates (')->assertDontSee('data-saved-weekly=', false)->assertDontSee('Old pipeline project');
        }
        $service->restore($u, $batch['id']);
        $this->assertSame(1, WeeklyConsolidatedItem::count());
        $this->assertSame(1, CrmPipelineProject::count());
        $this->assertSame(1, Task::where('title', 'Original daily task')->count());
    }
}
