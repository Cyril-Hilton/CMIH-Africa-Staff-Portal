<?php

namespace Tests\Feature;

use App\Models\CrmClientRecord;
use App\Models\CrmPipelineProject;
use App\Models\HrPeriodicReport;
use App\Models\User;
use App\Services\ClientRelationsScorecard;
use App\Services\PerformanceExport;
use App\Services\StaffPerformanceScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPerformanceAndHrReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_uses_exact_10_65_25_weights_without_rescaling_missing_data(): void
    {
        $s = app(StaffPerformanceScore::class);
        $r = $s->combine(80, 90, 60);
        $this->assertSame(81.5, $r['score']);
        $this->assertSame(8.0, $r['components']['punctuality']['points']);
        $this->assertSame(58.5, $r['components']['completion']['points']);
        $this->assertSame(15.0, $r['components']['kpi']['points']);
        $r = $s->combine(100, 100, null);
        $this->assertNull($r['score']);
        $this->assertSame(75.0, $r['subtotal']);
        $this->assertSame(75, $r['coverage_weight']);
        $this->assertFalse($r['complete']);
        $this->assertSame(100.0, $s->combine(100, 200, 200)['score']);
    }

    public function test_hr_report_matches_workbook_sections_and_keeps_missing_rates_unmeasured(): void
    {
        $hr = User::factory()->create(['department' => 'hr_admin', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($hr)->post(route('portal.hr-reporting.create'), ['frequency' => 'weekly', 'period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'prepared_on' => '2026-10-20'])->assertSessionHasNoErrors();
        $report = HrPeriodicReport::first();
        $this->post(route('portal.hr-reporting.entry', $report), ['section' => 'metrics', 'revision' => $report->revision, 'data' => ['metric' => 'Absence rate', 'numerator' => 0, 'denominator' => 0]])->assertSessionHasNoErrors();
        $this->assertNull($report->entries()->first()->data['actual']);
        $this->get(route('portal.hr-reporting.index'))->assertOk()->assertSee('Recruitment and Selection')->assertSee('Training and Development')->assertSee('Compensation &amp; Benefits', false)->assertSee('Employee Relations and Wellbeing')->assertSee('Previous period');
        $this->actingAs(User::factory()->create(['department' => 'creatives', 'access_role' => 'staff', 'status' => 'active']))->get(route('portal.hr-reporting.index'))->assertForbidden();
    }

    public function test_hr_source_population_preserves_existing_rows_and_locks_snapshots(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21));
        $hr = User::factory()->create(['department' => 'hr_admin', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($hr)->post(route('portal.hr-reporting.create'), ['frequency' => 'weekly', 'period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'prepared_on' => '2026-10-21'])->assertSessionHasNoErrors();
        $report = HrPeriodicReport::firstOrFail();
        $report->entries()->create(['section' => 'metrics', 'data' => ['metric' => 'Headcount', 'actual' => 99]]);
        $url = route('portal.hr-reporting.sources', $report);
        $this->post($url, ['revision' => $report->revision])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, $report->entries()->count());
        $this->assertEquals(99, $report->entries()->where('section', 'metrics')->first()->data['actual']);
        $this->post($url, ['revision' => $report->revision])->assertStatus(409);
        $revision = $report->fresh()->revision;
        $this->post($url, ['revision' => $revision])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, $report->entries()->count());
        $this->assertEquals($revision, $report->fresh()->revision);
        $report->refresh()->update(['status' => 'approved']);
        $this->post($url, ['revision' => $revision])->assertStatus(409);
        $report->update(['status' => 'draft', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30']);
        $this->post($url, ['revision' => $revision])->assertStatus(422);
        $this->actingAs(User::factory()->create(['department' => 'creatives', 'access_role' => 'staff', 'status' => 'active']))->post($url, ['revision' => $revision])->assertForbidden();
        $this->travelBack();
    }

    public function test_hr_corrections_recalculate_rates_and_preserve_review_locks(): void
    {
        $hr = User::factory()->create(['department' => 'hr_admin', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($hr)->post(route('portal.hr-reporting.create'), ['frequency' => 'weekly', 'period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'prepared_on' => '2026-10-20'])->assertSessionHasNoErrors();
        $report = HrPeriodicReport::first();
        $this->post(route('portal.hr-reporting.entry', $report), ['section' => 'metrics', 'revision' => $report->revision, 'data' => ['metric' => 'Absence rate', 'numerator' => 10, 'denominator' => 100]])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $report->entries()->first();
        $payload = ['section' => 'metrics', 'revision' => $report->fresh()->revision, 'reason' => 'Corrected attendance source', 'data' => ['metric' => 'Absence rate', 'numerator' => 5, 'denominator' => 100, 'actual' => 99]];
        $this->post(route('portal.hr-reporting.correct', [$report, $entry]), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(5, $entry->fresh()->data['actual']);
        $this->assertSame(1, $report->entries()->count());
        $this->post(route('portal.hr-reporting.correct', [$report, $entry]), $payload)->assertStatus(409);
        $report->refresh()->update(['status' => 'submitted']);
        $payload['revision'] = $report->revision;
        $this->post(route('portal.hr-reporting.correct', [$report, $entry]), $payload)->assertStatus(422);
        $other = User::factory()->create(['department' => 'creatives', 'access_role' => 'staff', 'status' => 'active']);
        $this->actingAs($other)->post(route('portal.hr-reporting.correct', [$report, $entry]), $payload)->assertForbidden();
    }

    public function test_client_scorecard_distinguishes_pipeline_actuals_and_unbilled_exposure(): void
    {
        $owner = User::factory()->create();
        $project = new CrmPipelineProject(['client_name' => 'Example client', 'project_name' => 'Example project', 'owner_id' => $owner->id, 'stage' => 'proposal_sent', 'new_business' => true, 'target_amount' => 100, 'actual_amount' => 50, 'proposed_amount' => 200, 'rate_card_value' => 120, 'po_amount' => 100, 'execution_value' => 80, 'invoiced_amount' => 60, 'po_state' => 'received']);
        $invoice = new CrmClientRecord(['kind' => 'invoice', 'client_name' => 'Example client', 'status' => 'part_paid', 'data' => ['amount' => 60, 'paid_amount' => 20]]);
        $feedback = new CrmClientRecord(['kind' => 'satisfaction', 'client_name' => 'Example client', 'status' => 'recorded', 'data' => ['score' => 4, 'maximum_score' => 5]]);
        $s = app(ClientRelationsScorecard::class)->build(collect([$project]), collect([$invoice, $feedback]));
        $this->assertSame(1, $s['total_clients']);
        $this->assertSame(50.0, $s['achievement']);
        $this->assertEquals(100, $s['weighted_pipeline']);
        $this->assertEquals(20, $s['rate_card_gap']);
        $this->assertEquals(20, $s['execution_gap']);
        $this->assertEquals(40, $s['invoice_balance']);
        $this->assertEquals(80, $s['client_satisfaction']);
    }

    public function test_report_downloads_are_valid_formats_and_do_not_create_excel_formulas(): void
    {
        $service = app(PerformanceExport::class);
        $pdf = $service->pdf([['CMIH', '=1+1'], ['Result', 50]]);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $xlsx = $service->xlsx([['Name', 'Actual'], ['=1+1', 50]]);
        $p = tempnam(sys_get_temp_dir(), 'cmih-test');
        file_put_contents($p, $xlsx);
        $z = new \ZipArchive;
        $this->assertTrue($z->open($p));
        $xml = $z->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('=1+1', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $z->close();
        unlink($p);
    }

    public function test_each_employee_uses_only_their_assigned_kpis_and_their_own_targets(): void
    {
        $owner = User::factory()->create(['department' => 'creatives', 'status' => 'active']);
        $other = User::factory()->create(['department' => 'finance', 'status' => 'active']);
        $reviewer = User::factory()->create(['status' => 'active']);
        $objective = \App\Models\PerformanceObjective::create(['department' => 'creatives', 'title' => 'Measured performance', 'outcome' => 'Approved results', 'owner_id' => $reviewer->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        foreach ([[$owner, 'Artwork delivered', 'jobs', 10, 5], [$other, 'Collections received', 'GHS', 1000, 1000]] as [$person,$name,$unit,$target,$actual]) {
            $kpi = \App\Models\PerformanceKpi::create(['objective_id' => $objective->id, 'department' => $person->department, 'name' => $name, 'definition' => $name, 'formula' => 'Actual / target', 'unit' => $unit, 'direction' => 'higher', 'aggregation' => 'sum', 'frequency' => 'monthly', 'source' => 'Approved source', 'owner_id' => $person->id, 'reviewer_id' => $reviewer->id, 'green_threshold' => 100, 'amber_threshold' => 80, 'escalation_days' => 2, 'required_action' => 'Address gap', 'dimension' => 'target_achievement', 'weight' => 1, 'evidence_required' => false]);
            \App\Models\PerformanceObservation::create(['kpi_id' => $kpi->id, 'owner_id' => $person->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'target' => $target, 'actual' => $actual, 'approved_at' => now(), 'approved_by' => $reviewer->id, 'source_reference' => 'Reviewed source', 'scope_key' => 'owner-'.$person->id]);
        }
        $service = app(StaffPerformanceScore::class);
        $start = \Illuminate\Support\Carbon::parse('2026-10-01');
        $end = \Illuminate\Support\Carbon::parse('2026-10-31');
        $a = $service->user($owner, $start, $end);
        $b = $service->user($other, $start, $end);
        $this->assertEquals(50, $a['kpi_rate']);
        $this->assertEquals(100, $b['kpi_rate']);
        $this->assertEquals(12.5, $a['components']['kpi']['points']);
        $this->assertEquals(25, $b['components']['kpi']['points']);
        $this->assertCount(1, $a['kpis']);
        $this->assertSame('Artwork delivered', $a['kpis'][0]['name']);
    }

    public function test_linked_task_and_commitment_are_scored_as_one_deliverable(): void
    {
        $owner = User::factory()->create(['department' => 'creatives', 'status' => 'active']);
        $manager = User::factory()->create(['department' => 'creatives', 'status' => 'active', 'access_role' => 'manager']);
        $task = \App\Models\Task::create(['title' => 'Final creative output', 'assigned_to' => $owner->id, 'assigned_by' => $manager->id, 'department' => 'creatives', 'status' => 'In Progress', 'priority' => 'Medium', 'created_at' => '2026-10-05 10:00:00']);
        \App\Models\WeeklyConsolidatedItem::create(['source_task_id' => $task->id, 'department' => 'creatives', 'workflow_state' => 'verified', 'week_start' => '2026-10-05', 'week_end' => '2026-10-09', 'campaign_name' => 'Same creative output', 'deliverables' => 'Final artwork', 'lead_staff_id' => $owner->id, 'reviewer_id' => $manager->id, 'status' => 'Done', 'verified_at' => now(), 'quality_score' => 90, 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        $item = \App\Models\WeeklyConsolidatedItem::where('source_task_id', $task->id)->firstOrFail();
        \App\Services\PerformanceWorkflow::record('commitment', $item->id, $manager->id, 'verify', [], $item->getAttributes(), 'Manager sign-off');
        $score = app(StaffPerformanceScore::class)->user($owner, \Illuminate\Support\Carbon::parse('2026-10-01'), \Illuminate\Support\Carbon::parse('2026-10-31'));
        $this->assertSame(1, $score['completion_total']);
        $this->assertSame(1, $score['completion_verified']);
        $this->assertEquals(100, $score['task_rate']);
    }
}
