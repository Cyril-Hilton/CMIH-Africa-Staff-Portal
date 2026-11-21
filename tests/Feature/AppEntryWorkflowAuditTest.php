<?php
namespace Tests\Feature;
use App\Models\{Task,User,PettyCashClaim,Sku};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AppEntryWorkflowAuditTest extends TestCase {
 use RefreshDatabase;
 public function test_old_update_links_share_task_archive_permissions_and_manager_completion_review(): void {
  $manager=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>'operations_projects','job_level'=>'manager']);
  $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'operations_projects','line_manager_id'=>$manager->id]);
  $other=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'client_relations']);
  $this->actingAs($staff)->post(route('portal.updates.store'),['title'=>'Shared update route','summary'=>'Actual task details','priority'=>'medium','completion_manager_id'=>$manager->id,'due_on'=>'2026-11-25'])->assertSessionHasNoErrors();
  $task=Task::where('title','Shared update route')->sole();$this->assertSame($staff->id,$task->assigned_to);$this->assertSame('Actual task details',$task->details);
  $this->get(route('portal.updates.edit',$task))->assertOk()->assertSee('Shared update route');
  $this->patch(route('portal.updates.update',$task),['title'=>$task->title,'summary'=>'Submitted actual result','priority'=>'medium','status'=>'completed','progress'=>100,'completion_manager_id'=>$manager->id,'due_on'=>'2026-11-25'])->assertSessionHasNoErrors();
  $this->assertSame('Awaiting Approval',$task->fresh()->status);$this->assertSame('pending',$task->fresh()->completion_review_status);
  $this->actingAs($other)->get(route('portal.updates.edit',$task))->assertForbidden();
  $this->patch(route('portal.updates.update',$task),['title'=>'Unauthorised replacement','priority'=>'medium','status'=>'completed'])->assertForbidden();
  $this->assertSame('Shared update route',$task->fresh()->title);
 }
 public function test_archived_tasks_cannot_reappear_through_the_old_update_model_or_edit_links(): void {
  $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives']);
  $task=Task::create(['assigned_to'=>$staff->id,'assigned_by'=>$staff->id,'title'=>'Archived task','status'=>'In Progress','priority'=>'medium','department'=>'creatives']);
  $task->forceFill(['reporting_archive_batch_id'=>(string)\Illuminate\Support\Str::uuid()])->save();
  $this->assertSame(0,\App\Models\Update::count());$this->assertSame(1,\App\Models\Update::withReportingArchive()->count());
  $this->actingAs($staff)->get(route('portal.updates.edit',$task->id))->assertNotFound();
 }
 public function test_legacy_sku_entry_requires_merchandiser_admin_access(): void {
  $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'client_relations']);$sku=Sku::create(['name'=>'Protected stock item']);
  $this->actingAs($staff)->post(route('portal.merchandisers-admin.skus.store'),['name'=>'Forbidden item'])->assertForbidden();
  $this->put(route('portal.merchandisers-admin.skus.update',$sku),['name'=>'Forbidden change'])->assertForbidden();
  $this->delete(route('portal.merchandisers-admin.skus.destroy',$sku))->assertForbidden();
  $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
  $this->actingAs($admin)->put(route('portal.merchandisers-admin.skus.update',$sku),['name'=>'Reviewed stock item'])->assertSessionHasNoErrors();$this->assertSame('Reviewed stock item',$sku->fresh()->name);
 }
 public function test_cvo_role_receives_financial_notifications_without_exposing_them_to_regular_staff(): void {
  $owner=User::factory()->create(['status'=>'active','access_role'=>'staff']);$cvo=User::factory()->create(['status'=>'active','access_role'=>'cvo','job_level'=>'CVO','position_title'=>'CVO']);
  $claim=PettyCashClaim::create(['user_id'=>$owner->id,'amount'=>150,'currency'=>'GHS','description'=>'Test claim','status'=>'pending']);
  $result=$this->actingAs($cvo)->getJson(route('portal.notifications.poll',['since'=>now()->subMinute()->toIso8601String()]))->assertOk();$this->assertTrue(collect($result->json('notifications'))->contains('id','claim_'.$claim->id));
  $other=User::factory()->create(['status'=>'active','access_role'=>'staff']);$result=$this->actingAs($other)->getJson(route('portal.notifications.poll',['since'=>now()->subMinute()->toIso8601String()]))->assertOk();$this->assertFalse(collect($result->json('notifications'))->contains('id','claim_'.$claim->id));
 }
}
