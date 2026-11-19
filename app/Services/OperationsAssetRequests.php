<?php
namespace App\Services;
use App\Models\{Asset,AssetWarehouseRequest,OperationsHandover,PerformanceRegister,User};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/** A published checklist queues requisitions; only warehouse issue/return changes stock. */
class OperationsAssetRequests
{
 public function validate(array $rows):array {
  $selected=[];
  foreach($rows as $row){if(empty($row['selected']))continue;$id=(int)($row['asset_id']??0);$qty=(int)($row['quantity']??0);
   $asset=Asset::where('is_warehouse_tracked',true)->find($id);
   if(!$asset||$qty<1||isset($selected[$id]))throw ValidationException::withMessages(['warehouse_assets'=>'Choose each available warehouse asset once and enter a positive quantity.']);
   $selected[$id]=['asset_id'=>$id,'quantity'=>$qty];
  }return array_values($selected);
 }
 public function sync(PerformanceRegister $record,User $reviewer):void {
  if(!array_key_exists('warehouse_assets',$record->data??[]))return;
  $data=$record->data;$custodian=User::find($record->owner_id);
  if(!$custodian||!$custodian->isActive()||User::normalizeDepartmentKey($custodian->department)!=='operations_projects')throw ValidationException::withMessages(['warehouse_assets'=>'An active Operations project owner is required for asset custody.']);
  $handover=!empty($data['operations_handover_id'])?OperationsHandover::findOrFail($data['operations_handover_id']):null;
  $selected=collect($data['warehouse_assets']);$existing=AssetWarehouseRequest::where('operations_register_id',$record->id)->lockForUpdate()->get()->keyBy('asset_id');
  foreach($selected as $line){$asset=Asset::where('is_warehouse_tracked',true)->findOrFail($line['asset_id']);$request=$existing->get($asset->id);$qty=(int)$line['quantity'];
   if($request){
    if($request->requested_quantity===$qty&&(int)($request->custodian_id??$request->requested_by)===$custodian->id)continue;
    if(!in_array($request->status,[AssetWarehouseRequest::STATUS_PENDING_CHECK,AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION,AssetWarehouseRequest::STATUS_REJECTED],true))throw ValidationException::withMessages(['warehouse_assets'=>'An approved or issued asset allocation cannot be changed by editing a report. Use the warehouse return/issue process.']);
   }
   $before=$request?->getAttributes()??[];
   $request??=new AssetWarehouseRequest(['request_code'=>'WAR-'.Str::ulid(),'asset_id'=>$asset->id,'operations_register_id'=>$record->id,'requested_by'=>$record->submitted_by??$custodian->id]);
   $request->fill(['custodian_id'=>$custodian->id,'crm_project_id'=>$data['crm_project_id']??null,'operations_handover_id'=>$handover?->id,'operations_activation_id'=>$record->kind==='activation'?$record->id:($data['activation_id']??null),'requested_quantity'=>$qty,'requested_for'=>$data['starts_on']??$record->period_start,'destination_location'=>trim(strip_tags($data['location']??''))?:$record->project?:$record->title,'purpose'=>'Project assets · '.($record->project?:$record->title),'status'=>AssetWarehouseRequest::STATUS_PENDING_CHECK])->save();
   PerformanceWorkflow::record('warehouse_request',$request->id,$reviewer->id,'project_asset_requested',$before,$request->getAttributes(),'Published Operations checklist. Custody begins only when issued.');
   $this->notify($request,$reviewer,'Project asset request awaiting warehouse review');
  }
  foreach($existing as $id=>$request)if(!$selected->contains('asset_id',$id)&&!in_array($request->status,[AssetWarehouseRequest::STATUS_REJECTED,AssetWarehouseRequest::STATUS_CLOSED],true)){
   if(!in_array($request->status,[AssetWarehouseRequest::STATUS_PENDING_CHECK,AssetWarehouseRequest::STATUS_RETURNED_FOR_CORRECTION],true))throw ValidationException::withMessages(['warehouse_assets'=>'Return or close approved/issued assets in the warehouse before removing their checklist allocation.']);
   $before=$request->getAttributes();$request->update(['status'=>AssetWarehouseRequest::STATUS_REJECTED,'reviewed_by'=>$reviewer->id,'review_note'=>'Removed from the reviewed project asset checklist.']);PerformanceWorkflow::record('warehouse_request',$request->id,$reviewer->id,'allocation_withdrawn',$before,$request->getAttributes(),'Project checklist revised.');
  }
 }
 public function notify(AssetWarehouseRequest $request,User $actor,string $title):void {
  $reviewers=User::internalStaff()->where('status','active')->get()->filter(fn($u)=>app(WarehouseAssetIntake::class)->canReview($u))->pluck('id');
  foreach($reviewers->merge([$request->custodian_id,$request->requested_by])->filter()->unique() as $id)PerformanceWorkflow::notify($id,'warehouse-project:'.$request->id.':'.$request->updated_at->format('U.u').':'.$title,$title,$request->asset->name.' · '.$request->requested_quantity.' units · custodian: '.($request->custodian?->name??$request->requester?->name).'. '.$request->purpose,route('portal.assets.warehouse.index',['request_status'=>$request->status]));
 }
}
