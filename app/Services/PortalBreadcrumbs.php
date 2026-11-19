<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PortalBreadcrumbs {
 public function build(Request $request): array {
  $name=$request->route()?->getName()??'';$path=trim($request->path(),'/');
  $home=auth()->check()?route('dashboard'):url('/');$items=[['label'=>auth()->check()?'Staff Dashboard':'Home','url'=>$home]];
  if($path===''||$name==='dashboard'){
   if($request->filled('exec_view')&&config('executive_tracker.tabs.'.$request->query('exec_view'))){$items[]=['label'=>'Executive command centre','url'=>route('dashboard')];$items[]=['label'=>config('executive_tracker.tabs.'.$request->query('exec_view')),'url'=>null];return $items;}
   if($request->query('weekly_department')&&$request->query('weekly_department')!=='all')$items[]=['label'=>\App\Models\User::departmentLabel($request->query('weekly_department')),'url'=>null];
   else $items[0]['url']=null;
   return $items;
  }
  $labels=['portal'=>'Staff Portal','admin'=>'Administration','hr'=>'HR & Admin','hr-workspace'=>'HR & Admin','hr-dashboard'=>'HR & Admin','client-relations'=>'Client Relations','pipeline'=>'Projects & Weekly Entries','creative'=>'Creative Studio','creative-studio'=>'Creative Studio','operations'=>'Operations & Projects','brands'=>'Brands & Marketing','finance'=>'Finance','boardroom-requests'=>'Boardroom Bookings','fleet-requests'=>'Vehicle Requests','warehouse-assets'=>'Warehouse Assets','assets'=>'Office Assets','performance'=>'Performance & Accountability','project-journey'=>'Live Project & Team Tracker','leaves'=>'Leaves & Absences','create'=>'Add New','edit'=>'Edit','show'=>'Details','index'=>'Overview','hr-reporting'=>'HR Reports'];
  $segments=explode('/',$path);$prefix='';
  foreach($segments as $i=>$segment){
   $prefix.='/'.$segment;if($segment==='portal')continue;
   $last=$i===count($segments)-1;$label=$labels[$segment]??Str::headline($segment);
   if(ctype_digit($segment)||strlen($segment)>45){$label='Details';foreach($request->route()?->parameters()??[] as $value)if($value instanceof \Illuminate\Database\Eloquent\Model&&(string)$value->getRouteKey()===$segment){$label=$value->getAttribute('name')??$value->getAttribute('title')??$value->getAttribute('activity')??$value->getAttribute('project_name')??'Details';break;}}
   $target=null;
   if(!$last)foreach(app('router')->getRoutes() as $route){if(!in_array('GET',$route->methods(),true)||str_contains($route->uri(),'{'))continue;if('/'.trim($route->uri(),'/')===$prefix){$target=url($prefix);break;}}
   if(in_array($segment,['hr','hr-workspace','hr-dashboard'],true)){$target=route('portal.hr-workspace',['hr_tab'=>HrLiveRegisters::full($request->user())?'overview':'people']);$last=false;}
   if($segment==='client-relations')$target=route('portal.crm.index');
   if(!$last&&$target===null)continue;
   if(end($items)['label']!==$label)$items[]=['label'=>$label,'url'=>$target];
  }
  if(str_starts_with($name,'portal.hr')||$request->query('weekly_department')==='hr_admin'){
   $tab=$request->query('hr_tab');if($tab&&config('hr_workspace.tabs.'.$tab))$items[]=['label'=>config('hr_workspace.tabs.'.$tab.'.label'),'url'=>null];
  }
  if(end($items)['url']!==null)$items[]=['label'=>'Overview','url'=>null];
  return $items;
 }
}
