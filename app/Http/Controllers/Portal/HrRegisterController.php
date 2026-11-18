<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\{Asset,HrRegisterRecord,User};
use App\Services\{HrLiveRegisters,HrRegisterEditor,HrWorkspace,PerformanceWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
class HrRegisterController extends Controller {
 public function index(Request $r){return response()->view('portal.hr-reporting.live-page',app(HrWorkspace::class)->build($r))->header('Cache-Control','private, no-store');}
 public function save(Request $r,string $kind,?int $id=null){
  abort_unless(HrLiveRegisters::canEdit($r->user()),403);
  $r->validate(['data'=>'required|array','revision'=>$id?'required':'nullable','attachment'=>in_array($kind,['staff','asset','vendor'])?'prohibited':'nullable|file|mimes:pdf,docx,xlsx,pptx,txt,jpg,jpeg,png,webp|max:10240']);
  app(HrRegisterEditor::class)->validate($kind,$r->input('data'),$r->user(),$id);
  $attachment=null;if($r->hasFile('attachment')){$f=$r->file('attachment');app(\App\Services\Creative\CreativeUploadScreening::class)->check($f);$path=$f->store('hr-register-documents','local');abort_unless($path,503);$attachment=['path'=>$path,'name'=>basename($f->getClientOriginalName())];}
  try{$row=app(HrRegisterEditor::class)->save($kind,$r->input('data'),$r->user(),$id,$r->input('revision'),$attachment);}catch(\Throwable $e){if($attachment)Storage::disk('local')->delete($attachment['path']);throw $e;}
  return redirect()->to($this->back($r,app(HrLiveRegisters::class)->schema($kind)['tab']))->with('status','Register saved. Overview totals updated automatically.');
 }
 public function destroy(Request $r,string $kind,int $id){
  abort_unless(HrLiveRegisters::canEdit($r->user()),403);$service=app(HrLiveRegisters::class);abort_unless($service->schema($kind),404);
  $r->validate(['revision'=>'required']);
  DB::transaction(function()use($r,$kind,$id){
   $row=match($kind){'staff'=>User::internalStaff()->lockForUpdate()->findOrFail($id),'vendor'=>\App\Models\ThirdPartyVendor::lockForUpdate()->findOrFail($id),'asset'=>Asset::where(fn($q)=>$q->where('is_warehouse_tracked',false)->orWhereNull('is_warehouse_tracked'))->lockForUpdate()->findOrFail($id),default=>HrRegisterRecord::where('kind',$kind)->lockForUpdate()->findOrFail($id)};
   $rev=in_array($kind,['staff','asset','vendor'])?hash('sha256',json_encode($row->getRawOriginal())):$row->revision;abort_unless((string)$rev===(string)$r->input('revision'),409,'Record changed; refresh before archiving.');
   $before=$row->getAttributes();if($kind==='staff'){abort_if($row->id===$r->user()->id||$row->isCvoOrSuperAdmin(),422,'Use the existing staff account management process for this account.');$row->update(['status'=>'suspended']);}elseif($kind==='vendor')$row->update(['hr_details'=>array_replace($row->hr_details??[],['state'=>'Inactive','updated_by'=>$r->user()->id])]);elseif($kind==='asset')$row->update(['condition'=>'Disposed','status'=>'disposed']);else$row->delete();
   unset($before['password'],$before['remember_token']);$after=$row->getAttributes();unset($after['password'],$after['remember_token']);
   PerformanceWorkflow::record('hr_register_'.$kind,$id,$r->user()->id,'archive',$before,$after,'Archived from HR live register; history retained.');
  });return redirect()->to($this->back($r,$service->schema($kind)['tab']))->with('status','Record archived; change history retained.');
 }
 public function attachment(Request $r,HrRegisterRecord $record){
  $schema=app(HrLiveRegisters::class)->schema($record->kind);abort_unless($schema&&HrLiveRegisters::tabAllowed($r->user(),$schema['tab'])&&app(HrLiveRegisters::class)->records($record->kind,$r->user())->contains('id',$record->id),404);
  $f=$record->attachment;abort_unless($f&&Storage::disk('local')->exists($f['path']),404);return Storage::disk('local')->download($f['path'],$f['name'],['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
 public function history(Request $r,string $kind,int $id){
  abort_unless(HrLiveRegisters::full($r->user()),403);
  abort_unless($kind==='activity'||app(HrLiveRegisters::class)->schema($kind),404);
  $type=$kind==='activity'?'hr_activity':'hr_register_'.$kind;
  $events=\App\Models\PerformanceEvent::with('actor:id,name')->where('entity_type',$type)->where('entity_id',$id)->orderByDesc('id')->paginate(20)->withQueryString();
  return response()->view('portal.hr-reporting.history',compact('events','kind','id'))->header('Cache-Control','private, no-store');
 }
 private function back(Request $r,string $tab): string {return route('portal.hr-workspace',$r->only(['hr_period','hr_from','hr_to','hr_owner'])+['hr_tab'=>$tab]).'#hr-registers';}
}
