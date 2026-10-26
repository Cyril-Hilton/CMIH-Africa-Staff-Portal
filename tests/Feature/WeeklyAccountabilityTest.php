<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\WeeklyAccountabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_zero_targets_are_not_fabricated_scores(): void
    {
        $item = new WeeklyConsolidatedItem(['target_value' => 0, 'actual_value' => 0]);
        $this->assertNull($item->achievementPercent());
        $this->assertSame(0.0, $item->variance());
        $item->target_value = 10;
        $this->assertSame(0.0, $item->achievementPercent());
        $item->actual_value = null;
        $this->assertNull($item->variance());
    }

    public function test_lower_is_better_and_blocked_overdue_are_measured_correctly(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $item = new WeeklyConsolidatedItem(['measurement_direction' => 'lower', 'target_value' => 10,
            'actual_value' => 12, 'status' => 'Blocked', 'week_end' => '2026-10-19']);
        $this->assertSame(80.0, $item->achievementPercent());
        $this->assertTrue($item->isOverdue());
        $item->week_end = '2026-10-20';
        $this->assertFalse($item->isOverdue());
        $item->status = 'Done';
        $item->week_end = '2026-10-19';
        $this->assertFalse($item->isOverdue());
    }

    public function test_staff_summary_excludes_unknown_completion_dates_and_does_not_credit_supporters(): void
    {
        $owner = User::factory()->create();
        $support = User::factory()->create();
        $rows = collect([
            new WeeklyConsolidatedItem(['lead_staff_id' => $owner->id, 'supporting_staff_ids' => [$support->id], 'status' => 'Done', 'week_end' => '2026-10-18', 'completed_at' => '2026-10-18 10:00:00']),
            new WeeklyConsolidatedItem(['lead_staff_id' => $owner->id, 'status' => 'Done', 'week_end' => '2026-10-18']),
            new WeeklyConsolidatedItem(['status' => 'Planned']),
        ]);
        $result = app(WeeklyAccountabilityService::class)->summarize($rows);
        $this->assertSame(100.0, $result['on_time']);
        $this->assertSame(1, $result['dated_completions']);
        $this->assertSame(1, $result['unassigned']);
        $this->assertCount(1, $result['staff']);
        $this->assertSame(2, $result['staff'][0]['total']);
    }

    public function test_department_kpi_definition_and_completion_timestamp_are_preserved(): void
    {
        $manager = User::factory()->create(['access_role' => 'manager', 'status' => 'active', 'department' => 'finance']);
        $payload = ['department' => 'finance', 'week_start' => '2026-10-19', 'week_end' => '2026-10-25',
            'deliverables' => 'Collect agreed receivables', 'status' => 'In Progress', 'kpi_name' => 'Overdue receivables',
            'measurement_direction' => 'higher', 'measurement_unit' => 'jobs', 'target_value' => 1000, 'actual_value' => 1200,
            'corrective_action' => 'Follow up with client', 'action_owner_id' => $manager->id, 'action_due_date' => '2026-10-23',
            'evidence_url' => 'https://example.com/evidence'];
        $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), $payload)->assertSessionHasNoErrors();
        $item = WeeklyConsolidatedItem::firstOrFail();
        $this->assertSame('lower', $item->measurement_direction);
        $this->assertSame('GHS', $item->measurement_unit);
        $this->assertSame(80.0, $item->achievementPercent());
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $url = route('portal.dashboard.weekly-consolidated.update', $item);
        $this->patch($url, ['status' => 'Done'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 12:00:00', $item->fresh()->completed_at->format('Y-m-d H:i:s'));
        $this->travel(1)->days();
        $this->patch($url, ['status' => 'Done'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 12:00:00', $item->fresh()->completed_at->format('Y-m-d H:i:s'));
        $this->patch($url, ['status' => 'In Progress'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->completed_at);
        $this->get(route('dashboard', ['weekly_department' => 'finance']))->assertOk()->assertSee('Delivery accountability')->assertSee('Overdue receivables');
    }

    public function test_each_department_renders_its_own_kpi_dictionary_and_period_totals(): void
    {
        foreach (config('department_kpis') as $department => $definitions) {
            $manager = User::factory()->create(['access_role' => 'manager', 'status' => 'active', 'department' => $department]);
            WeeklyConsolidatedItem::create(['department' => $department, 'week_start' => '2026-10-19',
                'week_end' => '2026-10-25', 'lead_staff_id' => $manager->id, 'deliverables' => 'Period commitment',
                'status' => 'In Progress', 'created_by' => $manager->id, 'updated_by' => $manager->id]);
            WeeklyConsolidatedItem::create(['department' => $department, 'week_start' => '2026-09-01',
                'week_end' => '2026-09-06', 'deliverables' => 'Outside period', 'status' => 'Blocked',
                'created_by' => $manager->id, 'updated_by' => $manager->id]);
            $response = $this->actingAs($manager)->get(route('dashboard', ['weekly_department' => $department,
                'weekly_from' => '2026-10-19', 'weekly_to' => '2026-10-25']));
            if ($department === 'client_relations') {
                $response->assertOk()->assertViewHas('weeklyAccountability', fn ($summary) => $summary['total'] === 1);
            } else {
                $response->assertOk()->assertSee($definitions[0]['name'])->assertViewHas('weeklyAccountability', fn ($summary) => $summary['total'] === 1);
            }
        }
    }

    public function test_period_end_can_be_used_alone_and_inverted_ranges_are_rejected(): void
    {
        $manager = User::factory()->create(['access_role' => 'manager', 'status' => 'active', 'department' => 'finance']);
        $this->actingAs($manager)->get(route('dashboard', ['weekly_department' => 'finance', 'weekly_to' => '2026-10-25']))->assertOk();
        $this->get(route('dashboard', ['weekly_from' => '2026-10-25', 'weekly_to' => '2026-10-19']))->assertSessionHasErrors('weekly_to');
    }

    public function test_unsafe_evidence_and_invalid_metrics_are_rejected(): void
    {
        $manager = User::factory()->create(['access_role' => 'manager', 'status' => 'active', 'department' => 'finance']);
        $this->actingAs($manager)->post(route('portal.dashboard.weekly-consolidated.store'), [
            'department' => 'finance', 'week_start' => '2026-10-19', 'deliverables' => 'Test', 'status' => 'Planned',
            'evidence_url' => 'javascript:alert(1)', 'actual_value' => -1,
        ])->assertSessionHasErrors(['evidence_url', 'actual_value']);
    }
}
