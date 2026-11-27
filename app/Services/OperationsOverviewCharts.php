<?php
namespace App\Services;
use Illuminate\Support\Collection;
class OperationsOverviewCharts {
 public function build(Collection $rows,array $trend):array {
  $activations=$rows->where('kind','activation');$states=$activations->countBy(fn($r)=>filled($r->data['activation_status']??null)?$r->data['activation_status']:OperationsReporting::STATUSES[$r->status]??'Not recorded');
  $brands=$activations->countBy(fn($r)=>trim($r->data['brand']??'')?:'Brand not recorded')->sortDesc();
  $posm=['deployed'=>0.0,'returned'=>0.0,'outstanding'=>0.0,'needs_correction'=>0];
  foreach($rows->where('kind','posm_deployment') as $row){$d=$row->data;$qty=$d['quantity']??null;$returned=$d['returned_quantity']??0;if((!isset($d['returned_quantity'])&&(!empty($d['returned_on'])||in_array($d['return_status']??'',['Returned','Partially Returned'])))||!is_numeric($qty)||!is_numeric($returned)||$qty<0||$returned<0||$returned>$qty){$posm['needs_correction']++;continue;}$posm['deployed']+=(float)$qty;$posm['returned']+=(float)$returned;$posm['outstanding']+=(float)$qty-(float)$returned;}
  $pendingProcurement=$rows->where('kind','procurement')->filter(fn($r)=>!in_array($r->status,['completed','cancelled'])&&($r->data['procurement_status']??'')!=='Delivered')->count();$fabricationLate=$rows->where('kind','fabrication')->filter(fn($r)=>!empty($r->data['due_on'])&&$r->data['due_on']<today()->toDateString()&&empty($r->data['delivered_on'])&&!in_array($r->status,['completed','cancelled']))->count();
  return ['pending_procurements'=>$pendingProcurement,'overdue_fabrications'=>$fabricationLate,'trend'=>$trend,'statuses'=>$states->all(),'brands'=>$brands->all(),'posm'=>$posm,'activations'=>$activations->count(),'targets_met'=>$activations->filter(fn($r)=>is_numeric($r->data['target_quantity']??null)&&$r->data['target_quantity']>0&&is_numeric($r->data['actual_quantity']??null)&&$r->data['actual_quantity']>=$r->data['target_quantity'])->count()];
 }
}
