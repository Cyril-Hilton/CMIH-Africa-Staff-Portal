<?php
namespace Tests\Feature;
use App\Models\{User,Attendance,CrmPipelineProject,LeaveApplication,Appraisal};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CrossDepartmentStatisticsAuditTest extends TestCase {
 use RefreshDatabase;
 public function test_each_department_attendance_uses_native_days_even_without_department_reports(): void {
  $this->travelTo(now()->setDate(2026,11,18)->setTime(18,0));
  foreach(['brands_marketing','client_relations','operations_projects','creatives','finance','hr_admin'] as $dept){
   $user=User::factory()->create(['status'=>'active','access_role'=>'staff','job_level'=>'executive','department'=>$dept,'start_date'=>'2026-11-16']);
   Attendance::create(['user_id'=>$user->id,'clock_in_at'=>'2026-11-16 08:50:00','status'=>'On Time','daily_objective'=>'Real work']);
   Attendance::create(['user_id'=>$user->id,'clock_in_at'=>'2026-11-17 09:30:00','status'=>'Late','daily_objective'=>'Real work']);
   $source=app(\App\Services\DepartmentSourcePerformance::class)->build($dept,'2026-11-16','2026-11-22');
   $this->assertSame(3,$source['attendance']['expected'],$dept);$this->assertSame(2,$source['attendance']['clocked'],$dept);$this->assertSame(1,$source['attendance']['late'],$dept);$this->assertSame(1,$source['attendance']['missing'],$dept);$this->assertEqualsWithDelta(66.67,$source['attendance']['attendance_credit'],.01,$dept);
  }
 }
 public function test_revenue_period_uses_overlapping_project_dates_and_only_finance_confirmed_actuals(): void {
  $this->travelTo(now()->setDate(2026,11,18)->setTime(18,0));$u=User::factory()->create(['status'=>'active','department'=>'client_relations']);
  CrmPipelineProject::create(['client_name'=>'Period spanning account','project_name'=>'Long engagement','owner_id'=>$u->id,'created_by'=>$u->id,'updated_by'=>$u->id,'stage'=>'project_ongoing','week_start'=>'2026-11-02','week_end'=>'2026-11-30','source_period_confirmed'=>true,'actual_amount'=>120,'revenue_state'=>'confirmed','revenue_confirmed_amount'=>120]);
  $dashboard=app(\App\Services\RevenueDashboard::class)->build(['period'=>'weekly','date'=>'2026-11-18']);
  $this->assertSame(1,$dashboard['pager']->total());
 }
 public function test_executive_review_counts_follow_real_approval_states(): void {
  $cvo=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);$staff=User::factory()->create(['status'=>'active']);
  LeaveApplication::create(['user_id'=>$staff->id,'start_date'=>'2026-11-19','end_date'=>'2026-11-20','leave_type'=>'annual','status'=>'pending_hr']);
  LeaveApplication::create(['user_id'=>$cvo->id,'start_date'=>'2026-11-19','end_date'=>'2026-11-20','leave_type'=>'annual','status'=>'pending_hr']);
  Appraisal::create(['user_id'=>$staff->id,'year'=>2026,'quarter'=>'Q4','status'=>'manager_reviewed']);
  $cards=collect(app(\App\Services\ExecutiveDashboardSummary::class)->build($cvo))->keyBy('label');
  $this->assertSame(1,$cards['Leave Approvals']['count']);$this->assertSame(1,$cards['Appraisal Reviews']['count']);
 }
}
