<?php
namespace Tests\Feature;
use App\Models\{User,ActionPoint};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ActionPointsWorkflowTest extends TestCase {
 use RefreshDatabase;
 public function test_staff_can_edit_change_status_and_delete_then_refresh_only_action_points():void {
  $u=User::factory()->create(['status'=>'active']);$assignee=User::factory()->create(['status'=>'active']);
  $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($u);
  $this->postJson(route('portal.action-points.store'),['action_point'=>'Prepare the meeting follow up','assignee_id'=>$assignee->id,'status'=>'pending'])->assertOk();$item=ActionPoint::sole();
  $this->patchJson(route('portal.action-points.update',$item),['action_point'=>'Updated meeting decision','assignee_id'=>$assignee->id,'status'=>'in_progress','comments'=>'Supplier response received'])->assertOk();
  $this->get(route('portal.action-points.table'))->assertOk()->assertSee('Updated meeting decision')->assertSee('Supplier response received')->assertDontSee('Weekly Department Reports');
  $this->postJson(route('portal.action-points.update',$item),['_method'=>'PATCH','status'=>'done'])->assertOk()->assertJsonPath('status','done');
  $this->deleteJson(route('portal.action-points.destroy',$item))->assertOk();
  $this->get(route('portal.action-points.table'))->assertOk()->assertDontSee('Updated meeting decision');
  $this->assertDatabaseCount('action_points',0);
 }
}
