<?php
namespace App\Services\Creative;
use App\Models\{CreativeRequest,User,LeaveApplication};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class CreativeCapacity {
 public function workingDates(Carbon $start,Carbon $end,?int $user=null):array {
  $days=[];$leave=[];
  if($user)foreach(LeaveApplication::where('user_id',$user)->where('status','approved')->whereDate('start_date','<=',$end)->whereDate('end_date','>=',$start)->get() as $l){for($d=$l->start_date->copy()->max($start)->startOfDay();$d->lte($l->end_date->copy()->min($end));$d->addDay())$leave[]=$d->toDateString();}
  for($d=$start->copy()->timezone(config('creative.timezone'))->startOfDay(),$last=$end->copy()->timezone(config('creative.timezone'))->startOfDay();$d->lte($last);$d->addDay())if(!$d->isWeekend()&&!in_array($d->toDateString(),config('company_calendar.non_working_dates',[]))&&!in_array($d->toDateString(),$leave))$days[]=$d->toDateString();
  return $days;
 }
 public function hours(CreativeRequest $r,Carbon $from,Carbon $to,bool $remaining=false):?float {
  if(!$r->planned_start||!$r->internal_eta||$r->estimated_hours===null)return null;
  if($remaining && in_array($r->stage,['backlog','completed','archived','delivered','in_review']))return 0;
  $days=$this->workingDates($r->planned_start,$r->internal_eta,$r->assignee_id);if(!$days)return 0;
  $first=$from->toDateString();$last=$to->toDateString();if($remaining)$first=max($first,today(config('creative.timezone'))->toDateString());
  return round($r->estimated_hours*count(array_filter($days,fn($d)=>$d>=$first&&$d<=$last))/count($days),2);
 }
 public function available(int $user,Carbon $from,Carbon $to):?float {
  $base=DB::table('creative_capacities')->where('user_id',$user)->value('weekly_hours');
  $exceptions=DB::table('creative_capacity_exceptions')->where('user_id',$user)->where('starts_on','<=',$to->toDateString())->where('ends_on','>=',$from->toDateString())->orderByDesc('id')->get();
  $total=0;foreach($this->workingDates($from,$to,$user) as $date){$exception=$exceptions->first(fn($e)=>$e->starts_on<=$date&&$e->ends_on>=$date);$hours=$exception?->weekly_hours??$base;if($hours===null)return null;$total+=(float)$hours/5;}return round($total,2);
 }
 public function summary(User $actor,Carbon $from,Carbon $to):array {
  $staff=User::whereIn('department',User::departmentAliases('creatives'))->where('status','active')->get();
  $requests=CreativeRequest::whereNotNull('assignee_id')->whereDate('planned_start','<=',$to)->whereDate('internal_eta','>=',$from)->get();
  $capacities=DB::table('creative_capacities')->pluck('weekly_hours','user_id');$nominal=count($this->workingDates($from,$to));
  return $staff->map(function($u)use($requests,$capacities,$from,$to,$nominal,$actor){$own=$requests->where('assignee_id',$u->id);$capacity=$capacities->get($u->id);$available=$this->available($u->id,$from,$to);$planned=$own->sum(fn($r)=>$this->hours($r,$from,$to)??0);$remaining=$own->sum(fn($r)=>$this->hours($r,$from,$to,true)??0);return ['id'=>$u->id,'name'=>$u->name,'weekly_capacity'=>$capacity===null?null:(float)$capacity,'available'=>$available,'planned'=>round($planned,2),'remaining'=>round($remaining,2),'overload'=>$available===null?null:max(0,round($remaining-$available,2)),'exceptions'=>DB::table('creative_capacity_exceptions')->where('user_id',$u->id)->where('ends_on','>=',$from->toDateString())->orderBy('starts_on')->get(['starts_on','ends_on','weekly_hours'])->all(),'restricted_assignments'=>$own->filter(fn($r)=>!CreativeAccess::view($actor,$r))->count()];})->all();
 }
 public function reviewDue(Carbon $start):Carbon {$d=$start->copy()->timezone(config('creative.timezone'));$count=0;while($count<(int)config('creative.review_business_days',2)){$d->addDay();if(!$d->isWeekend()&&!in_array($d->toDateString(),config('company_calendar.non_working_dates',[])))$count++;}return $d->utc();}
 public function warnings(CreativeRequest $r):array {
  $warnings=[];if($r->internal_eta && $r->requested_deadline && $r->internal_eta->gt($r->requested_deadline))$warnings[]='Internal ETA is later than the requested deadline. Agree the difference with the requester.';
  if(!$r->assignee_id||!$r->planned_start||!$r->internal_eta)return $warnings;

  for($week=$r->planned_start->copy()->startOfWeek();$week->lte($r->internal_eta);$week->addWeek()){$end=$week->copy()->endOfWeek();$available=$this->available($r->assignee_id,$week,$end);if($available===null){$warnings[]=$week->format('d M').' week: capacity has not been recorded for this creative.';continue;}$others=CreativeRequest::where('assignee_id',$r->assignee_id)->whereKeyNot($r->id)->whereDate('planned_start','<=',$end)->whereDate('internal_eta','>=',$week)->get();$load=$others->sum(fn($o)=>$this->hours($o,$week,$end,true)??0)+($this->hours($r,$week,$end,true)??0);if($load>$available)$warnings[]=$week->format('d M').' week: '.round($load,1).'h remaining against '.round($available,1).'h available.';}
  return $warnings;
 }
}
