<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\{CrmReportSubmission,CrmPipelineProject,CrmClientRecord};
use App\Services\{ClientRelationsPipeline,ClientRelationsSubmissions,PerformanceWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ClientRelationsSubmissionController extends Controller {
 public function index(Request $r) {
  abort_unless(ClientRelationsPipeline::member($r->user()),403);
  $manager=ClientRelationsSubmissions::manager($r->user());
  $items=CrmReportSubmission::with('submitter','reviewer')->when(!$manager,fn($q)=>$q->where('submitted_by',$r->user()->id))->whereIn('status',['pending','returned'])->latest()->limit(100)->get();
  return response()->json(['can_review'=>$manager,'actor'=>$r->user()->id,'schema'=>config('crm_scorecard'),'entries'=>$items->map(fn($i)=>['id'=>$i->id,'revision'=>$i->revision,'type'=>$i->entity_type,'entity_id'=>$i->entity_id,'payload'=>$i->payload,'details'=>$this->details($i->payload),'status'=>$i->status,'note'=>$i->review_note,'submitted_by'=>$i->submitted_by,'author'=>$i->submitter?->name,'at'=>$i->updated_at?->toIso8601String(),'can_resubmit'=>$i->status==='returned' && (int)$i->submitted_by===$r->user()->id])])->header('Cache-Control','private, no-store');
 }
 private function details(array $payload): array {
  $details=[];
  foreach($payload as $key=>$value) {
   if(in_array($key,['revision','reason']))continue;
   $values=$key==='data' && is_array($value)?$value:[$key=>$value];
   foreach($values as $field=>$text) {
    if($field==='owner_id')$text=\App\Models\User::find($text)?->name??'Owner not available';
    elseif(str_ends_with($field,'_amount') && $text!==null)$text='GH₵ '.number_format((float)$text,2);
    $details[]=['label'=>ucwords(str_replace('_',' ',$field==='owner_id'?'account_owner':$field)), 'html'=>\App\Support\ReportRichText::html(is_scalar($text)?(string)$text:($text===null?'Not recorded':json_encode($text)))];
   }
  }
  return $details;
 }
 public function review(Request $r,CrmReportSubmission $submission) {
  abort_unless(ClientRelationsSubmissions::manager($r->user()),403);
  $v=$r->validate(['revision'=>'required|integer|min:1','decision'=>'required|in:approve,return','note'=>'required_if:decision,return|nullable|string|min:5|max:2000']);
  DB::transaction(function()use($r,$submission,$v){
   $i=CrmReportSubmission::lockForUpdate()->findOrFail($submission->id);
   abort_unless($i->status==='pending',409,'This entry is no longer awaiting review.');
   abort_if((int)$i->revision!==(int)$v['revision'],409,'This entry changed. Refresh before reviewing.');
   $before=$i->getAttributes();
   if($v['decision']==='approve') {
    $apply=Request::create('/portal/client-relations/pipeline','POST',$i->payload);
    $apply->headers->set('Accept','application/json'); $apply->setUserResolver(fn()=>$r->user());
    $apply->attributes->set('crm_approved_submission',$i); $controller=app(ClientRelationsPipelineController::class);
    if($i->entity_type==='project') $response=$i->entity_id?$controller->update($apply,app(ClientRelationsPipeline::class),CrmPipelineProject::findOrFail($i->entity_id)):$controller->store($apply,app(ClientRelationsPipeline::class));
    else $response=$controller->record($apply,$i->entity_id?CrmClientRecord::findOrFail($i->entity_id):null);
    abort_unless($response->getStatusCode()<300,422,'Approval could not be saved. Review the entry and try again.');
    $i->entity_id=$response->getData(true)['id'];
   }
   $i->status=$v['decision']==='approve'?'approved':'returned'; $i->review_note=$v['note']??null;
   $i->reviewed_by=$r->user()->id; $i->reviewed_at=now(); $i->revision++; $i->save();
   PerformanceWorkflow::record('crm_submission',$i->id,$r->user()->id,$v['decision'],$before,$i->getAttributes(),$v['note']??'Client Relations manager approved publication.');
   PerformanceWorkflow::notify($i->submitted_by,'crm-submission-reviewed:'.$i->id.':'.$i->revision,$i->status==='approved'?'Client Relations entry approved':'Client Relations corrections requested',$i->status==='approved'?'Your entry is published. Revenue remains subject to Finance confirmation.':'Open your entry on the weekly dashboard to read the correction notes and resubmit.',route('dashboard',['weekly_department'=>'client_relations']).'#staff-weekly-performance');
  });
  return response()->json(['message'=>$v['decision']==='approve'?'Entry approved and published. Revenue still requires Finance confirmation.':'Entry returned with correction notes. The author has been notified.']);
 }
}
