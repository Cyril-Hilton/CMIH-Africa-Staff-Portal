<?php
namespace App\Services;
use App\Models\{Asset,HrDashboardActivity,HrRegisterRecord,PerformanceRegister,PerformanceTargetPlan,User};
use Illuminate\Support\Carbon;
/** Register statistics describe the business; only measurable results against obligations earn KPI credit. */
class HrRegisterKpis {
 public function build(string $from,string $to,?int $owner=null): array {
  $current=$from<=today()->toDateString()&&$to>=today()->toDateString();$cutoff=min($to,today()->toDateString());
  $rows=HrRegisterRecord::whereDate('created_at','<=',$to)->when($owner,fn($q)=>$q->where(fn($q)=>$q->where('created_by',$owner)->orWhere('updated_by',$owner)))->get();
  $activities=HrDashboardActivity::whereDate('opened_on','<=',$cutoff)->when($owner,fn($q)=>$q->where('assigned_to',$owner))->get();
  $staff=User::internalStaff()->where('status','active')->where(fn($q)=>$q->whereNull('start_date')->orWhereDate('start_date','<=',today()))->get();$areas=[];$metrics=[];
  $measure=function($name,$tab,$target,$actual,$note)use(&$metrics){$metrics[]=['name'=>$name,'area'=>config('hr_workspace.tabs.'.$tab.'.label'),'target'=>$target,'actual'=>$actual,'unit'=>'records','expected'=>$target===null?0:(int)$target,'reported'=>$actual===null?0:(int)$target,'achievement'=>$target!==null&&$target>0?round(100*($actual??0)/$target,2):null,'note'=>$note,'state'=>$target===null?'Target / evidence setup needed · 0 credit':'Measured against recorded obligations'];};
  $legacy=PerformanceRegister::whereIn('department',['hr_admin','admin_drivers'])->whereNotNull('approved_at')->where('status','!=','cancelled')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)->when($owner,fn($q)=>$q->where('owner_id',$owner))->get();
  $attendanceUsers=User::internalStaff()->where(fn($q)=>$q->where('status','active')->orWhereHas('attendances',fn($a)=>$a->whereBetween('clock_in_at',[$from.' 00:00:00',$to.' 23:59:59'])))->get()->reject(fn($u)=>$u->isCvoOrSuperAdmin()||$u->isServiceAccount());
  $expectedDays=$clockedDays=0;
  foreach($attendanceUsers as $person){$a=PerformanceScoringService::attendanceSummary($person,Carbon::parse($from),Carbon::parse($to));$expectedDays+=$a['expected_workdays'];$clockedDays+=$a['attendance_days'];}
  $attendanceIds=$attendanceUsers->pluck('id');
  $clockInRecords=\App\Models\Attendance::whereIn('user_id',$attendanceIds)->whereBetween('clock_in_at',[$from.' 00:00:00',$to.' 23:59:59'])->count();
  $nativeLeaves=\App\Models\LeaveApplication::whereIn('user_id',$attendanceIds)->whereDate('start_date','<=',$to)->whereDate('end_date','>=',$from)->count();
  $nativeAppraisals=\App\Models\Appraisal::whereDate('created_at','<=',$to)->where(fn($q)=>$q->whereDate('created_at','>=',$from)->orWhereNotIn('status',['approved']))->count();
  $nativeFleet=\App\Models\FleetRequest::whereBetween('requested_date',[$from,$to])->count();
  foreach(config('hr_workspace.tabs') as $tab=>$schema){
   $kinds=collect(config('hr_workspace.registers'))->filter(fn($s)=>$s['tab']===$tab)->keys()->all();$evidence=$rows->whereIn('kind',$kinds)->count();
   $work=$activities->whereIn('tab',$tab==='facilities'?['facilities','operations']:[$tab]);$due=$work->filter(fn($a)=>$a->deadline&&$a->deadline>=$from&&$a->deadline<=$cutoff&&$a->status!=='Cancelled');
   $evidence+=$work->count();
   $evidence+=match($tab){'attendance'=>$clockInRecords+$nativeLeaves,'performance'=>$nativeAppraisals,'fleet'=>$nativeFleet,default=>0};
   if($current&&!$owner){if($tab==='people')$evidence+=$staff->count();if($tab==='supplies')$evidence+=\App\Models\ThirdPartyVendor::count();if($tab==='assets')$evidence+=Asset::where(fn($q)=>$q->where('is_warehouse_tracked',false)->orWhereNull('is_warehouse_tracked'))->count();}

   $legacyKinds=match($tab){'people','attendance'=>['workforce'],'recruitment'=>['vacancy'],'performance'=>['workforce','improvement_plan'],'relations'=>['employee_case'],'fleet'=>['vehicle','fuel','trip'],'facilities'=>['maintenance','facility_request'],'assets'=>['asset'],default=>[]};$evidence+=$legacy->whereIn('kind',$legacyKinds)->count();
   $areas[]=['name'=>$schema['label'],'records'=>$evidence,'reported'=>$evidence>0,'score'=>$evidence?100.0:0.0,'state'=>$evidence?'Source records available':'Missing source evidence','gap'=>$evidence?null:'No register or activity evidence for this reporting area.'];
   $onTime=$due->filter(fn($a)=>$a->status==='Completed'&&$a->completed_at&&$a->completed_at->toDateString()<=$a->deadline)->count();
   $measure($schema['label'].' · on-time activity delivery',$tab,$due->count()?:null,$due->isEmpty()?null:$onTime,'Activities completed by their recorded deadline / all non-cancelled activities due. Future deadlines and unconfirmed historical completion dates receive no completion credit.');
  }
  $measure('Company attendance coverage','attendance',$expectedDays?:null,$expectedDays?$clockedDays:null,'Recorded clock-in staff-days / expected Monday–Friday staff-days across all internal departments. Approved leave, holidays, future and pre-employment days are excluded. Uses native attendance logs for current and historical periods; HR does not re-enter attendance. Individual punctuality remains in the separate 10% component.');
  $dueRows=fn($kind,$field)=>$rows->where('kind',$kind)->filter(fn($r)=>!empty($r->data[$field])&&substr($r->data[$field],0,10)>=$from&&substr($r->data[$field],0,10)<=$cutoff);
  $vacancies=$dueRows('vacancy','target_fill_on')->where('data.state','!=','Cancelled');
  if($vacancies->isNotEmpty())$measure('On-time vacancy filling','recruitment',$vacancies->count(),$vacancies->filter(fn($r)=>($r->data['state']??'')==='Filled'&&!empty($r->data['filled_on'])&&$r->data['filled_on']<=$r->data['target_fill_on'])->count(),'Vacancies filled by their recorded target fill date / vacancies due. An open vacancy or missing fill date earns zero credit.');
  $probation=$dueRows('probation','confirmation_due');
  if($probation->isNotEmpty())$measure('On-time probation confirmations','recruitment',$probation->count(),$probation->filter(fn($r)=>!empty($r->data['confirmed_on'])&&$r->data['confirmed_on']<=$r->data['confirmation_due'])->count(),'Confirmations recorded by the confirmation due date / probation reviews due.');
  $reviews=$dueRows('appraisal_record','due_on');
  if($reviews->isNotEmpty())$measure('On-time registered appraisals','performance',$reviews->count(),$reviews->filter(fn($r)=>!empty($r->data['completed_on'])&&$r->data['completed_on']<=$r->data['due_on'])->count(),'Appraisals completed by their recorded due date / appraisal register entries due. Portal appraisals without a recorded deadline remain descriptive records.');
  $training=$dueRows('training','date')->filter(fn($r)=>isset($r->data['required_completions'])&&$r->data['required_completions']>0);
  if($training->isNotEmpty())$measure('Training completion','performance',$training->sum('data.required_completions'),$training->sum(fn($r)=>min($r->data['required_completions'],$r->data['completed']??0)),'Completed training obligations / recorded required completions for trainings held in period. Missing completion results earn zero credit.');
  if($current){
   $docs=$rows->where('kind','staff_document')->filter(fn($r)=>$r->attachment&&!empty($r->data['received_on'])&&$r->data['received_on']<=today()->toDateString());$known=0;
   foreach($staff as $s){$present=$docs->filter(fn($r)=>(int)($r->data['staff_id']??0)===$s->id)->pluck('data.document_type')->all();if(!$owner){if(app(HrStaffRecord::class)->document($s,'contract_path'))$present[]='Signed contract';if(app(HrStaffRecord::class)->document($s,'job_description_path'))$present[]='Signed JD';if(app(HrStaffRecord::class)->document($s,'national_id_front_path')||app(HrStaffRecord::class)->document($s,'ghana_card_path'))$present[]='ID';}$known+=count(array_intersect(['CV','Referees','ID','Certificates','Signed contract','Signed JD','Handbook acknowledgement'],array_unique($present)));}
   $measure('Staff document completeness','people',$staff->count()?7*$staff->count():null,$staff->isEmpty()?null:$known,'Required documents received / seven required document types per active staff member. A filename or upload alone does not count without a received date; existing signed contracts, JDs and IDs are reused. Current snapshot.');
   $plans=PerformanceTargetPlan::whereNotNull('approved_at')->whereNull('withdrawn_at')->whereNotNull('target')->where('weight','>',0)->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)->whereIn('owner_id',$staff->pluck('id'))->when($owner,fn($q)=>$q->where('approved_by',$owner))->distinct()->pluck('owner_id')->count();
   // An employee is covered only by approved weighted KPI targets, never by a manually entered setup note.
   if(!$owner)$measure('Staff with approved KPI targets','performance',$staff->count()?:null,$staff->isEmpty()?null:$plans,'Active staff with approved, weighted KPI targets / active staff. Current-period targets only.');
   $vehicles=$rows->where('kind','vehicle')->where('data.state','!=','Retired');$valid=0;foreach($vehicles as $v)foreach(['insurance_expiry','roadworthy_expiry'] as $f)if(!empty($v->data[$f])&&$v->data[$f]>=today()->toDateString())$valid++;
   if($vehicles->isNotEmpty())$measure('Fleet document validity','fleet',2*$vehicles->count(),$valid,'Unexpired insurance and roadworthy records / two required documents per active registered vehicle. Missing or expired documents earn zero credit.');
   $supplies=$rows->where('kind','supply');if($supplies->isNotEmpty())$measure('Supplies at reorder level or above','supplies',$supplies->count(),$supplies->filter(fn($r)=>isset($r->data['quantity'],$r->data['reorder_level'])&&$r->data['quantity']>=$r->data['reorder_level'])->count(),'Supplies at or above their recorded reorder level / all supply lines. Missing quantities or reorder levels earn zero credit.');
   $handbook=$owner?$docs->where('data.document_type','Handbook acknowledgement')->filter(fn($r)=>$staff->contains('id',(int)($r->data['staff_id']??0)))->unique(fn($r)=>$r->data['staff_id'])->count():app(HrLiveRegisters::class)->handbookSigned($staff->pluck('id'));$measure('Handbook acknowledgement coverage','compliance',$staff->count()?:null,$staff->isEmpty()?null:$handbook,'Active staff with a received signed handbook acknowledgement / active staff. Current snapshot.');
  }
  $metrics=collect($metrics)->reject(fn($m)=>$m['target']===null&&str_contains($m['name'],'on-time activity')&&collect($metrics)->contains(fn($other)=>$other['area']===$m['area']&&$other['target']!==null))->values()->all();
  return compact('areas','metrics');
 }
}
