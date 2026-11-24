<?php
namespace Tests\Feature;
use App\Models\{User,Attendance,ThirdPartyVendor,HrDashboardActivity};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HrClockInRecoveryTest extends TestCase
{
 use RefreshDatabase;
 public function test_register_and_activity_forms_survive_clock_in_blocks():void{
  $this->travelTo(now()->setDate(2026,11,24)->setTime(12,0));$hr=User::factory()->create(['status'=>'active','department'=>'hr_admin','access_role'=>'manager','position_title'=>'HR Manager']);
  $input=['data'=>['name'=>'Unique blocked vendor input','service'=>'Fabrication','state'=>'Active']];
  $response=$this->actingAs($hr)->withHeader('X-Test-Enforce-ClockIn','1')->post(route('portal.hr-registers.store','vendor'),$input)->assertSessionHasErrors('attendance')->assertSessionHasInput('data.name','Unique blocked vendor input');
  $html=$this->get($response->headers->get('Location'))->assertOk()->assertSee('Open Clock In in another tab')->getContent();$this->assertSame(1,substr_count($html,'value="Unique blocked vendor input"'));$this->assertDatabaseCount('third_party_vendors',0);
  $activity=['tab'=>'fleet','category'=>'Vehicle servicing','activity'=>'Preserved HR activity','opened_on'=>'2026-11-23','deadline'=>'2026-11-25','assigned_to'=>$hr->id,'status'=>'Not Started'];
  $response=$this->post(route('portal.hr-dashboard.activities.store'),$activity)->assertSessionHasErrors('attendance')->assertSessionHasInput('activity','Preserved HR activity');
  $this->get($response->headers->get('Location'))->assertOk()->assertSee('Preserved HR activity');$this->assertDatabaseCount('hr_dashboard_activities',0);
  Attendance::create(['user_id'=>$hr->id,'clock_in_at'=>now(),'status'=>'Late','daily_objective'=>'HR reporting']);
  $this->post(route('portal.hr-registers.store','vendor'),$input)->assertSessionHasNoErrors();$this->assertDatabaseCount('third_party_vendors',1);
  $this->post(route('portal.hr-dashboard.activities.store'),$activity)->assertSessionHasNoErrors();$this->assertDatabaseCount('hr_dashboard_activities',1);
 }
}
