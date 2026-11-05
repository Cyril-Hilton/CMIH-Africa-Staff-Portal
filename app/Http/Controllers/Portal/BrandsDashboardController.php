<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\{User,BrandsReportSubmission,Task};
use App\Services\{BrandsReports,BrandsDashboard};
use Illuminate\Http\Request;
class BrandsDashboardController extends Controller {
 public function data(Request $r){return response()->json(app(BrandsDashboard::class)->build($r->query(),$r->user()))->header('Cache-Control','private, no-store');}
 public function submissions(Request $r){
  abort_unless(BrandsReports::member($r->user()),403);$manager=BrandsReports::manager($r->user());
  $entries=BrandsReportSubmission::with('submitter')->whereIn('status',['pending','returned'])->when(!$manager,fn($q)=>$q->where('submitted_by',$r->user()->id))->latest()->limit(100)->get()->map(fn($s)=>['id'=>$s->id,'revision'=>$s->revision,'category'=>$s->category,'source_type'=>$s->source_type,'source_id'=>$s->source_id,'source_token'=>$s->source_token,'payload'=>$s->payload,'display'=>$this->display($s->payload),'author'=>$s->submitter?->name??'Retained staff history','status'=>$s->status,'note'=>$s->review_note,'can_resubmit'=>$s->status==='returned'&&(int)$s->submitted_by===$r->user()->id]);
  $collaborators=User::internalStaff()->where('status','active')->orderBy('name')->get(['id','name']);
  return response()->json(['entries'=>$entries,'schemas'=>config('brands_dashboard.schemas'),'can_review'=>$manager,'actor'=>$r->user()->id,'collaborators'=>$collaborators])->header('Cache-Control','private, no-store');
 }
 public function detail(Request $r,string $type,int $id){
  abort_unless(in_array($type,['weekly','register','task']),404);
  if($type==='task'){$t=Task::findOrFail($id);abort_unless(User::normalizeDepartmentKey($t->department)==='brands_marketing',404);return response()->json(['payload'=>['category'=>'productivity','title'=>$t->title,'data'=>['Action'=>$t->details,'Status'=>$t->status,'Priority'=>$t->priority]],'display'=>[['label'=>'Action','html'=>\App\Support\ReportRichText::html($t->details??$t->title)]],'can_edit'=>false]);}
  $service=app(BrandsReports::class);$source=$service->source($type,$id);if($type==='register')abort_unless($source->approved_at,404);if($type==='weekly'&&in_array($source->report_status,['draft','submitted','returned'],true))abort_unless(BrandsReports::manager($r->user()),403);
  $p=$service->payload($source,$type);$fields=config('brands_dashboard.schemas.'.$p['category'],[]);$display=[];foreach($p['data'] as $key=>$value){if($key==='collaborator_roles'&&is_array($value))$value=nl2br(e($this->collaboratorRoles($value)));if(($fields[$key]['type']??'')==='staff')$value=User::whereIn('id',$value??[])->pluck('name')->join(', ');if(($fields[$key]['type']??'')==='select')$value=$fields[$key]['options'][$value]??$value;$display[]=['label'=>$fields[$key]['label']??ucwords(str_replace('_',' ',$key)),'html'=>\App\Support\ReportRichText::html(is_scalar($value)?(string)$value:'')];}
  if($type==='weekly')foreach(\App\Models\WeeklyConsolidatedColumn::where('user_id',$source->created_by)->whereIn('department',User::departmentAliases('brands_marketing'))->orderBy('order')->get() as $column){$value=$source->customFieldValue($column);if(filled($value))$display[]=['label'=>$column->label,'html'=>\App\Support\ReportRichText::html((string)$value)];}
  return response()->json(['is_draft'=>$type==='weekly'&&in_array($source->report_status,['draft','submitted','returned'],true),'payload'=>$p,'source_type'=>$type,'source_id'=>$id,'source_token'=>BrandsReports::token($source),'collaborator_names'=>User::internalStaff()->whereIn('id',$p['data']['collaborators']??[])->pluck('name','id'),'display'=>$display,'can_edit'=>($type!=='weekly'||$source->workflow_state==='legacy')&&BrandsReports::member($r->user())&&(BrandsReports::manager($r->user())||(int)$p['owner_id']===$r->user()->id)])->header('Cache-Control','private, no-store');
 }
 private function collaboratorRoles(array $roles):string {
  $names=User::whereIn('id',array_keys($roles))->pluck('name','id');
  return collect($roles)->map(fn($role,$id)=>($names[$id]??'Retained staff history').': '.(string)$role)->join("\n");
 }
 private function display(array $payload):array {
  $fields=config('brands_dashboard.schemas.'.$payload['category'],[]);$result=[];
  foreach($payload['data'] as $key=>$value){if($key==='collaborator_roles'&&is_array($value))$value=nl2br(e($this->collaboratorRoles($value)));if(($fields[$key]['type']??'')==='staff')$value=User::whereIn('id',$value??[])->pluck('name')->join(', ');if(($fields[$key]['type']??'')==='select')$value=$fields[$key]['options'][$value]??$value;$result[]=['label'=>$fields[$key]['label']??ucwords(str_replace('_',' ',$key)),'html'=>\App\Support\ReportRichText::html(is_scalar($value)?(string)$value:'')];}return $result;
 }
 public function submit(Request $r){return app(BrandsReports::class)->submit($r);}
 public function review(Request $r,BrandsReportSubmission $submission){return app(BrandsReports::class)->review($r,$submission);}
}
