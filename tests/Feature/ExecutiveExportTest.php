<?php

namespace Tests\Feature;

use App\Models\CrmPipelineProject;
use App\Models\PerformanceRegister;
use App\Models\ProjectExecutionControl;
use App\Models\User;
use App\Services\ExecutiveExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExecutiveExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 12:00:00'));
    }

    private function executive(): User
    {
        return User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'super_admin']);
    }

    private function input(array $changes = []): array
    {
        return array_replace(['format' => 'xlsx', 'scope' => 'custom', 'dates' => 'range', 'date_from' => '2026-11-16', 'date_to' => '2026-11-22', 'sections' => ['projects', 'calendar'], 'people_scope' => 'all', 'departments_scope' => 'all', 'projects_scope' => 'all', 'context' => ['exec_period' => 'week', 'exec_date' => '2026-11-20']], $changes);
    }

    private function project(User $owner, string $title = 'Selected project', string $week = '2026-11-16'): CrmPipelineProject
    {
        return CrmPipelineProject::create(['owner_id' => $owner->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, 'client_name' => 'Client', 'project_name' => $title, 'stage' => 'project_ongoing', 'source_period_confirmed' => true, 'week_start' => $week, 'week_end' => \Illuminate\Support\Carbon::parse($week)->addDays(6)->toDateString()]);
    }

    private function build(User $user, array $input): array
    {
        $this->actingAs($user);
        $r = Request::create('/portal/executive/export/preview', 'POST', $input);
        $r->setUserResolver(fn () => $user);
        $r->setLaravelSession(app('session.store'));

        return app(ExecutiveExport::class)->build($r, $input);
    }

    private function workbookXml(string $bytes): string
    {
        $p = tempnam(sys_get_temp_dir(), 'export-test-');
        file_put_contents($p, $bytes);
        $z = new \ZipArchive;
        $this->assertTrue($z->open($p));
        $xml = '';
        foreach (range(0, $z->numFiles - 1) as $index) {
            $n = $z->getNameIndex($index);
            if (str_starts_with($n, 'xl/worksheets/')) {
                $xml .= $z->getFromName($n);
            }
        }$z->close();
        unlink($p);

        return $xml;
    }

    public function test_builder_and_preview_provide_selection_for_all_sections_and_formats(): void
    {
        $u = $this->executive();
        $this->project($u);
        $this->actingAs($u)->get(route('portal.executive.export'))->assertOk()->assertSee('Select everything')->assertSee('All recorded dates');
        $this->post(route('portal.executive.export.preview'), $this->input(['sections' => array_keys(ExecutiveExport::SECTIONS)]))->assertOk()->assertSee('Download Excel')->assertSee('Download PDF')->assertSee('Selected project');
    }

    public function test_selected_people_projects_and_records_exclude_other_projects_and_formula_text_is_literal(): void
    {
        $u = $this->executive();
        $a = User::factory()->create(['status' => 'active', 'department' => 'operations_projects']);
        $b = User::factory()->create(['status' => 'active', 'department' => 'creatives']);
        $p = $this->project($a, '=HYPERLINK("https://example.test")');
        $this->project($b, 'Excluded project');
        $input = $this->input(['people_scope' => 'selected', 'people' => [$a->id], 'projects_scope' => 'selected', 'projects' => ['crm:'.$p->id], 'departments_scope' => 'selected', 'departments' => ['operations_projects'], 'sections' => ['projects', 'calendar', 'overview', 'charts']]);
        $report = $this->build($u, $input);
        $this->assertCount(1, $report['command']['activeProjects']);
        $key = 'projects:'.$p->id;
        $response = $this->actingAs($u)->post(route('portal.executive.export.download'), $input + ['selection_mode' => 'selected', 'records' => [$key]])->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xml = $this->workbookXml($response->getContent());
        $this->assertStringContainsString('=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringNotContainsString('Excluded project', $xml);
        $this->assertStringNotContainsString('Brief review', $xml);
    }

    public function test_all_dates_and_complete_source_register_export_do_not_stop_at_visible_page(): void
    {
        $u = $this->executive();
        $owner = User::factory()->create(['status' => 'active', 'department' => 'operations_projects']);
        $this->project($owner, 'Old project', '2025-03-03');
        for ($i = 1; $i <= 12; $i++) {
            PerformanceRegister::create(['department' => 'operations_projects', 'owner_id' => $owner->id, 'submitted_by' => $owner->id, 'approved_by' => $u->id, 'kind' => 'supplier', 'reference' => 'SUP-'.$i, 'title' => 'Saved supplier '.$i, 'status' => 'completed', 'approved_at' => now(), 'period_start' => '2025-03-03', 'period_end' => '2025-03-09', 'data' => ['supplier' => 'Vendor '.$i]]);
        }
        $input = $this->input(['dates' => 'all', 'sections' => ['projects', 'reports'], 'departments_scope' => 'selected', 'departments' => ['operations_projects']]);
        $r = $this->build($u, $input);
        $this->assertTrue($r['records']->contains('title', 'Old project'));
        $this->assertTrue($r['records']->contains(fn ($row) => str_contains($row['title'], 'Saved supplier 1')));
        $this->assertTrue($r['records']->contains(fn ($row) => str_contains($row['title'], 'Saved supplier 12')));
        $this->assertEmpty($r['command']['days']);
    }

    public function test_non_executive_export_is_forbidden_and_traffic_cannot_export_private_finance_or_hr_payloads(): void
    {
        $regular = User::factory()->create(['status' => 'active', 'department' => 'creatives', 'access_role' => 'staff']);
        $this->actingAs($regular)->get(route('portal.executive.export'))->assertForbidden();
        $this->post(route('portal.executive.export.download'), $this->input())->assertForbidden();
        $traffic = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'staff', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        PerformanceRegister::create(['department' => 'finance', 'owner_id' => $traffic->id, 'submitted_by' => $traffic->id, 'kind' => 'invoice', 'reference' => 'FIN-PRIVATE', 'title' => 'Private finance fixture', 'status' => 'completed', 'approved_at' => now(), 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'data' => ['amount' => 99999]]);
        $r = $this->build($traffic, $this->input(['sections' => ['reports']]));
        $this->assertFalse($r['records']->contains(fn ($record) => str_contains(json_encode($record), 'Private finance fixture')));
    }

    public function test_empty_selected_scope_and_stale_record_selection_are_rejected(): void
    {
        $u = $this->executive();
        $this->project($u);
        $this->actingAs($u)->post(route('portal.executive.export.preview'), $this->input(['people_scope' => 'selected', 'people' => []]))->assertSessionHasErrors('people');
        $this->post(route('portal.executive.export.download'), $this->input() + ['selection_mode' => 'selected', 'records' => ['projects:999999']])->assertSessionHasErrors('records');
    }

    public function test_pdf_contains_selected_data_and_no_remote_assets_or_extra_records(): void
    {
        $u = $this->executive();
        $p = $this->project($u, 'PDF selected launch');
        $input = $this->input(['format' => 'pdf', 'sections' => ['projects', 'charts']]);
        $response = $this->actingAs($u)->post(route('portal.executive.export.download'), $input + ['selection_mode' => 'selected', 'records' => ['projects:'.$p->id]])->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(3000, strlen($response->getContent()));
    }

    public function test_private_unit_rates_stay_out_of_traffic_exports_and_no_business_records_are_changed(): void
    {
        $traffic = User::factory()->create(['status' => 'active', 'department' => 'executive', 'access_role' => 'staff', 'permissions_matrix' => [User::EXECUTIVE_ACTIVITY_PERMISSION]]);
        $p = $this->project($traffic);
        ProjectExecutionControl::create(['crm_project_id' => $p->id, 'created_by' => $traffic->id, 'updated_by' => $traffic->id, 'revision' => 1, 'data' => ['assets' => ['rows' => [['id' => 'asset-a', 'description' => 'Private cost asset', 'required' => 1, 'confirmed' => 1, 'unit_rate' => 98765, 'due_at' => '2026-11-20 12:00']]]]]);
        $r = $this->build($traffic, $this->input(['sections' => ['resources', 'projects']]));
        $this->assertStringNotContainsString('98765', json_encode($r['records']));
        $this->assertDatabaseCount('project_execution_controls', 1);
        $this->assertDatabaseCount('performance_outbox', 0);
    }

    public function test_chart_graphics_render_in_pdf_and_all_worksheets_are_valid_xml(): void
    {
        $u = $this->executive();
        $this->project($u, 'Chart export launch');
        $input = $this->input(['sections' => ['overview', 'projects', 'charts', 'team']]);
        $excel = $this->actingAs($u)->post(route('portal.executive.export.download'), $input)->assertOk();
        $xml = $this->workbookXml($excel->getContent());
        $this->assertStringContainsString('Chart export launch', $xml);
        $pdf = $this->post(route('portal.executive.export.download'), array_replace($input, ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        if (getenv('CMIH_EXPORT_VISUAL')) {
            file_put_contents(storage_path('app/private/executive-export-visual.pdf'), $pdf->getContent());
            file_put_contents(storage_path('app/private/executive-export-visual.xlsx'), $excel->getContent());
        }
    }

    public function test_serialized_record_selection_and_linked_activation_sources_follow_the_selected_project(): void
    {
        $u = $this->executive();
        $owner = User::factory()->create(['status' => 'active', 'department' => 'operations_projects']);
        $p = $this->project($owner, 'Shared project');
        $base = ['department' => 'operations_projects', 'owner_id' => $owner->id, 'submitted_by' => $owner->id, 'approved_by' => $u->id, 'approved_at' => now(), 'period_start' => '2026-11-16', 'period_end' => '2026-11-22', 'status' => 'in_progress'];
        $activation = PerformanceRegister::create($base + ['kind' => 'activation', 'reference' => 'LINK-A', 'title' => 'Linked activation', 'data' => ['crm_project_id' => $p->id, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-21']]);
        PerformanceRegister::create($base + ['kind' => 'supplier', 'reference' => 'LINK-S', 'title' => 'Linked supplier record', 'data' => ['activation_id' => $activation->id]]);
        PerformanceRegister::create($base + ['kind' => 'supplier', 'reference' => 'OTHER-S', 'title' => 'Excluded supplier record', 'data' => []]);
        $input = $this->input(['projects_scope' => 'selected', 'projects' => ['crm:'.$p->id], 'sections' => ['reports', 'projects']]);
        $r = $this->build($u, $input);
        $this->assertTrue($r['records']->contains(fn ($row) => str_contains($row['title'], 'Linked supplier record')));
        $this->assertFalse($r['records']->contains(fn ($row) => str_contains($row['title'], 'Excluded supplier record')));
        $response = $this->actingAs($u)->post(route('portal.executive.export.download'), $input + ['selection_mode' => 'selected', 'selected_records' => json_encode(['projects:'.$p->id])])->assertOk();
        $this->assertStringNotContainsString('Linked supplier record', $this->workbookXml($response->getContent()));
    }
}
