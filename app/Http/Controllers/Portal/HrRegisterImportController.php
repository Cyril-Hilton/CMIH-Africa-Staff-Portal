<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\{Asset,HrRegisterRecord,User};
use App\Services\{BrandedSpreadsheet,HrLiveRegisters,HrRegisterEditor};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class HrRegisterImportController extends Controller {
 private function authorizeImport(Request $r,string $kind): array {abort_unless(HrLiveRegisters::canEdit($r->user())&&in_array($kind,['staff','vehicle','asset']),403);return app(HrLiveRegisters::class)->schema($kind);}
 public function index(Request $r,string $kind){$schema=$this->authorizeImport($r,$kind);$preview=$r->session()->get('hr_register_import.'.$kind);return response()->view('portal.hr-reporting.import',compact('kind','schema','preview'))->header('Cache-Control','private, no-store');}
 public function template(Request $r,string $kind){$schema=$this->authorizeImport($r,$kind);return response(app(BrandedSpreadsheet::class)->xlsx([array_keys($schema['fields'])]))->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition','attachment; filename="hr-'.$kind.'-template.xlsx"');}
 public function preview(Request $r,string $kind){
  $schema=$this->authorizeImport($r,$kind);$r->session()->forget('hr_register_import.'.$kind);$r->validate(['file'=>'required|file|mimes:csv,txt,xlsx|extensions:csv,xlsx|max:5120']);
  $f=$r->file('file');$contents=strtolower($f->getClientOriginalExtension())==='xlsx'?app(BrandedSpreadsheet::class)->csvForImport($f->getRealPath()):file_get_contents($f->getRealPath());
  $h=fopen('php://temp','w+');fwrite($h,preg_replace('/^\xEF\xBB\xBF/','',$contents));rewind($h);$headers=fgetcsv($h,0,',','"','');$allowed=array_keys($schema['fields']);
  if(!$headers||array_diff($headers,$allowed)||count($headers)!==count(array_unique($headers)))throw ValidationException::withMessages(['file'=>'Use the template headings; unknown and duplicate headings cannot be imported.']);
  $identity=match($kind){'staff'=>'email','asset'=>'asset_tag','vehicle'=>'registration_number'};if(!in_array($identity,$headers,true))throw ValidationException::withMessages(['file'=>'Include '.$identity.' to prevent duplicates.']);
  $rows=[];$errors=[];$seen=[];$line=1;
  while(($values=fgetcsv($h,0,',','"',''))!==false){$line++;if(!array_filter($values,fn($x)=>$x!==null&&trim($x)!==''))continue;if($line>501){$errors[]='Maximum 500 rows per import.';break;}if(count($values)!==count($headers)){$errors[]='Row '.$line.': number of columns does not match.';continue;}$data=array_combine($headers,array_map('trim',$values));$key=mb_strtolower($data[$identity]??'');
   if(isset($seen[$key])){$errors[]='Row '.$line.': repeated '.$identity.'.';continue;}$seen[$key]=true;
   $row=match($kind){'staff'=>User::internalStaff()->where('email',$data[$identity])->first(),'asset'=>Asset::where('asset_tag',$data[$identity])->first(),'vehicle'=>HrRegisterRecord::where('kind','vehicle')->get()->first(fn($x)=>mb_strtolower($x->data['registration_number']??'')===$key)};
   if($kind==='asset'&&$row?->is_warehouse_tracked){$errors[]='Row '.$line.': this asset belongs to the warehouse; use its warehouse register.';continue;}
   try{$validated=app(HrRegisterEditor::class)->validate($kind,$data,$r->user(),$row?->id);}catch(ValidationException $e){$errors[]='Row '.$line.': '.implode(' ',$e->validator->errors()->all());continue;}
   $revision=$row?(in_array($kind,['staff','asset'])?hash('sha256',json_encode($row->getRawOriginal())):$row->revision):null;
   $rows[]=['line'=>$line,'id'=>$row?->id,'revision'=>$revision,'data'=>$validated,'action'=>$row?'Update':'Add'];
  }fclose($h);
  if(!$rows&&!$errors)$errors[]='No data rows found.';
  $preview=['viewer'=>$r->user()->id,'token'=>Str::random(40),'expires'=>now()->addMinutes(30)->timestamp,'rows'=>$rows,'errors'=>$errors];$r->session()->put('hr_register_import.'.$kind,$preview);
  return redirect()->route('portal.hr-registers.import',['kind'=>$kind]);
 }
 public function confirm(Request $r,string $kind){
  $this->authorizeImport($r,$kind);$r->validate(['token'=>'required|string']);$preview=$r->session()->get('hr_register_import.'.$kind);
  abort_unless($preview&&$preview['viewer']===$r->user()->id&&hash_equals($preview['token'],$r->input('token'))&&$preview['expires']>=now()->timestamp&&!$preview['errors'],422,'Upload and review a fresh valid preview.');
  DB::transaction(function()use($preview,$r,$kind){foreach($preview['rows'] as $item)app(HrRegisterEditor::class)->save($kind,$item['data'],$r->user(),$item['id'],$item['revision']);});
  $r->session()->forget('hr_register_import.'.$kind);return redirect()->route('portal.hr-workspace',['hr_tab'=>app(HrLiveRegisters::class)->schema($kind)['tab']])->with('status',count($preview['rows']).' register rows imported. Overview totals updated.');
 }
}
