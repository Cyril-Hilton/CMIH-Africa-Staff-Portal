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
   $row=WeeklyConsolidatedItem::create(['department'=>$source->department,'week_start'=>$start->toDateString(),'week_end'=>$end->toDateString(),'client_name'=>$source->client_name,'campaign_name'=>$source->campaign_name,'lead_staff_id'=>$source->lead_staff_id,'supporting_staff_ids'=>$source->supporting_staff_ids,'supporting_roles'=>$source->supporting_roles,'objective'=>$source->objective,'target_breakdown'=>$source->target_breakdown,'kpi_name'=>$source->kpi_name,'measurement_unit'=>$source->measurement_unit,'measurement_direction'=>$source->measurement_direction,'target_value'=>$source->target_value,'custom_fields'=>$this->copyColumns($source,$actor??$source->created_by),'deliverables'=>'','status'=>in_array($source->status,['In Progress','Blocked','Deferred'],true)?$source->status:'Planned','progress_percent'=>0,'priority'=>$source->priority,'created_by'=>$actor??$source->created_by,'updated_by'=>$actor??$source->updated_by,'workflow_state'=>'legacy']);
   $row->forceFill(['report_series'=>$source->report_series,'project_starts_on'=>$source->project_starts_on??$source->week_start,'project_ends_on'=>$source->project_ends_on,'carried_from_id'=>$source->id,'report_status'=>'draft','previous_week_reference'=>['id'=>$source->id,'week_start'=>$source->week_start->toDateString(),'status'=>$source->status,'progress_percent'=>$source->progress_percent,'content'=>['deliverables'=>$source->deliverables,'target_breakdown'=>$source->target_breakdown,'achieved_breakdown'=>$source->achieved_breakdown,'gap_breakdown'=>$source->gap_breakdown,'custom_fields'=>$source->custom_fields]]])->save();
   $this->record($row,'carried_forward','New weekly draft; previous results and approvals were not copied.');return $row;
  });
 }
 private function copyColumns(WeeklyConsolidatedItem $source,int $actor):array {
  $values=[];
  foreach(\App\Models\WeeklyConsolidatedColumn::where('user_id',$source->created_by)->whereIn('department',User::departmentAliases($source->department))->get() as $column){
   $existing=\App\Models\WeeklyConsolidatedColumn::where('user_id',$actor)->where('department',$source->department)->where('column_key',$column->column_key)->first();
   $key=$column->column_key;
   if($existing && ($existing->label!==$column->label||$existing->type!==$column->type))$key=$key.'_'.substr(hash('sha256',$column->id.'|'.$column->label),0,8);
   $target=\App\Models\WeeklyConsolidatedColumn::firstOrCreate(['user_id'=>$actor,'department'=>$source->department,'column_key'=>$key],['label'=>$column->label,'type'=>$column->type,'order'=>$column->order]);
   // Outcomes and evidence are references, never new weekly achievements.
   $values[$target->column_key]=preg_match('/actual|achiev|result|progress|evidence|complete|revenue|receipt|invoice|paid|amount|performance|gap/i',$column->label)?null:($source->custom_fields[$column->column_key]??null);
  }
  return $values;
 }

 public function weekSources(string $department,string $week) {
  $start=CarbonImmutable::parse($week)->startOfWeek();
  return WeeklyConsolidatedItem::where('workflow_state','legacy')->whereIn('department',User::departmentAliases($department))->whereBetween('week_start',[$start->toDateString(),$start->endOfWeek()->toDateString()])->orderBy('id');
 }

 public function copyWeek(string $department,string $sourceWeek,string $targetWeek,array $ids,int $actor):array {
  $target=CarbonImmutable::parse($targetWeek)->startOfWeek();$source=CarbonImmutable::parse($sourceWeek)->startOfWeek();
  if($target->lte($source)||$target->gt(CarbonImmutable::now()->startOfWeek()))throw ValidationException::withMessages(['target_week'=>'Choose a later week that has already begun.']);
  return DB::transaction(function()use($department,$sourceWeek,$target,$ids,$actor){
   $rows=$this->weekSources($department,$sourceWeek)->whereIn('id',$ids)->lockForUpdate()->get();
   if($rows->count()!==count(array_unique($ids)))throw ValidationException::withMessages(['items'=>'Some selected activities no longer belong to this source week. Preview again.']);
   $created=0;$existing=0;
   foreach($rows as $row){$prior=$row->report_series?WeeklyConsolidatedItem::where('report_series',$row->report_series)->whereDate('week_start',$target->toDateString())->first():null;
    $this->carry($row,$target->toDateString(),$actor);$prior?$existing++:$created++;
   }
   return compact('created','existing');
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
