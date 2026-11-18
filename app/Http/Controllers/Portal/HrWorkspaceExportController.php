<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Services\{HrWorkspace,HrLiveRegisters,BrandedSpreadsheet,HrStaffRecord};
use Illuminate\Http\Request;
class HrWorkspaceExportController extends Controller {
 public function __invoke(Request $r){
  $r->validate(['format'=>'required|in:pdf,xlsx']);$data=app(HrWorkspace::class)->build($r);$service=$data['registers'];$u=$r->user();
  $rows=[['HR & Admin report'],['Activity period',$data['from'],$data['to']],['Registers are current snapshots as of',today()->toDateString()],[],['Section','Measure','Value']];
  foreach($data['tab']==='overview'?$data['tabs']:[$data['tab']=>$data['tabs'][$data['tab']]] as $key=>$tab)foreach($service->stats($key,$u,$data['from'],$data['to']) as $label=>$value)$rows[]=[$tab['label'],$label,$value];
  foreach($data['summary'] as $label=>$value)$rows[]=['Activities',$label,$value];
  $rows[]=[];$rows[]=['Activities'];$rows[]=['Function','Type','Description','Linked record','Staff involved','Department','Assigned to','Opened','Deadline','Priority','Status','Completed','Remarks'];
  foreach($data['allExportActivities'] as $a)$rows[]=[config('hr_workspace.tabs.'.$a->tab.'.label',$a->tab),$a->category,$a->activity,$service->linkedLabel($a->linked_type,$a->linked_id,$u),$a->staff?->name,\App\Models\User::departmentLabel($a->department),$a->assignee?->name,$a->opened_on,$a->deadline,$a->priority,$a->status.($a->isOverdue(min($data['to'],today()->toDateString()))?' · Overdue':''),$a->completed_at?->format('Y-m-d H:i'),$a->remarks];
  $name='hr-admin-'.$data['from'].'-'.$data['to'];
  if($r->format==='xlsx')return response(app(BrandedSpreadsheet::class)->xlsx($rows))->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition','attachment; filename="'.$name.'.xlsx"')->header('Cache-Control','private, no-store');
  $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'defaultFont'=>'DejaVu Sans']);$pdf->loadHtml(view('portal.hr-reporting.export',compact('rows'))->render());$pdf->setPaper('A4','landscape');$pdf->render();return response($pdf->output())->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="'.$name.'.pdf"')->header('Cache-Control','private, no-store');
 }
}
