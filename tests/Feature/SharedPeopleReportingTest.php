<?php

namespace Tests\Feature;

use App\Models\AdminWeeklyReport;
use App\Models\HrPeriodicReport;
use App\Models\User;
use App\Services\SharedPeopleReporting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedPeopleReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_public_totals_and_derived_efficiency_are_shared(): void
    {
        $u = User::factory()->create();
        $hr = HrPeriodicReport::create(['frequency' => 'weekly', 'period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'prepared_on' => '2026-10-20', 'prepared_by' => $u->id, 'section_states' => [], 'approved_at' => now()]);
        $hr->entries()->create(['section' => 'metrics', 'data' => ['metric' => 'Headcount', 'actual' => 33, 'notes' => 'PRIVATE HR NOTE']]);
        $hr->entries()->create(['section' => 'metrics', 'data' => ['metric' => 'Training completion rate', 'actual' => 99, 'numerator' => 9, 'denominator' => 10]]);
        $hr->entries()->create(['section' => 'employee_relations', 'data' => ['staff_name' => 'PRIVATE PERSON', 'number' => 5]]);
        $admin = AdminWeeklyReport::create(['period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'prepared_by' => $u->id, 'approved_at' => now()]);
        foreach (['fuel_litres' => 100, 'distance_km' => 500, 'fuel_cost' => 123456.78] as $code => $value) {
            $admin->lines()->create(['section' => 'fleet', 'code' => $code, 'numeric_value' => $value, 'value_state' => 'reported', 'issue' => 'PRIVATE ISSUE']);
        }
        $service = app(SharedPeopleReporting::class);
        $rows = $service->build('2026-10-19', '2026-10-25');
        $this->assertEquals(90, collect($rows['hr'])->firstWhere('metric', 'Training Completion Rate')['actual']);
        $this->assertEquals(20, collect($rows['administrator'])->firstWhere('metric', 'Fuel Efficiency')['actual']);
        $json = json_encode($rows);
        foreach (['123456.78', 'PRIVATE HR NOTE', 'PRIVATE PERSON', 'PRIVATE ISSUE'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $hr->update(['approved_at' => null]);
        $admin->update(['approved_at' => null]);
        $this->assertEquals(['hr' => [], 'administrator' => []], $service->build('2026-10-19', '2026-10-25'));
    }
}
