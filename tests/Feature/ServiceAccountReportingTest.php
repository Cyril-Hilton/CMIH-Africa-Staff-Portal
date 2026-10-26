<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Task,Attendance};
use App\Services\{HrAttendanceReport,TaskStatsService,PerformanceScoringService,StaffPerformanceScore};
class ServiceAccountReportingTest extends TestCase {
 use RefreshDatabase;
 private function service(){return User::factory()->create(['name'=>'CMIH Staffs Presentation Account','email'=>'cmihstaffs@cmih.africa','department'=>'hr_admin','status'=>'active','access_role'=>'staff','is_service_account'=>true]);}
 public function test_service_account_is_excluded_without_losing_its_stored_records_or_identity():void {
  $service=$this->service();$human=User::factory()->create(['name'=>'Actual Employee','department'=>'hr_admin','status'=>'active']);
  $attendance=Attendance::create(['user_id'=>$service->id,'clock_in_at'=>today()->setTime(8,45),'daily_objective'=>'Presentation example','status'=>'On Time']);
  $this->assertSame([$human->id],User::internalStaff()->pluck('id')->all());$this->assertNotNull(User::find($service->id));$service->update(['name'=>'Shared presentation identity renamed']);$this->assertFalse(User::internalStaff()->whereKey($service->id)->exists());
  foreach(['weekly','monthly','yearly'] as $period){$data=app(HrAttendanceReport::class)->build(['period'=>$period]);$this->assertFalse($data['summary']->pluck('id')->contains($service->id));$this->assertSame(0,$data['logs']->total());}
  $this->assertDatabaseHas('attendance',['id'=>$attendance->id]);
  $score=PerformanceScoringService::attendanceSummary($service,today()->startOfMonth(),today());$this->assertTrue($score['excluded']);$this->assertSame(0,$score['expected_workdays']);
  $score=app(StaffPerformanceScore::class)->user($service,today()->startOfMonth(),today());$this->assertTrue($score['excluded']);$this->assertFalse($score['has_activity']);$this->assertNull($score['score']);
  $team=app(StaffPerformanceScore::class)->team(collect([$service,$human]),today()->startOfMonth(),today());$this->assertSame(1,$team['member_count']);
 }
 public function test_service_work_does_not_inflate_totals_but_work_created_for_a_person_is_retained():void {
  $service=$this->service();$human=User::factory()->create(['status'=>'active','department'=>'finance']);
  $demo=Task::create(['title'=>'Presentation example','assigned_to'=>$service->id,'assigned_by'=>$human->id,'department'=>'hr_admin','status'=>'Open','priority'=>'Low']);
  $real=Task::create(['title'=>'Real staff delivery','assigned_to'=>$human->id,'assigned_by'=>$service->id,'department'=>'finance','status'=>'Open','priority'=>'Low']);
  $this->assertSame(0,TaskStatsService::forUser($service)['created_total']);$this->assertSame(1,TaskStatsService::global()['total']);$this->assertSame(0,TaskStatsService::forDepartments(['hr_admin'])['total']);$this->assertSame(1,TaskStatsService::forUser($human)['assigned_total']);
  $this->assertDatabaseHas('tasks',['id'=>$demo->id]);$this->assertDatabaseHas('tasks',['id'=>$real->id]);
 }
 public function test_reporting_flag_is_not_changeable_through_profile_mass_assignment():void {
  $user=User::factory()->create(['is_service_account'=>true]);$user->fill(['is_service_account'=>false]);$this->assertTrue($user->isServiceAccount());
 }
}
