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
        $hr->entries()->create(['section'=>'recruitment','data'=>['metric'=>'Vacancies advertised','position'=>'PRIVATE ROLE NOTE','number'=>6,'notes'=>'PRIVATE CV LINK']]);
        $hr->entries()->create(['section'=>'attendance_leave','data'=>['metric'=>'Lateness','staff_name'=>'PRIVATE ATTENDANCE NAME','number'=>2,'notes'=>'PRIVATE MEDICAL NOTE']]);
        $hr->entries()->create(['section'=>'training','data'=>['training_title'=>'PRIVATE TRAINING DETAIL','trainings_delivered'=>2,'employees_attended'=>10,'required_completions'=>12,'completed'=>9]]);
        $hr->entries()->create(['section' => 'employee_relations', 'data' => ['staff_name' => 'PRIVATE PERSON', 'number' => 5]]);
        $admin = AdminWeeklyReport::create(['period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'prepared_by' => $u->id, 'approved_at' => now()]);
        foreach (['fuel_litres' => 100, 'distance_km' => 500, 'fuel_cost' => 123456.78] as $code => $value) {
            $admin->lines()->create(['section' => 'fleet', 'code' => $code, 'numeric_value' => $value, 'value_state' => 'reported', 'issue' => 'PRIVATE ISSUE']);
        }
        $service = app(SharedPeopleReporting::class);
        $rows = $service->build('2026-10-19', '2026-10-25');
        $this->assertEquals(90, collect($rows['hr'])->firstWhere('metric', 'Training Completion Rate')['actual']);
        $this->assertEquals(20, collect($rows['administrator'])->firstWhere('metric', 'Fuel Efficiency')['actual']);
        $this->assertEquals(6, collect($rows['hr'])->firstWhere('metric','Vacancies advertised')['actual']);
        $this->assertEquals(2, collect($rows['hr'])->firstWhere('metric','Lateness')['actual']);
        $this->assertEquals(9, collect($rows['hr'])->firstWhere('metric','Training Completions')['actual']);
        $json = json_encode($rows);
        foreach (['123456.78', 'PRIVATE HR NOTE', 'PRIVATE PERSON', 'PRIVATE ISSUE', 'PRIVATE ROLE NOTE', 'PRIVATE CV LINK', 'PRIVATE ATTENDANCE NAME', 'PRIVATE MEDICAL NOTE', 'PRIVATE TRAINING DETAIL'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $hr->update(['approved_at' => null]);
        $admin->update(['approved_at' => null]);
        $this->assertEquals(['hr' => [], 'administrator' => []], $service->build('2026-10-19', '2026-10-25'));
    }
    public function test_comparisons_use_approved_previous_period_and_keep_sensitive_details_private(): void
    {
        $user=User::factory()->create();
        foreach ([['2026-10-12','2026-10-18',30],['2026-10-19','2026-10-25',33]] as [$start,$end,$headcount]) {
            $report=HrPeriodicReport::create(['frequency'=>'weekly','period_start'=>$start,'period_end'=>$end,'prepared_on'=>$start,'section_states'=>[],'prepared_by'=>$user->id,'approved_at'=>now()]);
            $report->entries()->create(['section'=>'metrics','data'=>['metric'=>'Headcount','actual'=>$headcount]]);
            $report->entries()->create(['section'=>'priorities','data'=>['metric'=>'Actions to complete','details'=>'SECRET DISCIPLINARY CASE','status'=>'Blocked','owner_id'=>$user->id]]);
            $report->entries()->create(['section'=>'performance','data'=>['metric'=>'Performance Appraisal period','notes'=>'SECRET SALARY OFFER']]);
        }
        $admin=AdminWeeklyReport::create(['period_start'=>'2026-10-19','period_end'=>'2026-10-25','prepared_by'=>$user->id,'approved_at'=>now()]);
        $admin->lines()->create(['section'=>'office','code'=>'security','value_state'=>'reported','text_value'=>'SECRET ACCESS CODE','issue'=>'SECRET CCTV VULNERABILITY','owner_id'=>$user->id,'due_date'=>'2026-10-25']);
        $admin->lines()->create(['section'=>'assets','code'=>'damaged_assets','value_state'=>'not_reported','numeric_value'=>999]);
        $rows=app(SharedPeopleReporting::class)->build('2026-10-19','2026-10-25');
        $headcount=collect($rows['hr'])->firstWhere('metric','Headcount');
        $this->assertEquals(30,$headcount['previous']);
        $this->assertEquals(3,$headcount['change']);
        $this->assertEquals(1,collect($rows['hr'])->firstWhere('metric','Blocked Priorities')['actual']);
        $this->assertEquals(1,collect($rows['administrator'])->firstWhere('metric','Actions Assigned')['actual']);
        $this->assertNull(collect($rows['administrator'])->firstWhere('metric','Damaged assets')['actual']);
        $this->assertStringNotContainsString('SECRET',json_encode($rows));
        $this->assertStringNotContainsString('999',json_encode($rows));
    }

}
