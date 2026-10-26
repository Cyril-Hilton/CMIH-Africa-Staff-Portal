<?php
namespace App\Services;
use App\Models\WeeklyConsolidatedItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WeeklyReportingHistory
{
 public function record(WeeklyConsolidatedItem $item,string $action,?string $reason=null):void {
  DB::table('weekly_report_versions')->insert(['weekly_item_id'=>$item->id,'actor_id'=>auth()->id(),'action'=>$action,'reason'=>$reason,'snapshot'=>json_encode($item->getOriginal(),JSON_THROW_ON_ERROR),'created_at'=>now()]);
 }
 public function validateUpdate(WeeklyConsolidatedItem $item,array $values,?string $reason):void {
  foreach(['week_start','week_end'] as $field)if(array_key_exists($field,$values)&&CarbonImmutable::parse($values[$field])->toDateString()!==$item->$field?->toDateString())throw ValidationException::withMessages([$field=>'This reporting period is permanent. Extend the project end date or use Copy to New Week instead.']);
  if(($item->week_end?->isPast()||in_array($item->report_status,['submitted','reviewed']))&&!trim($reason??''))throw ValidationException::withMessages(['correction_reason'=>'Explain this correction so the earlier weekly report remains traceable.']);
 }
 public function carry(WeeklyConsolidatedItem $source,string $week,?int $actor=null):WeeklyConsolidatedItem {
  $start=CarbonImmutable::parse($week)->startOfWeek();$end=$start->endOfWeek();
  if($start->lte(CarbonImmutable::parse($source->week_start)->startOfWeek()))throw ValidationException::withMessages(['week'=>'Choose a later reporting week.']);
  if($start->gt(now()->startOfWeek()))throw ValidationException::withMessages(['week'=>'Future reporting drafts open when their week begins.']);
  return DB::transaction(function()use($source,$start,$end,$actor){
   $source=WeeklyConsolidatedItem::lockForUpdate()->findOrFail($source->id);
   if(!$source->report_series){$source->report_series=(string)Str::uuid();$source->save();}
   $existing=WeeklyConsolidatedItem::where('report_series',$source->report_series)->whereDate('week_start',$start->toDateString())->first();if($existing)return $existing;
   $row=WeeklyConsolidatedItem::create(['department'=>$source->department,'week_start'=>$start->toDateString(),'week_end'=>$end->toDateString(),'client_name'=>$source->client_name,'campaign_name'=>$source->campaign_name,'lead_staff_id'=>$source->lead_staff_id,'supporting_staff_ids'=>$source->supporting_staff_ids,'supporting_roles'=>$source->supporting_roles,'objective'=>$source->objective,'deliverables'=>'','status'=>in_array($source->status,['In Progress','Blocked','Deferred'],true)?$source->status:'Planned','progress_percent'=>0,'priority'=>$source->priority,'created_by'=>$actor??$source->created_by,'updated_by'=>$actor??$source->updated_by,'workflow_state'=>'legacy']);
   $row->forceFill(['report_series'=>$source->report_series,'project_starts_on'=>$source->project_starts_on??$source->week_start,'project_ends_on'=>$source->project_ends_on,'carried_from_id'=>$source->id,'report_status'=>'draft','previous_week_reference'=>['id'=>$source->id,'week_start'=>$source->week_start->toDateString(),'status'=>$source->status,'progress_percent'=>$source->progress_percent]])->save();
   $this->record($row,'carried_forward','New weekly draft; previous results and approvals were not copied.');return $row;
  });
 }
 public function rollForward():int {
  $count=0;$week=now()->startOfWeek()->toDateString();
  WeeklyConsolidatedItem::where('workflow_state','legacy')->whereNotNull('project_ends_on')->whereDate('project_ends_on','>=',$week)->where(fn($q)=>$q->whereNull('project_starts_on')->orWhereDate('project_starts_on','<=',now()->endOfWeek()->toDateString()))->whereDate('week_start','<',$week)->whereNotIn('status',['Done','Deferred'])->orderByDesc('week_start')->get()->unique(fn($r)=>$r->report_series??'item-'.$r->id)->each(function($row)use(&$count,$week){$before=WeeklyConsolidatedItem::count();$this->carry($row,$week);$count+=WeeklyConsolidatedItem::count()-$before;});return $count;
 }
 public function summary(string $department,string $from,string $to):array {
  $rows=WeeklyConsolidatedItem::whereIn('department',User::departmentAliases($department))->where('workflow_state','legacy')->whereDate('week_start','>=',$from)->whereDate('week_start','<=',$to)->get();
  $series=WeeklyConsolidatedItem::whereIn('department',User::departmentAliases($department))->where('workflow_state','legacy')->whereNotNull('report_series')->whereNotNull('project_ends_on')->orderByDesc('week_start')->get()->unique('report_series');
  $missing=0;
  foreach($series as $project){
   $periodStart=CarbonImmutable::parse($from)->startOfWeek();if($periodStart->toDateString()<$from)$periodStart=$periodStart->addWeek();
   $start=CarbonImmutable::parse($project->project_starts_on??$project->week_start)->startOfWeek()->max($periodStart);
   $last=CarbonImmutable::parse($project->project_ends_on)->startOfWeek()->min(CarbonImmutable::parse($to)->startOfWeek())->min(CarbonImmutable::now()->startOfWeek());
   $weeks=WeeklyConsolidatedItem::where('report_series',$project->report_series)->pluck('week_start')->map(fn($day)=>CarbonImmutable::parse($day)->startOfWeek()->toDateString())->all();
   for($day=$start;$day->lte($last);$day=$day->addWeek())if(!in_array($day->toDateString(),$weeks,true))$missing++;
  }
  return ['entries'=>$rows->count(),'projects'=>$rows->whereNotNull('report_series')->unique('report_series')->count(),'unlinked_history'=>$rows->whereNull('report_series')->count(),'work'=>$rows->sortByDesc('week_start')->unique(fn($r)=>$r->report_series??'legacy-'.$r->id)->countBy('status')->all(),'weekly_work'=>$rows->countBy('status')->all(),'reports'=>$rows->countBy('report_status')->all(),'missing'=>$missing];
 }
}
