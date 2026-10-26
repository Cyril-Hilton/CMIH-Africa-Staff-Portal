<?php
namespace Tests\Feature;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HrAttendanceViewTest extends TestCase {
 use RefreshDatabase;
 public function test_period_filters_chart_and_private_access():void {
  $hr=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
  $staff=User::factory()->create(['name'=>'Punctual Person','department'=>'finance','status'=>'active']);
  Attendance::create(['daily_objective'=>'Test delivery','status'=>'On Time','user_id'=>$staff->id,'clock_in_at'=>'2026-10-20 08:45:00','clock_out_at'=>'2026-10-20 19:00:00','overtime_minutes'=>60]);
  Attendance::create(['daily_objective'=>'Test delivery','status'=>'On Time','user_id'=>$staff->id,'clock_in_at'=>'2026-10-21 09:30:00']);
  Attendance::create(['daily_objective'=>'Test delivery','status'=>'On Time','user_id'=>$staff->id,'clock_in_at'=>'2026-09-21 08:00:00']);
  $this->actingAs($hr)->get(route('portal.hr-reporting.attendance',['period'=>'weekly','date'=>'2026-10-20']))->assertOk()->assertSee('50% On Time')->assertSee('30')->assertSee('Not Clocked Out');
  $this->get(route('portal.hr-reporting.attendance',['period'=>'monthly','date'=>'2026-10-20','punctuality'=>'late','department'=>'finance','q'=>'Punctual','sort'=>'newest']))->assertOk()->assertSee('50% On Time')->assertDontSee('2026-10-20 08:45');
  $this->get(route('portal.hr-reporting.attendance',['period'=>'yearly','date'=>'2026-10-20']))->assertOk()->assertSee('66.7% On Time');
  $this->actingAs($hr)->get(route('dashboard',['weekly_department'=>'hr_admin','attendance_period'=>'yearly','attendance_date'=>'2026-10-20']))->assertOk()->assertSee('dw-tab-hr_admin-attendance',false)->assertSee('66.7% On Time');
  $this->actingAs($staff)->get(route('portal.hr-reporting.attendance'))->assertForbidden();
  $this->get(route('dashboard',['weekly_department'=>'hr_admin']))->assertOk()->assertDontSee('dw-tab-hr_admin-attendance',false);
 }
 public function test_fixed_break_allowance_excludes_overtime_without_break_clocking():void {
  $a=new Attendance(['clock_in_at'=>'2026-10-23 09:00:00','clock_out_at'=>'2026-10-23 18:00:00']);
  $this->assertSame(480,$a->scheduledWorkingMinutes());
  $a->clock_out_at='2026-10-23 19:00:00';
  $this->assertSame(480,$a->scheduledWorkingMinutes());
  $a->clock_in_at='2026-10-23 10:00:00';
  $this->assertSame(420,$a->scheduledWorkingMinutes());
  $a->clock_out_at=null;
  $this->assertNull($a->scheduledWorkingMinutes());
  $a->clock_out_at='2026-10-23 10:20:00';
  $this->assertSame(0,$a->scheduledWorkingMinutes());
 }
}
