<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\{User,CrmAccountRelationship,CrmPipelineProject};
use App\Services\{ClientRelationsCentre,ClientRelationsPipeline,PerformanceWorkflow,BrandedSpreadsheet};
use App\Services\Creative\CreativeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ClientRelationsCentreController extends Controller {
 private function filters(Request $r):array {
  abort_unless(CreativeAccess::staff($r->user()),403);
  return $r->validate(['source'=>'nullable|in:all,workbook','q'=>'nullable|string|max:150','client'=>'nullable|string|max:255','owner'=>'nullable|integer|min:1','stage'=>['nullable',Rule::in(array_keys(config('crm_pipeline.stages')))],'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from','sort'=>'nullable|in:client,revenue','per_page'=>'nullable|in:6,12,24,48','page'=>'nullable|integer|min:1']);
 }
 public function data(Request $r,ClientRelationsCentre $centre){$f=$this->filters($r);return response()->json($centre->build($f,$r->user()))->header('Cache-Control','private, no-store');}
 public function relationship(Request $r){
  abort_unless(ClientRelationsPipeline::member($r->user())&&$r->user()->isLineManager(),403);
  $v=$r->validate(['client_name'=>'required|string|max:255','owner_id'=>'required|integer|exists:users,id','relationship'=>'required|in:current,previous,shared','starts_on'=>'nullable|date_format:Y-m-d','ends_on'=>'nullable|date_format:Y-m-d|after_or_equal:starts_on','note'=>'required|string|min:5|max:2000']);
  abort_unless(collect([CrmPipelineProject::class,\App\Models\CrmFinancialRegister::class,\App\Models\CrmAccountAllocation::class])->contains(fn($model)=>$model::whereRaw('LOWER(TRIM(client_name)) = ?',[ClientRelationsCentre::key($v['client_name'])])->exists()),422,'Choose an existing Client Relations account.');
  $owner=User::findOrFail($v['owner_id']);abort_unless(User::normalizeDepartmentKey($owner->department)==='client_relations',422,'Choose a Client Relations account owner.');
  if($v['relationship']==='previous'&&empty($v['ends_on']))return response()->json(['message'=>'Record the end date for a previous account owner.'],422);
  $item=DB::transaction(function()use($v,$r){$existing=CrmAccountRelationship::whereRaw('LOWER(TRIM(client_name)) = ?',[ClientRelationsCentre::key($v['client_name'])])->where('owner_id',$v['owner_id'])->where('relationship',$v['relationship'])->when(!empty($v['starts_on']),fn($q)=>$q->whereDate('starts_on',$v['starts_on']),fn($q)=>$q->whereNull('starts_on'))->when(!empty($v['ends_on']),fn($q)=>$q->whereDate('ends_on',$v['ends_on']),fn($q)=>$q->whereNull('ends_on'))->first();abort_if($existing,409,'This account relationship is already recorded.');$item=CrmAccountRelationship::create($v+['created_by'=>$r->user()->id]);PerformanceWorkflow::record('crm_relationship',$item->id,$r->user()->id,'create',[],$item->getAttributes(),$v['note']);return $item;});
  return response()->json(['id'=>$item->id,'message'=>'Account relationship recorded. Existing project ownership and history are retained.'],201);
 }
 public function targetResult(Request $r,\App\Models\CrmAccountTarget $target){
  abort_unless(ClientRelationsPipeline::member($r->user())&&$r->user()->isLineManager(),403);
  $v=$r->validate(['revision'=>'required|integer|min:1','actual_amount'=>'required|numeric|min:0|max:9999999999.99','reason'=>'required|string|min:5|max:2000']);
  DB::transaction(function()use($r,$target,$v){$item=\App\Models\CrmAccountTarget::lockForUpdate()->findOrFail($target->id);abort_if($item->needs_reconciliation,422,'Confirm the original target through the source correction workflow first.');abort_if((int)$item->revision!==(int)$v['revision'],409,'The target changed. Refresh before saving.');$before=$item->getAttributes();$item->update(['actual_amount'=>$v['actual_amount'],'revision'=>$item->revision+1]);PerformanceWorkflow::record('crm_target',$item->id,$r->user()->id,'record_actual_result',$before,$item->getAttributes(),$v['reason']);});
  return response()->json(['message'=>'Period result recorded and sent for Finance confirmation. It will count after both departments agree.']);
 }
 public function financialUpdate(Request $r,\App\Models\CrmFinancialRegister $entry){
  abort_unless(ClientRelationsPipeline::member($r->user())&&($r->user()->isLineManager()||(int)$entry->owner_id===$r->user()->id),403);
  $v=$r->validate(['revision'=>'required|integer|min:1','po_amount'=>'nullable|numeric|min:0|max:9999999999.99','po_balance'=>'nullable|numeric|min:-9999999999.99|max:9999999999.99','invoiced_amount'=>'nullable|numeric|min:0|max:9999999999.99','po_status'=>'nullable|string|max:255','invoice_status'=>'nullable|string|max:255','as_of'=>'required|date_format:Y-m-d|before_or_equal:today','reason'=>'required|string|min:5|max:2000']);
  DB::transaction(function()use($r,$entry,$v){$item=\App\Models\CrmFinancialRegister::lockForUpdate()->findOrFail($entry->id);abort_if((int)$item->revision!==(int)$v['revision'],409,'This record changed. Refresh before saving.');$before=$item->getAttributes();$item->fill(collect($v)->except(['revision','reason'])->all());$item->revision++;$item->save();PerformanceWorkflow::record('crm_financial_register',$item->id,$r->user()->id,'correct_record',$before,$item->getAttributes(),$v['reason']);});return response()->json(['message'=>'Financial record updated. The original workbook figures are retained in its history.']);
 }
 public function allocationUpdate(Request $r,\App\Models\CrmAccountAllocation $allocation){
  abort_unless(ClientRelationsPipeline::member($r->user())&&($r->user()->isLineManager()||(int)$allocation->owner_id===$r->user()->id),403);
  $v=$r->validate(['revision'=>'required|integer|min:1','owner_id'=>'nullable|integer|exists:users,id','ytd_target'=>'nullable|numeric|min:0|max:9999999999.99','monthly_target'=>'nullable|numeric|min:0|max:9999999999.99','budget_target'=>'nullable|numeric|min:0|max:9999999999.99','reported_target'=>'nullable|numeric|min:0|max:9999999999.99','reason'=>'required|string|min:5|max:2000']);
  if(array_key_exists('owner_id',$v)&&(int)$v['owner_id']!==(int)$allocation->owner_id){abort_unless($r->user()->isLineManager(),403);abort_unless(!empty($v['owner_id'])&&ClientRelationsPipeline::staffMember(User::find($v['owner_id'])),422,'Choose an active Client Relations owner.');}
  DB::transaction(function()use($r,$allocation,$v){$item=\App\Models\CrmAccountAllocation::lockForUpdate()->findOrFail($allocation->id);abort_if((int)$item->revision!==(int)$v['revision'],409,'This allocation changed. Refresh before saving.');$before=$item->getAttributes();$item->fill(collect($v)->except(['revision','reason'])->all());$item->revision++;$item->save();PerformanceWorkflow::record('crm_allocation',$item->id,$r->user()->id,'correct_allocation',$before,$item->getAttributes(),$v['reason']);});return response()->json(['message'=>'Account allocation updated. The original source is retained.']);
 }
 public function export(Request $r,ClientRelationsCentre $centre,string $format){
  $f=$this->filters($r);abort_unless(in_array($format,['xlsx','pdf']),404);
  // Export the whole matching cohort, independently of the currently visible client page.
  $f['per_page']=1000;$f['page']=1;$data=$centre->build($f,$r->user());abort_if($data['pagination']['last']>1,422,'Narrow the filters to 1,000 clients before exporting.');
  if($format==='xlsx'){$rows=[['Client','Project','Account Owner','Sales Stage','Reporting Week','Proposed (GH₵)','PO (GH₵)','Target (GH₵)','Finance Confirmed Revenue (GH₵)','Invoiced (GH₵)','Collected (GH₵)','Weighted Forecast (GH₵)','Achievement (%)','Bottleneck','Next Action','PO Balance (GH₵)','YTD Target (GH₵)','Monthly Target (GH₵)','Budget Target (GH₵)','Reported Target (GH₵)']];foreach($data['clients'] as $c)foreach($c['projects'] as $p)$rows[]=[$c['name'],$p['title'],$p['owner']['name'],config('crm_pipeline.stages.'.$p['stage'].'.label'),$p['week'],...array_map(fn($k)=>$p['money'][$k]??'Not Recorded',['proposed_amount','po_amount','target_amount','actual_amount','invoiced_amount','collected_amount']),$p['metrics']['weighted_forecast']??'Not Recorded',$p['metrics']['achievement_percent']??'Not Measured',$p['bottleneck'],$p['next']];foreach($data['clients'] as $c)foreach($c['finance']??[] as $r)$rows[]=[$c['name'],$r['title'],$r['owner']['name'],'Source Financial Register',($r['as_of']??('Year '.$r['year'].'; exact date not provided')),'Not Recorded',$r['po'],'Not Recorded','Not Recorded',$r['invoiced'],'Not Recorded','Not Recorded','Not Measured',$r['discrepancy']?'Source balance discrepancy':'',$r['source'],$r['balance']];foreach($data['allocations']??[] as $a)$rows[]=[$a['client'],'Account Allocation',$a['owner'],'Reference Allocation','Period Not Confirmed',null,null,null,null,null,null,null,null,null,$a['source'],null,$a['ytd'],$a['monthly'],$a['budget'],$a['reported']];return response(app(BrandedSpreadsheet::class)->xlsx($rows),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="cmih-client-business-review.xlsx"','Cache-Control'=>'private, no-store']);}
  $pdf=new \Dompdf\Dompdf();$pdf->loadHtml(view('portal.client-relations.business-report',['data'=>$data])->render());$pdf->setPaper('a4','landscape');$pdf->render();return response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="cmih-client-business-review.pdf"','Cache-Control'=>'private, no-store']);
 }
}
