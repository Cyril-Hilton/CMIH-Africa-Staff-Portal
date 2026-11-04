<?php
namespace App\Services;
use App\Models\{User,BrandsReportSubmission,WeeklyConsolidatedItem,PerformanceRegister};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\{Rule,ValidationException};
class BrandsReports {
 public static function member(User $u):bool{return $u->isActive()&&!$u->isServiceAccount()&&(User::normalizeDepartmentKey($u->department)==='brands_marketing'||$u->isCvoOrSuperAdmin());}
 public static function manager(User $u):bool{return self::member($u)&&PerformanceAccess::manages($u,'brands_marketing');}
 public static function token($record):string{return hash('sha256',json_encode($record->getAttributes(),JSON_THROW_ON_ERROR));}
 public function source(string $type,int $id){$item=$type==='weekly'?WeeklyConsolidatedItem::findOrFail($id):PerformanceRegister::findOrFail($id);abort_unless(User::normalizeDepartmentKey($item->department)==='brands_marketing',404);return $item;}
 public function payload($item,string $type):array {
  if($type==='weekly')return ['category'=>'workstream','title'=>$item->client_name?:$item->campaign_name?:'Weekly activity','owner_id'=>$item->lead_staff_id??$item->created_by,'week'=>$item->week_start->toDateString(),'data'=>['project'=>$item->campaign_name,'brand'=>$item->custom_fields['brand']??null,'task_in_week'=>$item->deliverables,'collaborators'=>$item->supporting_staff_ids??[],'collaborator_roles'=>$item->supporting_roles??[],'priority'=>$item->priority??'Medium','status'=>$item->status??'Planned','project_start'=>$item->project_starts_on?->toDateString(),'project_end'=>$item->project_ends_on?->toDateString(),'kpis'=>$item->target_breakdown,'results'=>$item->achieved_breakdown,'gaps'=>$item->gap_breakdown,'notes'=>$item->notes]];
  $kind=$item->kind; $data=$item->data??[];
  if($kind==='campaign'){$kind='perfect_store';$data=['partner'=>in_array($data['partner']??null,['castel','unilever'],true)?$data['partner']:(preg_match('/castel/i',$data['brand']??$item->client??'')?'castel':(preg_match('/unilever/i',$data['brand']??$item->client??'')?'unilever':null)),'breakdown'=>!empty($data['region'])?'region':'overall','location'=>$data['region']??null]+array_intersect_key($data,config('brands_dashboard.schemas.perfect_store'));}
  return ['category'=>$kind,'title'=>$item->title,'owner_id'=>$item->owner_id,'week'=>$item->period_start->toDateString(),'data'=>array_intersect_key($data,config('brands_dashboard.schemas.'.$kind,[]))];
 }
 public function submit(Request $r){
  abort_unless(self::member($r->user()),403);
  $v=$r->validate(['category'=>['required',Rule::in(array_keys(config('brands_dashboard.schemas')))],'title'=>'required|string|min:3|max:255','owner_id'=>'required|integer|exists:users,id','week'=>'required|date_format:Y-m-d','source_type'=>'required_with:source_id|nullable|in:weekly,register','source_id'=>'nullable|integer|min:1','source_token'=>'required_with:source_id|nullable|string|size:64','submission_id'=>'nullable|integer|min:1','revision'=>'required_with:submission_id|nullable|integer|min:1','reason'=>'required|string|min:5|max:2000','data'=>'required|array']);
  abort_unless(($v['source_type']??($v['category']==='workstream'?'weekly':'register'))===($v['category']==='workstream'?'weekly':'register'),422,'Choose the matching report category.');
  $source=!empty($v['source_id'])?$this->source($v['source_type']??($v['category']==='workstream'?'weekly':'register'),$v['source_id']):null;
  if($source&&($v['source_type']??'')==='weekly')abort_unless($source->workflow_state==='legacy',422,'Use the delivery workflow to update a verified commitment.');
  if($source){$existing=$this->payload($source,$v['source_type']);abort_unless($existing['category']===$v['category'],422);abort_if($existing['week']!==$v['week'],422,'The reporting week is permanent. Create a new weekly entry instead.');abort_unless(self::manager($r->user())||(int)$existing['owner_id']===$r->user()->id||(int)($source->submitted_by??$source->created_by)===$r->user()->id,403);abort_unless(hash_equals(self::token($source),$v['source_token']),409,'This report changed. Refresh before submitting corrections.');}
  $owner=User::findOrFail($v['owner_id']);abort_unless((User::normalizeDepartmentKey($owner->department)==='brands_marketing'&&$owner->isActive())||($source&&(int)$existing['owner_id']===$owner->id),422,'Choose an active Brands & Marketing owner.');
  if(!self::manager($r->user()))abort_unless($owner->id===$r->user()->id,403);
  $rules=[];foreach(config('brands_dashboard.schemas.'.$v['category']) as $key=>$field){$rules['data.'.$key]=match($field['type']){'number'=>'nullable|numeric|min:0|max:999999999999','date'=>'nullable|date_format:Y-m-d','select'=>['nullable',Rule::in(array_keys($field['options']))],'staff'=>'nullable|array|max:30',default=>'nullable|string|max:15000'};if($field['type']==='staff')$rules['data.'.$key.'.*']='integer|exists:users,id';}
  $data=$r->validate($rules)['data']??[];$data=array_intersect_key($data,config('brands_dashboard.schemas.'.$v['category']));
  foreach($data as $key=>$value){if(str_ends_with($key,'_percent')&&$value!==null&&(float)$value>100)throw ValidationException::withMessages(['data.'.$key=>'Use a percentage from 0 to 100.']);if(in_array($key,['planned_count','active_count','attrition_count','leave_count','absent_count','planned_scope','active_scope','scheduled','deployed','target_reach','total_reach','conversions','conversion_base'],true)&&$value!==null&&floor((float)$value)!=(float)$value)throw ValidationException::withMessages(['data.'.$key=>'Use a whole number of people or items.']);}
  foreach([['project_start','project_end'],['brief_date','revert_date'],['brief_date','closed_on']] as [$a,$b])if(!empty($data[$a])&&!empty($data[$b])&&$data[$b]<$data[$a])throw ValidationException::withMessages(['data.'.$b=>'The end date cannot be before the start date.']);
  if(!empty($data['collaborators']))abort_unless(User::internalStaff()->where('status','active')->whereIn('id',$data['collaborators'])->count()===count(array_unique($data['collaborators'])),422,'Choose active Brands collaborators.');
  if($v['category']==='workstream'&&trim(html_entity_decode(strip_tags($data['task_in_week']??'')))==='')throw ValidationException::withMessages(['data.task_in_week'=>'Describe the work for this reporting week.']);
  foreach(match($v['category']){'people'=>['cohort'],'perfect_store'=>['partner','breakdown'],'activation'=>['partner','brief_status','brief_date'],'visibility'=>['partner','posm_type'],'workstream'=>['project','task_in_week','status'],default=>[]} as $required)if(!filled($data[$required]??null))throw ValidationException::withMessages(['data.'.$required=>'Complete '.config('brands_dashboard.schemas.'.$v['category'].'.'.$required.'.label').'.']);
  if($v['category']==='perfect_store'&&($data['breakdown']??'overall')!=='overall'&&!filled($data['location']??null))throw ValidationException::withMessages(['data.location'=>'Name the outlet, region or distributor.']);
  if($v['category']==='activation'&&(isset($data['target_sales'])||isset($data['total_sales']))&&!filled($data['sales_unit']??null))throw ValidationException::withMessages(['data.sales_unit'=>'Record the sales unit so the result can be interpreted correctly.']);
  $payload=collect($v)->only(['category','title','owner_id','week','reason'])->all()+['data'=>$data];
  $submission=DB::transaction(function()use($r,$v,$payload){
   if(!empty($v['submission_id'])){$s=BrandsReportSubmission::lockForUpdate()->findOrFail($v['submission_id']);abort_unless($s->submitted_by===$r->user()->id,403);abort_if($s->status!=='returned'||$s->revision!==(int)$v['revision'],409,'This submission changed. Refresh before resubmitting.');abort_unless($s->source_type===($v['source_type']??($v['category']==='workstream'?'weekly':'register'))&&(int)$s->source_id===(int)($v['source_id']??0)&&$s->category===$v['category'],422);$s->revision++;}
   else{$s=new BrandsReportSubmission(['category'=>$v['category'],'source_type'=>$v['source_type']??($v['category']==='workstream'?'weekly':'register'),'source_id'=>$v['source_id']??null,'submitted_by'=>$r->user()->id]);abort_if(BrandsReportSubmission::where('submitted_by',$r->user()->id)->where('status','pending')->where('category',$v['category'])->get()->contains(fn($p)=>($s->source_id&&$p->source_id===$s->source_id&&$p->source_type===$s->source_type)||$p->payload===$payload),409,'This entry is already awaiting review.');}
   $s->payload=$payload;$s->source_token=$v['source_token']??null;$s->status='pending';$s->reviewed_at=null;$s->reviewed_by=null;$s->save();PerformanceWorkflow::record('brands_submission',$s->id,$r->user()->id,'submitted',[],['revision'=>$s->revision,'source_id'=>$s->source_id],$v['reason']);
   User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases('brands_marketing'))->get()->filter(fn($u)=>self::manager($u))->each(fn($u)=>PerformanceWorkflow::notify($u->id,'brands-review:'.$s->id.':'.$s->revision,'Brands report awaiting review',$r->user()->name.' submitted a report. Review it on the Brands weekly dashboard.',route('dashboard',['weekly_department'=>'brands_marketing']).'#staff-weekly-performance'));
   return $s;
  });return response()->json(['message'=>'Entry submitted for manager review. It appears on the shared dashboard after approval.','id'=>$submission->id],202);
 }
 public function review(Request $r,BrandsReportSubmission $submission){
  abort_unless(self::manager($r->user()),403);$v=$r->validate(['revision'=>'required|integer|min:1','decision'=>'required|in:approve,return','note'=>'required_if:decision,return|nullable|string|min:5|max:2000']);
  DB::transaction(function()use($r,$submission,$v){$s=BrandsReportSubmission::lockForUpdate()->findOrFail($submission->id);abort_if($s->status!=='pending'||$s->revision!==(int)$v['revision'],409,'This entry changed. Refresh before reviewing.');$p=$s->payload;
   if($v['decision']==='approve'){
    $old=$s->source_id?$this->source($s->source_type,$s->source_id)->newQuery()->lockForUpdate()->findOrFail($s->source_id):null;
    if($old)abort_unless(hash_equals(self::token($old),$s->source_token??''),409,'The published report changed. Request a fresh correction.');
    $owner=User::findOrFail($p['owner_id']);abort_unless($owner->isActive()||($old&&(int)$this->payload($old,$s->source_type)['owner_id']===$owner->id),422,'The report owner is no longer active. Return the entry for reassignment.');
    $start=Carbon::parse($p['week'])->startOfWeek();$end=$start->copy()->endOfWeek();
    if($s->source_type==='weekly'){
     $data=$p['data'];$values=['department'=>'brands_marketing','client_name'=>$p['title'],'campaign_name'=>$data['project'],'lead_staff_id'=>$p['owner_id'],'supporting_staff_ids'=>$data['collaborators']??[],'deliverables'=>$data['task_in_week'],'target_breakdown'=>$data['kpis']??null,'achieved_breakdown'=>$data['results']??null,'gap_breakdown'=>$data['gaps']??null,'notes'=>$data['notes']??null,'status'=>$data['status'],'priority'=>$data['priority']??'Medium','project_starts_on'=>$data['project_start']??null,'project_ends_on'=>$data['project_end']??null,'updated_by'=>$r->user()->id,'report_status'=>'reviewed','report_submitted_by'=>$s->submitted_by,'report_submitted_at'=>$s->created_at,'report_reviewed_by'=>$r->user()->id,'report_reviewed_at'=>now()];
     if($old){app(WeeklyReportingHistory::class)->record($old,'manager_approved_correction',$p['reason']);$values['custom_fields']=array_replace($old->custom_fields??[],['brand'=>$data['brand']??null]);$old->fill($values);$old->revision++;$old->save();$item=$old;}
     else{$item=WeeklyConsolidatedItem::create($values+['created_by'=>$s->submitted_by,'week_start'=>$start,'week_end'=>$end,'workflow_state'=>'legacy','custom_fields'=>['brand'=>$data['brand']??null]]);}
    }else{
     // Operational publication does not create an independently verified KPI observation.
     $values=['title'=>$p['title'],'owner_id'=>$p['owner_id'],'data'=>array_replace($old?->data??[],$p['data']),'status'=>'submitted','approved_by'=>$r->user()->id,'approved_at'=>now()];
     if($old){abort_if($old->kind==='campaign'&&((int)$old->owner_id===$r->user()->id||(int)$s->submitted_by===$r->user()->id),403,'This earlier KPI source requires an independent reviewer.');$item=$old;$item->fill($values)->save();}
     else $item=PerformanceRegister::create($values+['department'=>'brands_marketing','kind'=>$p['category'],'reference'=>'BR-'.str_pad((string)$s->id,6,'0',STR_PAD_LEFT),'period_start'=>$start,'period_end'=>$end,'submitted_by'=>$s->submitted_by]);
    }
    $s->source_id=$item->id;
   }
   $s->status=$v['decision']==='approve'?'approved':'returned';$s->review_note=$v['note']??null;$s->reviewed_by=$r->user()->id;$s->reviewed_at=now();$s->revision++;$s->save();PerformanceWorkflow::record('brands_submission',$s->id,$r->user()->id,$v['decision'],[],['revision'=>$s->revision,'source_id'=>$s->source_id],$v['note']??'Manager approved publication.');PerformanceWorkflow::notify($s->submitted_by,'brands-result:'.$s->id.':'.$s->revision,$s->status==='approved'?'Brands report published':'Brands report corrections requested','Open My Entries & Reviews on the Brands dashboard to see the decision and correction notes.',route('dashboard',['weekly_department'=>'brands_marketing']).'#staff-weekly-performance');
  });return response()->json(['message'=>$v['decision']==='approve'?'Report approved and published.':'Entry returned with correction notes.']);
 }
}
