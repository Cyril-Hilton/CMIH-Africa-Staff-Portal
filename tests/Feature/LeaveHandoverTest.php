<?php
namespace Tests\Feature;
use App\Models\{User,LeaveApplication};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Storage};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class LeaveHandoverTest extends TestCase {
 use RefreshDatabase;
 public function test_required_private_handover_upload_acknowledgement_and_resubmission():void {
  Mail::fake();Storage::fake('local');config(['creative.upload_scan_required'=>false,'creative.upload_scanner'=>null]);
  $u=User::factory()->create(['status'=>'active','leave_balance'=>30,'job_level'=>'executive']);$cover=User::factory()->create(['status'=>'active']);$other=User::factory()->create(['status'=>'active']);$manager=User::factory()->create(['status'=>'active','job_level'=>'manager','access_role'=>'manager']);
  $v=['leave_type'=>'annual','start_date'=>now()->next('Monday')->toDateString(),'end_date'=>now()->next('Monday')->addDay()->toDateString(),'line_manager_id'=>$manager->id,'covering_staff_id'=>$cover->id];
  $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($u)->post(route('portal.leaves.store'),$v)->assertSessionHasErrors('handover_notes');
  $v['handover_notes']='<p>Follow up the vendor delivery and send the Friday report.</p>';
  $this->post(route('portal.leaves.store'),$v+['handover_attachment'=>UploadedFile::fake()->createWithContent('handover.txt','Vendor delivery schedule and reporting instructions.')])->assertSessionHasNoErrors();
  $leave=LeaveApplication::sole();$this->assertSame($v['handover_notes'],$leave->handover_notes);Storage::disk('local')->assertExists($leave->handover_attachment['path']);
  $this->actingAs($other)->get(route('portal.leaves.handover.file',$leave))->assertForbidden();
  $this->actingAs($cover)->get(route('portal.leaves.handover.file',$leave))->assertDownload('handover.txt');
  $this->get(route('portal.leaves'))->assertOk()->assertSee('Follow up the vendor delivery');
  $this->post(route('portal.leaves.handover.acknowledge',$leave),['revision'=>1,'handover_reply'=>'Received all files and the reporting deadlines.'])->assertSessionHasNoErrors();
  $this->assertSame($cover->id,$leave->fresh()->handover_acknowledged_by);$this->assertSame('pending_manager',$leave->fresh()->status);
  $this->post(route('portal.leaves.handover.acknowledge',$leave),['revision'=>1,'handover_reply'=>'Duplicate acknowledgement'])->assertStatus(409);
  $this->actingAs($u)->post(route('portal.leaves.resubmit',$leave),array_replace($v,['handover_notes'=>'Updated handover: send the new Monday report as well.']))->assertSessionHasNoErrors();
  $this->assertNull($leave->fresh()->handover_acknowledged_at);$this->assertSame(2,$leave->fresh()->handover_revision);$this->assertCount(1,$leave->fresh()->handover_history);
  $this->assertSame($v['handover_notes'],$leave->fresh()->handover_history[0]['notes']);
  $this->actingAs($cover)->post(route('portal.leaves.handover.acknowledge',$leave),['revision'=>1,'handover_reply'=>'An obsolete version response.'])->assertStatus(409);
 }
 public function test_unsafe_attachment_cannot_be_uploaded():void {
  config(['creative.upload_scan_required'=>false]);Mail::fake();$u=User::factory()->create(['status'=>'active','leave_balance'=>30,'job_level'=>'executive']);$manager=User::factory()->create(['status'=>'active','job_level'=>'manager']);$cover=User::factory()->create(['status'=>'active']);
  $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($u)->post(route('portal.leaves.store'),['leave_type'=>'annual','start_date'=>now()->next('Monday')->toDateString(),'end_date'=>now()->next('Monday')->toDateString(),'line_manager_id'=>$manager->id,'covering_staff_id'=>$cover->id,'handover_notes'=>'Full documented handover for the vendor schedule.','handover_attachment'=>UploadedFile::fake()->createWithContent('payload.html','<script>alert(1)</script>')])->assertSessionHasErrors('handover_attachment');
  $this->assertDatabaseCount('leave_applications',0);
 }
}
