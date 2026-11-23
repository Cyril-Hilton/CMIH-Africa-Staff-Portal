<?php
namespace Tests\Feature;
use App\Models\{User,Task,Attendance};
use App\Services\{AutoClockService,StaffPerformanceScore,RankingAttention,PerformanceScoringService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ExecutiveSupervisionPolicyTest extends TestCase {
 use RefreshDatabase;
 public function test_executive_profiles_have_no_personal_performance_flags_or_automatic_attendance_or_tasks():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 12:00:00'));
  $boss=User::factory()->create(['access_role'=>'manager','status'=>'active']);
  foreach([
   ['name'=>'Solomon Nanfa','department'=>'executive','access_role'=>'staff','job_level'=>'cvo','position_title'=>'CVO'],
   ['name'=>'Ericka','department'=>'executive','access_role'=>'staff','permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION]],
   ['name'=>'Super Admin','department'=>'executive','access_role'=>'super_admin'],
  ] as $profile){
   $u=User::factory()->create($profile+['status'=>'active','start_date'=>'2026-11-23','line_manager_id'=>$boss->id]);$this->assertTrue($u->isSupervisionOnly());$this->assertNull($u->fresh()->line_manager_id);
   AutoClockService::handleForUser($u);AutoClockService::backfillCurrentMonthForUser($u);
   $this->assertDatabaseMissing('attendance',['user_id'=>$u->id]);$this->assertDatabaseMissing('tasks',['assigned_to'=>$u->id]);
   $this->assertTrue(app(StaffPerformanceScore::class)->user($u,today()->startOfMonth(),today()->endOfMonth())['excluded']);
   $this->assertSame([],app(RankingAttention::class)->forPerson($u,today()->startOfMonth(),today()->endOfMonth())['issues']);
   $this->assertSame(0,PerformanceScoringService::attendanceSummary($u,today()->startOfMonth(),today()->endOfMonth())['expected_workdays']);
   $this->actingAs($u)->withHeader('X-Test-Enforce-ClockIn','true')->get(route('portal.performance.index'))->assertOk();
   $this->post(route('portal.attendance.clock-in'),[])->assertRedirect(route('dashboard'));$this->assertDatabaseMissing('attendance',['user_id'=>$u->id]);
  }
  foreach(['Cyril Hilton','Curtis Barnor'] as $name){$staff=User::factory()->create(['name'=>$name,'access_role'=>'staff','status'=>'active','department'=>'creatives']);$this->assertFalse($staff->isSupervisionOnly());AutoClockService::handleForUser($staff);$this->assertTrue(Attendance::where('user_id',$staff->id)->exists());$this->assertTrue(Task::where('assigned_to',$staff->id)->exists());}
 }
}
