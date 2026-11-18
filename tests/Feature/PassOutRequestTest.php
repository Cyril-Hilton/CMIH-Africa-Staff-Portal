<?php
namespace Tests\Feature;
use App\Models\{User,PassOutRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PassOutRequestTest extends TestCase {
    use RefreshDatabase;
    private function people(): array {
        $this->travelTo(now()->setDate(2026,11,18)->setTime(9,0));
        $supervisor=User::factory()->create(['status'=>'active','department'=>'operations_projects','job_level'=>'manager','access_role'=>'staff']);
        $hod=User::factory()->create(['status'=>'active','department'=>'operations_projects','job_level'=>'manager','access_role'=>'staff']);
        $staff=User::factory()->create(['status'=>'active','department'=>'operations_projects','job_level'=>'executive','access_role'=>'staff','line_manager_id'=>$supervisor->id,'leave_balance'=>20]);
        $hr=User::factory()->create(['status'=>'active','department'=>'hr_admin','position_title'=>'HR Manager','job_level'=>'manager','access_role'=>'manager']);
        return [$staff,$supervisor,$hod,$hr];
    }
    private function data($sup,$hod): array {return ['request_date'=>'2026-11-19','departure_time'=>'13:00','reason'=>'Personal appointment','supervisor_id'=>$sup->id,'hod_id'=>$hod->id];}
    public function test_half_day_requires_all_stages_and_never_deducts_full_day_leave(): void {
        [$staff,$sup,$hod,$hr]=$this->people();
        $this->actingAs($staff)->post(route('portal.pass-outs.store'),$this->data($sup,$hod))->assertSessionHasNoErrors();
        $row=PassOutRequest::sole();
        $this->actingAs($staff)->get(route('portal.leaves'))->assertOk()->assertSee('Half-day Pass-out')->assertSee('Personal appointment');
        $this->actingAs($hr)->post(route('portal.pass-outs.review',$row),['decision'=>'approve'])->assertForbidden();
        foreach([[$sup,'pending_hod'],[$hod,'pending_hr'],[$hr,'approved']] as [$reviewer,$status]) {
            $this->actingAs($reviewer)->post(route('portal.pass-outs.review',$row),['decision'=>'approve','note'=>'Reviewed'])->assertSessionHasNoErrors();
            $this->assertSame($status,$row->fresh()->status);
        }
        $this->assertEquals(20,$staff->fresh()->leave_balance);
        $this->assertCount(4,$row->fresh()->history);
        $this->assertNotNull($row->fresh()->recorded_at);
        $this->actingAs($hr)->post(route('portal.pass-outs.review',$row),['decision'=>'approve'])->assertUnprocessable();
    }
    public function test_monthly_reservation_dates_and_review_permissions(): void {
        [$staff,$sup,$hod,$hr]=$this->people();$data=$this->data($sup,$hod);
        $this->actingAs($staff)->post(route('portal.pass-outs.store'),array_replace($data,['departure_time'=>'12:59']))->assertSessionHasErrors('departure_time');
        $this->post(route('portal.pass-outs.store'),array_replace($data,['request_date'=>'2026-11-21']))->assertSessionHasErrors('request_date');
        $this->post(route('portal.pass-outs.store'),array_replace($data,['request_date'=>'2026-11-17']))->assertSessionHasErrors('request_date');
        $this->post(route('portal.pass-outs.store'),$data)->assertSessionHasNoErrors();$row=PassOutRequest::sole();
        $this->post(route('portal.pass-outs.store'),array_replace($data,['request_date'=>'2026-11-20']))->assertSessionHasErrors('request_date');
        $this->post(route('portal.pass-outs.review',$row),['decision'=>'approve'])->assertForbidden();
        $this->actingAs($sup)->post(route('portal.pass-outs.review',$row),['decision'=>'reject'])->assertSessionHasErrors('note');
        $this->post(route('portal.pass-outs.review',$row),['decision'=>'reject','note'=>'Please choose another day.'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('portal.pass-outs.store'),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pass_out_requests',2);
        $outsider=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives']);
        $this->actingAs($outsider)->get(route('portal.leaves'))->assertOk()->assertDontSee('Personal appointment');
    }
}
