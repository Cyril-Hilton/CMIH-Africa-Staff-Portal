<?php
namespace App\Services;
use App\Models\{Asset,HrDashboardActivity,HrRegisterRecord,User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class HrWorkspace {
 public function build(Request $request): array {
  $u=$request->user();$registers=app(HrLiveRegisters::class);$tabs=$registers->tabs($u);abort_if(!$tabs,403);
  $full=HrLiveRegisters::full($u);$default=$full?'overview':array_key_first($tabs);
  $v=validator($request->query(),['hr_period'=>'nullable|in:fortnight,week,month,year,range','hr_from'=>'nullable|date_format:Y-m-d','hr_to'=>'nullable|date_format:Y-m-d|after_or_equal:hr_from','hr_owner'=>'nullable|integer|min:1',
   'hr_q'=>'nullable|string|max:150','hr_status'=>['nullable',Rule::in([...HrDashboardActivity::STATUSES,'Overdue'])],'hr_priority'=>'nullable|in:High,Medium,Low','hr_department'=>['nullable',Rule::in(array_keys(config('performance.departments')))],
   'hr_deadline_from'=>'nullable|date_format:Y-m-d','hr_deadline_to'=>'nullable|date_format:Y-m-d|after_or_equal:hr_deadline_from','hr_sort'=>'nullable|in:activity,deadline,opened_on,status,priority','hr_direction'=>'nullable|in:asc,desc','hr_record_q'=>'nullable|string|max:150','hr_record_focus'=>'nullable|string|regex:/^[a-z_]+:-?[0-9]+$/','hr_activity_focus'=>'nullable|integer|min:1'
  ])->validate();
  $tab=$request->string('hr_tab')->toString()?:$default;if($tab==='operations')$tab='facilities';if(!$full&&$tab==='overview')$tab=$default;if($tab!=='overview' || !$full)abort_unless(isset($tabs[$tab]),403);
  $period=$v['hr_period']??($request->query('report_period')?:($request->query('weekly_from')?'range':'week'));if(!in_array($period,['week','month','year','range','fortnight'],true))$period='week';
  $anchor=Carbon::parse($v['hr_from']??$request->query('report_date')??$request->query('weekly_from')??today());
  [$from,$to]=match($period){'month'=>[$anchor->copy()->startOfMonth(),$anchor->copy()->endOfMonth()],'year'=>[$anchor->copy()->startOfYear(),$anchor->copy()->endOfYear()],'range'=>[$anchor,Carbon::parse($v['hr_to']??$request->query('weekly_to')??$anchor)],'fortnight'=>[$anchor->copy()->subWeek()->startOfWeek(),$anchor->copy()->endOfWeek()],default=>[$anchor->copy()->startOfWeek(),$anchor->copy()->endOfWeek()]};
  abort_if($from->diffInDays($to)>731,422,'Choose a date range of no more than two years.');$from=$from->toDateString();$to=$to->toDateString();
  $owner=(int)($v['hr_owner']??0);$team=HrDashboard::team()->get();if($owner&&!$team->contains('id',$owner))$owner=0;
  $query=HrDashboardActivity::with(['staff:id,name','assignee:id,name'])->whereDate('opened_on','<=',$to)->where(fn($q)=>$q->whereDate('opened_on','>=',$from)->orWhereDate('deadline','>=',$from)->orWhereBetween('completed_at',[$from.' 00:00:00',$to.' 23:59:59'])->orWhereNotIn('status',['Completed','Cancelled']));
  if(!$full)$query->whereIn('staff_id',$registers->staff($u)->pluck('id'))->whereIn('tab',array_keys($tabs));
  if($owner)$query->where(fn($q)=>$q->where('assigned_to',$owner)->orWhere('staff_id',$owner));
  if($tab!=='overview')$query->whereIn('tab',$tab==='facilities'?['facilities','operations']:[$tab]);
  if(!empty($v['hr_q'])){$n='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($v['hr_q'])).'%';$query->where(fn($q)=>$q->whereRaw("LOWER(activity) LIKE ? ESCAPE '!'",[$n])->orWhereRaw("LOWER(remarks) LIKE ? ESCAPE '!'",[$n]));}
  if(($v['hr_status']??'')==='Overdue')$query->whereDate('deadline','<',min($to,today()->toDateString()))->whereNotIn('status',['Completed','Cancelled']);elseif(!empty($v['hr_status']))$query->where('status',$v['hr_status']);
  foreach(['priority'=>'hr_priority','department'=>'hr_department'] as $col=>$key)if(!empty($v[$key]))$query->where($col,$v[$key]);
  if(!empty($v['hr_deadline_from']))$query->whereDate('deadline','>=',$v['hr_deadline_from']);if(!empty($v['hr_deadline_to']))$query->whereDate('deadline','<=',$v['hr_deadline_to']);
  $allActivities=(clone $query)->get();
  $summary=['Open activities'=>$allActivities->whereNotIn('status',['Completed','Cancelled'])->count(),'Completed in period'=>$allActivities->where('status','Completed')->filter(fn($a)=>$a->completed_at&&$a->completed_at->toDateString()>=$from&&$a->completed_at->toDateString()<=$to)->count(),'Overdue'=>$allActivities->filter(fn($a)=>$a->isOverdue(min($to,today()->toDateString())))->count()];
  if(!empty($v['hr_activity_focus']))$query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END',[(int)$v['hr_activity_focus']]);
  $query->orderBy($v['hr_sort']??'deadline',$v['hr_direction']??'asc')->orderBy('id');
  $activities=$query->paginate(15,['*'],'hr_page')->withQueryString();
  $allExportActivities=(clone $query)->reorder()->orderBy('deadline')->get();
  $stats=$tab==='overview'?[]:$registers->stats($tab,$u,$from,$to);
  $groups=$tab==='overview'?[]:$registers->groups($tab,$u);
  $v += app(AssetTableFilters::class)->values($request);
  if(isset($groups['asset'])){$assetIds=app(AssetTableFilters::class)->apply(\App\Models\Asset::query(),$request)->pluck('id');$groups['asset']['rows']=$groups['asset']['rows']->whereIn('id',$assetIds)->values();}
  $home=[];$alerts=[];$upcoming=collect();$tabCounts=[];
  if($tab==='overview'){
   $home=['Headcount'=>$registers->stats('people',$u,$from,$to)['Active headcount'],'Vehicles available'=>$registers->stats('fleet',$u,$from,$to)['Available'],'Total office assets'=>$registers->stats('assets',$u,$from,$to)['Total assets'],'Open vacancies'=>$registers->stats('recruitment',$u,$from,$to)['Open vacancies'],'Attendance rate (%)'=>$registers->attendance($u,$from,$to)['rate']];
   $alerts=$registers->alerts($u);$upcoming=HrDashboardActivity::whereNotIn('status',['Completed','Cancelled'])->whereNotNull('deadline')->whereDate('deadline','>=',today())->orderBy('deadline')->limit(10)->get();
   foreach($tabs as $key=>$t)$tabCounts[$key]=HrDashboardActivity::whereIn('tab',$key==='facilities'?['facilities','operations']:[$key])->whereNotIn('status',['Completed','Cancelled'])->count();
  }
  $edit=$request->integer('hr_edit')?HrDashboardActivity::find($request->integer('hr_edit')):null;
  if($edit)abort_unless(HrLiveRegisters::canEdit($u)&&in_array($edit->tab,$tab==='facilities'?['facilities','operations']:[$tab]),403);
  $people=$registers->staff($u)->pluck('name','id');$write=HrLiveRegisters::canEdit($u);$modernWorkspace=true;
  return compact('modernWorkspace','tabs','tab','period','from','to','owner','team','activities','allExportActivities','summary','stats','groups','home','alerts','upcoming','tabCounts','registers','people','write','full','edit','v');
 }
}
