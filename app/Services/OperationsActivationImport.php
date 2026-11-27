<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class OperationsActivationImport {
 private bool $dates1904=false;
 public function columns():array{return ['title'=>'Activation','client'=>'Client','project'=>'Project','owner'=>'Owner','week'=>'Reporting week','status'=>'Status']+collect(OperationsReporting::fields('activation',false))->mapWithKeys(fn($type,$key)=>[$key=>str($key)->headline()->toString()])->all();}
 public function parse(string $path,string $extension,User $actor,string $week,string $dateFormat='d/m/Y'):array {
  if($extension==='xlsx'){$z=new \ZipArchive;if($z->open($path)===true){$meta=$z->getFromName('xl/workbook.xml');$this->dates1904=(bool)preg_match('/date1904=[\"\'](?:1|true)[\"\']/i',$meta?:'');$z->close();}}
  try{$csv=$extension==='csv'?file_get_contents($path):app(BrandedSpreadsheet::class)->csvForImport($path);}catch(\Throwable $e){throw ValidationException::withMessages(['file'=>'Cannot read this workbook. Use a values-only .xlsx file or CSV. '.$e->getMessage()]);}
  if(strlen($csv)>20*1024*1024)throw ValidationException::withMessages(['file'=>'The worksheet is too large. Split the workbook into smaller files.']);
  $handle=fopen('php://temp','w+');fwrite($handle,preg_replace('/^\xEF\xBB\xBF/','',$csv));rewind($handle);$headers=fgetcsv($handle,0,',','"','');if(!$headers)throw ValidationException::withMessages(['file'=>'The file is empty.']);
  $normal=fn($v)=>trim(preg_replace('/[^a-z0-9]+/',' ',strtolower(trim($v))));$aliases=[];foreach($this->columns() as $key=>$label){$aliases[$normal($key)]=$key;$aliases[$normal($label)]=$key;}
  $aliases+=['activity'=>'title','activation name'=>'title','name'=>'title','project lead'=>'owner','account lead'=>'owner','owner id'=>'owner','period start'=>'week','week start'=>'week','reporting period'=>'week','activation date'=>'starts_on','start date'=>'starts_on','end date'=>'ends_on','activation status'=>'activation_status'];
  $mapped=array_map(fn($h)=>$aliases[$normal($h)]??null,$headers);if(!in_array('title',$mapped,true))throw ValidationException::withMessages(['file'=>'Include an Activation, Activation Name or Activity column. Download the template for supported headings.']);
  $known=array_filter($mapped);if(count($known)!==count(array_unique($known)))throw ValidationException::withMessages(['file'=>'Two columns map to the same field. Keep one column for each field.']);
  $people=User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases('operations_projects'))->get();$rows=[];$errors=[];$number=1;$schema=OperationsReporting::fields('activation',false);
  $defaultWeek=Carbon::parse($week)->startOfWeek();
  while(($cells=fgetcsv($handle,0,',','"',''))!==false){$number++;if(!array_filter($cells,fn($v)=>trim((string)$v)!==''))continue;if(count($rows)>=500)throw ValidationException::withMessages(['file'=>'Import at most 500 rows per workbook.']);
   $raw=[];foreach($mapped as $i=>$key)if($key)$raw[$key]=trim((string)($cells[$i]??''));
   try{$owner=trim($raw['owner']??'');$person=$owner===''?$actor:$people->first(fn($p)=>ctype_digit($owner)?$p->id===(int)$owner:mb_strtolower($p->name)===mb_strtolower($owner));if(!$person||!$people->contains('id',$person->id))throw new \RuntimeException('Choose an active Operations owner by full name or ID.');
    if(!OperationsReporting::manager($actor)&&$person->id!==$actor->id)throw new \RuntimeException('Staff imports must use your own Operations owner. Ask the HOD to import assignments for other owners.');
    $period=empty($raw['week'])?$defaultWeek->copy():Carbon::parse($this->date($raw['week'],$dateFormat))->startOfWeek();$data=[];
    foreach($schema as $key=>$type)if(isset($raw[$key])&&$raw[$key]!=='')$data[$key]=$type==='date'?$this->date($raw[$key],$dateFormat):$raw[$key];
    foreach($schema as $key=>$type){if(isset($data[$key])&&in_array($type,['number','money'])){if(!is_numeric($data[$key])||$data[$key]<0||$data[$key]>99999999999999)throw new \RuntimeException(str($key)->headline().' must be a non-negative number.');$data[$key]=(float)$data[$key];}}
    foreach(config('operations_tracker.options') as $key=>$options)if(isset($data[$key])&&in_array($key,array_keys($schema))){$match=collect($options)->first(fn($v)=>strtolower($v)===strtolower($data[$key]));if(!$match)throw new \RuntimeException('Invalid '.str($key)->headline().'.');$data[$key]=$match;}
    if(isset($data['starts_on'],$data['ends_on'])&&$data['ends_on']<$data['starts_on'])throw new \RuntimeException('End date precedes start date.');
    $state=strtolower($raw['status']??'planned');$state=['upcoming'=>'planned','ongoing'=>'in_progress','in progress'=>'in_progress','on hold'=>'on_hold'][$state]??$state;if(!array_key_exists($state,OperationsReporting::STATUSES))throw new \RuntimeException('Invalid status.');
    $v=Validator::make($raw,['title'=>'required|string|max:255','client'=>'nullable|string|max:255','project'=>'nullable|string|max:255'])->validate();
    foreach($data as $key=>$value)if(is_string($value)&&mb_strlen($value)>6000)throw new \RuntimeException(str($key)->headline().' is too long.');
    $rows[]=['title'=>$v['title'],'client'=>$v['client']??null,'project'=>$v['project']??null,'owner_id'=>$person->id,'period_start'=>$period->toDateString(),'period_end'=>$period->copy()->endOfWeek()->toDateString(),'status'=>$state,'evidence_url'=>null,'data'=>$data,'source_row'=>$number];
   }catch(\Throwable $e){$errors[]='Row '.$number.': '.$e->getMessage();if(count($errors)>=20)break;}
  }fclose($handle);if($errors)throw ValidationException::withMessages(['file'=>implode(' ', $errors)]);if(!$rows)throw ValidationException::withMessages(['file'=>'No activation rows were found.']);
  return ['rows'=>$rows,'unmapped'=>array_values(array_filter($headers,fn($h,$i)=>$mapped[$i]===null,ARRAY_FILTER_USE_BOTH))];
 }
 private function date(string $value,string $format):string {
  if(is_numeric($value)){if((float)$value<1||(float)$value>100000)throw new \RuntimeException('Invalid Excel date.');return ($this->dates1904?Carbon::create(1904,1,1):Carbon::create(1899,12,30))->addDays((int)$value)->toDateString();}
  $format=preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)?'Y-m-d':$format;$d=Carbon::createFromFormat('!'.$format,$value);if(!$d||$d->format($format)!==$value)throw new \RuntimeException('Use YYYY-MM-DD or the selected date format.');return $d->toDateString();
 }
}
