<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PortalBreadcrumbs {
 public function build(Request $request): array {
  $name=$request->route()?->getName()??'';$path=trim($request->path(),'/');
  $home=auth()->check()?route('dashboard'):url('/');$items=[['label'=>auth()->check()?'Staff Dashboard':'Home','url'=>$home]];
  if($path===''||in_array($name,['dashboard','portal.dashboard.live'],true)){
   $department=$request->query('weekly_department');
   if($department==='all'||is_string($department)&&array_key_exists($department,config('performance.departments'))){
    $items[]=['label'=>'Weekly Department Reports','url'=>route('dashboard',$this->periodParams($request)+['weekly_department'=>'all'])];
    $items[]=['label'=>$department==='all'?'All Departments':\App\Models\User::departmentLabel($department),'url'=>null];
    return $this->selectedView($items,$request,$department);
   }
   if($request->filled('exec_view')&&config('executive_tracker.tabs.'.$request->query('exec_view'))){$items[]=['label'=>'Executive command centre','url'=>route('dashboard')];$items[]=['label'=>config('executive_tracker.tabs.'.$request->query('exec_view')),'url'=>null];return $items;}
   $items[0]['url']=null;return $items;
  }
  if(str_starts_with($name,'portal.executive.'))return array_merge($items,[['label'=>'Executive command centre','url'=>route('dashboard',['exec_view'=>'projects'])],['label'=>'Source details','url'=>null]]);
  $labels=['portal'=>'Staff Portal','admin'=>'Administration','hr'=>'HR & Admin','hr-workspace'=>'HR & Admin','hr-dashboard'=>'HR & Admin','client-relations'=>'Client Relations','pipeline'=>'Projects & Weekly Entries','creative'=>'Creative Studio','creative-studio'=>'Creative Studio','operations'=>'Operations & Projects','brands'=>'Brands & Marketing','finance'=>'Finance','boardroom-requests'=>'Boardroom Bookings','fleet-requests'=>'Vehicle Requests','warehouse-assets'=>'Warehouse Assets','assets'=>'Office Assets','performance'=>'Performance & Accountability','project-journey'=>'Live Project & Team Tracker','leaves'=>'Leaves & Absences','create'=>'Add New','edit'=>'Edit','show'=>'Details','index'=>'Overview','hr-reporting'=>'HR Reports'];
  $segments=explode('/',$path);$prefix='';
  foreach($segments as $i=>$segment){
   $prefix.='/'.$segment;if($segment==='portal')continue;
   $last=$i===count($segments)-1;$label=$labels[$segment]??Str::headline($segment);
   if(ctype_digit($segment)||strlen($segment)>45){$label='Details';foreach($request->route()?->parameters()??[] as $value)if($value instanceof \Illuminate\Database\Eloquent\Model&&(string)$value->getRouteKey()===$segment){$label=$value->getAttribute('name')??$value->getAttribute('title')??$value->getAttribute('activity')??$value->getAttribute('project_name')??'Details';break;}}
   $target=null;
   if(!$last)foreach(app('router')->getRoutes() as $route){if(!in_array('GET',$route->methods(),true)||str_contains($route->uri(),'{'))continue;if('/'.trim($route->uri(),'/')===$prefix){$target=url($prefix);break;}}
   if(in_array($segment,['hr','hr-workspace','hr-dashboard'],true)){$target=route('portal.hr-workspace',$this->periodParams($request)+['hr_tab'=>HrLiveRegisters::full($request->user())?'overview':'people']);$last=false;}
   if($segment==='client-relations')$target=route('portal.crm.index');
   if(!$last&&$target===null)continue;
   if(end($items)['label']!==$label)$items[]=['label'=>$label,'url'=>$target];
  }
  $department=match($path){'portal/hr','portal/hr-workspace','portal/hr-dashboard'=>'hr_admin','portal/finance'=>'finance','portal/creative'=>'creatives','portal/operations'=>'operations_projects','portal/brands'=>'brands_marketing',default=>null};
  if($department)$items=$this->selectedView($items,$request,$department);
  if(end($items)['url']!==null)$items[]=['label'=>'Overview','url'=>null];
  return $items;
 }
 private function periodParams(Request $request): array {
  return $request->only(['report_period','report_date','weekly_from','weekly_to','hr_period','hr_from','hr_to','finance_period','finance_date','finance_from','finance_to','brands_period','brands_date']);
 }
 private function selectedView(array $items,Request $request,?string $department): array {
  [$parameter,$tabs]=match($department){
   'hr_admin'=>['hr_tab',['overview'=>'Overview']+collect(config('hr_workspace.tabs'))->map(fn($tab)=>$tab['label'])->all()],
   'finance'=>['finance_tab',FinanceDashboard::TABS],
   'operations_projects'=>['ops_tab',config('operations_tracker.tabs')],
   'brands_marketing'=>['brands_tab',config('brands_dashboard.tabs')],
   'creatives'=>['creative_view',['overview'=>'Overview','workflow'=>'Work Flow','board'=>'Work Flow · Flow Board','table'=>'Work Flow · Table','calendar'=>'Calendar','workload'=>'Calendar · Team Workload','report'=>'Showcase','showcase'=>'Showcase','presentation'=>'Presentation','activity'=>'Activity history']],
   default=>[null,[]],
  };
  $value=$parameter?$request->query($parameter,in_array($department,['creatives','brands_marketing'],true)?'overview':null):null;
  if(!is_string($value)||!isset($tabs[$value]))return $items;
  $last=array_key_last($items);
  if($items[$last]['url']===null){
   $items[$last]['url']=in_array($request->route()?->getName(),['dashboard','portal.dashboard.live'],true)
    ?route('dashboard',$this->periodParams($request)+['weekly_department'=>$department])
    :url($request->path()).($this->periodParams($request)?'?'.http_build_query($this->periodParams($request)):'');
  }
  if($items[$last]['label']!==$tabs[$value])$items[]=['label'=>$tabs[$value],'url'=>null];
  return $items;
 }

}
