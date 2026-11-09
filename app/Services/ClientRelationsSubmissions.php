<?php
namespace App\Services;
use App\Models\{CrmReportSubmission,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientRelationsSubmissions {
 public static function manager(User $u): bool { return ClientRelationsPipeline::member($u) && $u->isLineManager(); }
 public static function needed(Request $r): bool { return !$r->attributes->get('crm_approved_submission') && !self::manager($r->user()); }
 public function submit(Request $r,string $type,?int $id,array $payload) {
  $v=$r->validate(['submission_id'=>'nullable|integer','submission_revision'=>'required_with:submission_id|integer|min:1']);
  $item=DB::transaction(function()use($r,$type,$id,$payload,$v){
   if(!empty($v['submission_id'])) {
    $item=CrmReportSubmission::lockForUpdate()->findOrFail($v['submission_id']);
    abort_unless((int)$item->submitted_by===$r->user()->id,403);
    abort_unless($item->status==='returned',409,'Only returned entries can be resubmitted.');
    abort_unless($item->entity_type===$type && (int)$item->entity_id===(int)$id,422);
    abort_if((int)$item->revision!==(int)$v['submission_revision'],409,'This entry changed. Refresh before resubmitting.');
    $before=$item->getAttributes(); $item->revision++;
   } else {
    abort_if(CrmReportSubmission::where('submitted_by',$r->user()->id)->where('entity_type',$type)->when($id,fn($q)=>$q->where('entity_id',$id),fn($q)=>$q->whereNull('entity_id'))->where('status','pending')->get()->contains(fn($s)=>$id || $s->payload===$payload),409,'This entry already awaits review.');
    $item=new CrmReportSubmission(['entity_type'=>$type,'entity_id'=>$id,'submitted_by'=>$r->user()->id]); $before=[];
   }
   $item->payload=$payload; $item->status='pending'; $item->reviewed_at=null; $item->reviewed_by=null; $item->save();
   PerformanceWorkflow::record('crm_submission',$item->id,$r->user()->id,'submit',$before,$item->getAttributes(),'Submitted for Client Relations manager review.');
   User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases('client_relations'))->get()->filter(fn($u)=>self::manager($u))->each(fn($u)=>PerformanceWorkflow::notify($u->id,'crm-submission:'.$item->id.':'.$item->revision,'Client Relations entry awaiting review',$r->user()->name.' submitted an entry. Review it on the weekly dashboard.',route('dashboard',['weekly_department'=>'client_relations']).'#staff-weekly-performance'));
   return $item;
  });
  $message='Entry submitted for manager review. It will appear on the shared dashboard after approval.';
  return $r->expectsJson()?response()->json(['message'=>$message,'submission_id'=>$item->id],202):back()->with('status',$message);
 }
}
