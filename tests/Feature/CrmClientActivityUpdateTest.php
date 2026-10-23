<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,CrmClientRecord};
class CrmClientActivityUpdateTest extends TestCase {
 use RefreshDatabase;
 public function test_owner_can_update_with_revision_and_reason_but_other_staff_cannot():void {
  $owner=User::factory()->create(['department'=>'client_relations','access_role'=>'staff','status'=>'active']);
  $record=CrmClientRecord::create(['kind'=>'issue','reference'=>'ISS-1','client_name'=>'Dove','owner_id'=>$owner->id,'status'=>'open','data'=>['title'=>'Delivery clarification'],'notes'=>'Private contact note','created_by'=>$owner->id,'updated_by'=>$owner->id]);
  $payload=['kind'=>'issue','reference'=>'ISS-1','client_name'=>'Dove','owner_id'=>$owner->id,'status'=>'resolved','data'=>['title'=>'Delivery clarification','resolved_on'=>today()->toDateString()],'notes'=>'Private revised note','revision'=>1,'reason'=>'Client confirmed the issue was resolved'];
  $other=User::factory()->create(['department'=>'client_relations','access_role'=>'staff','status'=>'active']);
  $this->actingAs($other)->postJson(route('portal.crm.record.update',$record),$payload)->assertForbidden();
  $this->actingAs($owner)->postJson(route('portal.crm.record.update',$record),array_diff_key($payload,['reason'=>true]))->assertUnprocessable();
  $this->postJson(route('portal.crm.record.update',$record),$payload)->assertRedirect();
  $this->assertSame('resolved',$record->fresh()->status);$this->assertEquals(2,$record->fresh()->revision);
  $this->postJson(route('portal.crm.record.update',$record),$payload)->assertStatus(409);
  $neutral=User::factory()->create(['department'=>'operations_projects','status'=>'active']);
  $this->actingAs($neutral)->getJson(route('portal.crm.centre.data'))->assertOk()->assertDontSee('Private revised note');
 }
 public function test_updates_reuse_invoice_and_reference_validation():void {
  $owner=User::factory()->create(['department'=>'client_relations','access_role'=>'manager','status'=>'active']);
  $record=CrmClientRecord::create(['kind'=>'invoice','reference'=>'INV-1','client_name'=>'Dove','owner_id'=>$owner->id,'status'=>'issued','data'=>['amount'=>100],'created_by'=>$owner->id,'updated_by'=>$owner->id]);
  $values=['kind'=>'invoice','reference'=>'INV-1','client_name'=>'Dove','owner_id'=>$owner->id,'status'=>'paid','data'=>['amount'=>100,'paid_amount'=>150],'revision'=>1,'reason'=>'Invoice reconciliation checked'];
  $this->actingAs($owner)->postJson(route('portal.crm.record.update',$record),$values)->assertUnprocessable();
  $values['data']['paid_amount']=100;$this->postJson(route('portal.crm.record.update',$record),$values)->assertRedirect();
  $this->assertSame('paid',$record->fresh()->status);
  $this->get(route('portal.crm.index'))->assertOk()->assertSee('Update Existing Client Activities')->assertSee('Save Activity Update');
 }
}
