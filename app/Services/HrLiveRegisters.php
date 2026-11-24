<?php
namespace App\Services;

use App\Models\{Appraisal,Asset,Attendance,HrDashboardActivity,HrRegisterRecord,LeaveApplication,PerformanceTargetPlan,User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HrLiveRegisters
{
 public static function full(User $u): bool {return $u->isActive() && ($u->hasFullHrAccess() || $u->canAuditHrAndFinance());}
 public static function manager(User $u): bool {return $u->hasFullHrAccess() && !$u->isCvoOrSuperAdmin();}
 public static function canEdit(User $u): bool {return $u->isActive() && ($u->access_role==='super_admin' || ($u->hasFullHrAccess()&&!$u->isCvoOrSuperAdmin()));}
 public static function tabAllowed(User $u,string $tab): bool {
  if(!$u->isActive())return false;
  if(self::full($u))return true;
  if($u->isLineManager())return in_array($tab,['people','attendance','performance'],true);
  return false;
 }
 public function tabs(User $u): array {return array_filter(config('hr_workspace.tabs'),fn($tab,$key)=>self::tabAllowed($u,$key),ARRAY_FILTER_USE_BOTH);}
 public function schema(string $kind): array {
  if($kind==='staff')return ['tab'=>'people','label'=>'Staff Register','fields'=>[
   'name'=>['type'=>'text','label'=>'Full name'],'staff_id_number'=>['type'=>'text','label'=>'Staff ID'],'department'=>['type'=>'department','label'=>'Department'],'position_title'=>['type'=>'text','label'=>'Role'],
   'start_date'=>['type'=>'date','label'=>'Date joined'],'employment_type'=>['type'=>'select','label'=>'Employment type','options'=>['Permanent','Contract','Consultant','National Service','Intern']],
   'contract_expires_at'=>['type'=>'date','label'=>'Contract end date'],'phone'=>['type'=>'text','label'=>'Phone'],'email'=>['type'=>'email','label'=>'Email'],
   'next_of_kin_name'=>['type'=>'text','label'=>'Emergency contact name'],'next_of_kin_phone'=>['type'=>'text','label'=>'Emergency contact phone']]];
  if($kind==='asset')return ['tab'=>'assets','label'=>'Office Asset Register','fields'=>[
   'asset_tag'=>['type'=>'text','label'=>'Asset tag'],'name'=>['type'=>'text','label'=>'Description'],'brand'=>['type'=>'text','label'=>'Brand'],'category'=>['type'=>'text','label'=>'Category'],'quantity_procured'=>['type'=>'number','label'=>'Quantity'],
   'location'=>['type'=>'text','label'=>'Location'],'assigned_to'=>['type'=>'staff','label'=>'Assigned to'],'acquired_on'=>['type'=>'date','label'=>'Date acquired'],'asset_value'=>['type'=>'number','label'=>'Cost (GHS)'],
   'condition'=>['type'=>'select','label'=>'Condition','options'=>['New','Good','Fair','Poor','Damaged','Lost','Under repair','Disposed']]]];
  return config('hr_workspace.registers.'.$kind,[]);
 }
 public function staff(User $viewer): Collection {
  return User::internalStaff()->when(!self::full($viewer),fn($q)=>$q->whereIn('department',User::departmentAliases($viewer->department)))->orderBy('name')->get();
 }
 public function records(string $kind,User $viewer): Collection {
  $schema=$this->schema($kind);abort_unless($schema && self::tabAllowed($viewer,$schema['tab']),403);
  if($kind==='staff')return $this->staff($viewer)->map(function($u)use($schema,$viewer){
   $data=$u->only(array_keys($schema['fields']));
   foreach(['start_date','contract_expires_at'] as $f)$data[$f]=$u->$f?->toDateString();
   if(!self::full($viewer))unset($data['phone'],$data['email'],$data['next_of_kin_name'],$data['next_of_kin_phone']);
   return ['id'=>$u->id,'kind'=>'staff','label'=>$u->name,'data'=>$data,'revision'=>hash('sha256',json_encode($u->getRawOriginal())),'attachment'=>null,'url'=>self::full($viewer)?route('portal.hr.staff.show',$u):null];
  });
  if($kind==='vendor')return app(HrVendorDirectory::class)->records();
  if($kind==='asset')return Asset::with('assignee:id,name')->where(fn($q)=>$q->where('is_warehouse_tracked',false)->orWhereNull('is_warehouse_tracked'))->orderBy('name')->get()->map(fn($a)=>[
   'id'=>$a->id,'kind'=>'asset','label'=>$a->name,'revision'=>hash('sha256',json_encode($a->getRawOriginal())),'attachment'=>null,'url'=>route('portal.assets'),
   'data'=>['asset_tag'=>$a->asset_tag,'name'=>$a->name,'brand'=>$a->brand,'category'=>$a->category?:$a->type,'quantity_procured'=>$a->hr_details['inventory_quantity']??($a->quantity_procured>0?$a->quantity_procured:1),'location'=>$a->location,'assigned_to'=>$a->assigned_to,'acquired_on'=>$a->hr_details['acquired_on']??null,'asset_value'=>$a->asset_value,'condition'=>$a->condition]
  ]);
  return HrRegisterRecord::where('kind',$kind)->orderBy('label')->get()->filter(function($row)use($viewer,$kind){
   if(self::full($viewer))return true;
   $ids=$this->staff($viewer)->pluck('id');
   return (!empty($row->data['staff_id']) && $ids->contains((int)$row->data['staff_id'])) || ($kind==='training' && $ids->intersect($row->data['attendees']??[])->isNotEmpty());
  })->map(fn($r)=>['id'=>$r->id,'kind'=>$kind,'label'=>$r->label,'data'=>$r->data,'revision'=>$r->revision,'attachment'=>$r->attachment,'url'=>null]);
 }
 public function groups(string $tab,User $viewer): array {
  $kinds=collect(config('hr_workspace.registers'))->filter(fn($s)=>$s['tab']===$tab)->keys()->all();
  if($tab==='people')array_unshift($kinds,'staff');if($tab==='assets')array_unshift($kinds,'asset');
  // Department heads see employee-linked appraisal and training records, not general HR notes.
  if(!self::full($viewer))$kinds=array_values(array_intersect($kinds,['staff','appraisal_record','training','improvement_plan']));
  $out=[];foreach($kinds as $kind)$out[$kind]=['schema'=>$this->schema($kind),'rows'=>$this->records($kind,$viewer)];return $out;
 }
 public function attendance(User $viewer,string $from,string $to): array {
  $staff=$this->staff($viewer)->filter(fn($u)=>!$u->isSupervisionOnly() && !$u->isServiceAccount());$expected=$attended=$punctual=0;
  foreach($staff as $u){$v=PerformanceScoringService::attendanceSummary($u,Carbon::parse($from),Carbon::parse($to));$expected+=$v['expected_workdays'];$attended+=$v['attendance_days'];$punctual+=$v['punctual_days'];}
  return ['expected'=>$expected,'attended'=>$attended,'on_time'=>$punctual,'late'=>max(0,$attended-$punctual),'absent'=>max(0,$expected-$attended),'rate'=>$expected?round(100*$attended/$expected,1):0];
 }
 private function stock(Collection $rows): int {return (int)$rows->sum(fn($r)=>(int)($r['data']['quantity']??$r['data']['quantity_procured']??1));}
 public function stats(string $tab,User $u,string $from,string $to): array {
  $staff=$this->staff($u);$active=$staff->where('status','active')->filter(fn($s)=>!$s->start_date||$s->start_date->lte(today()));$native=fn($kind)=>$this->records($kind,$u);
  $rows=fn($kind)=>HrRegisterRecord::where('kind',$kind)->get()->filter(fn($r)=>self::full($u) || $staff->contains('id',(int)($r->data['staff_id']??0)) || ($kind==='training' && $staff->pluck('id')->intersect($r->data['attendees']??[])->isNotEmpty()));$between=fn($date)=>$date && substr($date,0,10)>=$from && substr($date,0,10)<=min($to,today()->toDateString());
  $stats=[];
  switch($tab){
   case 'people':
    $stats=['Active headcount'=>$active->count(),'New joiners in period'=>$staff->filter(fn($s)=>$between($s->start_date?->toDateString()))->count(),'Exits in period'=>$rows('exit')->filter(fn($r)=>$between($r->data['last_day']??null))->unique(fn($r)=>$r->data['staff_id'])->count()];
    foreach($active->countBy(fn($s)=>User::departmentLabel($s->department)) as $label=>$n)$stats[$label]=$n;
    foreach(['Permanent','Contract','Consultant','National Service','Intern'] as $type)$stats[$type]=$active->where('employment_type',$type)->count();
    $stats['Employment type needs setup']=$active->filter(fn($s)=>!$s->employment_type)->count();break;
   case 'recruitment':$stats=['Open vacancies'=>$rows('vacancy')->where('data.state','Open')->count(),'Candidates in pipeline'=>$rows('candidate')->whereIn('data.stage',['Applied','Shortlisted','Interview','Reference check','Offer'])->count(),'Interviews this week'=>$rows('candidate')->filter(fn($r)=>isset($r->data['interview_at']) && Carbon::parse($r->data['interview_at'])->betweenIncluded(today()->startOfWeek(),today()->endOfWeek()->endOfDay()))->count(),'Hires in period'=>$staff->filter(fn($s)=>$between($s->start_date?->toDateString()))->count(),'Staff on probation'=>$rows('probation')->filter(fn($r)=>empty($r->data['confirmed_on']))->unique(fn($r)=>$r->data['staff_id'])->count()];break;
   case 'attendance':$a=$this->attendance($u,$from,$to);$ids=$staff->pluck('id');$leaves=LeaveApplication::whereIn('user_id',$ids)->get();$stats=['Attendance rate (%)'=>$a['rate'],'Late staff-days'=>$a['late'],'Absent staff-days'=>$a['absent'],'Staff on leave today'=>$leaves->where('status','approved')->filter(fn($r)=>$r->start_date->lte(today())&&$r->end_date->gte(today()))->unique('user_id')->count(),'Pending leave requests'=>$leaves->whereIn('status',['pending_manager','pending_hr','pending_cvo'])->count()];break;
   case 'performance':
    $plans=PerformanceTargetPlan::whereNotNull('approved_at')->whereNull('withdrawn_at')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)->whereIn('owner_id',$active->pluck('id'))->distinct()->pluck('owner_id');
    $appraisals=$this->appraisals($u,$from,$to);$appraisalStates=[];
    foreach($appraisals as $a)$appraisalStates[$a->user_id.':'.$a->year.':'.ltrim((string)$a->quarter,'Qq')]=$a->status==='approved';
    foreach($rows('appraisal_record') as $a)if(!empty($a->data['period_start'])&&($a->data['period_start']<=$to)&&(($a->data['period_end']??$a->data['period_start'])>=$from)){
     $d=Carbon::parse($a->data['period_start']);$key=($a->data['staff_id']??0).':'.$d->year.':'.$d->quarter;
     if(!array_key_exists($key,$appraisalStates))$appraisalStates[$key]=!empty($a->data['completed_on'])&&$a->data['completed_on']<=today()->toDateString();
    }
    $stats=['Staff with approved KPIs (%)'=>$active->count()?round(100*$plans->count()/$active->count(),1):0,'Appraisals completed'=>count(array_filter($appraisalStates)),'Appraisals pending'=>count($appraisalStates)-count(array_filter($appraisalStates)),'Trainings delivered this quarter'=>$rows('training')->filter(fn($r)=>isset($r->data['date'])&&Carbon::parse($r->data['date'])->betweenIncluded(today()->startOfQuarter(),today()))->count(),'Staff on improvement plans'=>$rows('improvement_plan')->where('data.state','Open')->unique(fn($r)=>$r->data['staff_id'])->count()];break;
   case 'relations':$r=$rows('employee_case');$stats=['Open cases'=>$r->whereIn('data.state',['Open','Investigating'])->count(),'Queries issued in period'=>$r->where('data.case_type','Query')->filter(fn($r)=>$between($r->data['opened_on']??null))->count(),'Cases resolved'=>$r->whereIn('data.state',['Resolved','Closed'])->count(),'Grievances pending'=>$r->where('data.case_type','Grievance')->whereIn('data.state',['Open','Investigating'])->count()];break;
   case 'welfare':$stats=['Birthdays this month'=>$active->filter(fn($s)=>(int)($s->date_of_birth?->month??$s->birthday_month)===today()->month)->count(),'Welfare cases open'=>$rows('welfare_case')->where('data.state','Open')->count(),'Upcoming staff events'=>$rows('engagement')->filter(fn($r)=>($r->data['date']??'')>=today()->toDateString())->count()];break;
   case 'compliance':$policies=$rows('policy');$handbook=$this->handbookSigned($active->pluck('id'));$stats=['Policies approved'=>$policies->where('data.state','Approved')->count(),'Policies in draft'=>$policies->where('data.state','Draft')->count(),'Handbook signed (%)'=>$active->count()?round(100*$handbook/$active->count(),1):0,'Exits in progress'=>$rows('exit')->filter(fn($r)=>empty($r->data['completed_on']))->count()];break;
   case 'facilities':$r=$this->facilityInventory($u);foreach(['AC','Fan','Fridge','Water dispenser','Printer'] as $c)$stats[$c]=$this->stock($r->where('data.category',$c)->where('data.condition','!=','Disposed'));$stats['Faulty / damaged / under repair']=$this->stock($r->whereIn('data.condition',['Faulty','Damaged','Under repair']));$stats['Open maintenance activities']=HrDashboardActivity::whereIn('tab',['facilities','operations'])->whereNotIn('status',['Completed','Cancelled'])->count();break;
   case 'fleet':$r=$rows('vehicle');$stats=['Total vehicles'=>$r->where('data.state','!=','Retired')->count(),'Available'=>$r->where('data.state','Available')->count(),'In use'=>$r->where('data.state','In use')->count(),'Under maintenance'=>$r->where('data.state','Under maintenance')->count(),'Documents expiring in 30 days'=>$r->sum(function($r){$n=0;foreach(['insurance_expiry','roadworthy_expiry'] as $f)if(!empty($r->data[$f])&&Carbon::parse($r->data[$f])->betweenIncluded(today(),today()->addDays(30)))$n++;return $n;}),'Fuel spend this month (GHS)'=>$rows('fuel')->filter(fn($r)=>!empty($r->data['date'])&&Carbon::parse($r->data['date'])->betweenIncluded(today()->startOfMonth(),today()))->sum('data.amount')];break;
   case 'assets':$r=$native('asset')->where('data.condition','!=','Disposed');$stats=['Total assets'=>$this->stock($r->where('data.condition','!=','Disposed')),'Assigned'=>$this->stock($r->filter(fn($r)=>!empty($r['data']['assigned_to']))),'Unassigned'=>$this->stock($r->filter(fn($r)=>empty($r['data']['assigned_to']))),'Damaged / lost'=>$this->stock($r->whereIn('data.condition',['Damaged','Lost']))];foreach($r->groupBy(fn($r)=>$r['data']['category']?:'Category needs setup') as $c=>$items)$stats[$c]=$this->stock($items);break;
   case 'supplies':$stats=['Total vendors'=>$native('vendor')->count(),'Active vendors'=>$native('vendor')->where('data.state','Active')->count(),'Vendor status needs setup'=>$native('vendor')->filter(fn($r)=>empty($r['data']['state']))->count(),'Pending purchase requests'=>$rows('purchase')->where('data.state','Pending')->count(),'Memos issued in period'=>$rows('communication')->where('data.document_type','Memo')->filter(fn($r)=>$between($r->data['date']??null))->count()];break;
  }
  return $stats;
 }
 public function facilityInventory(User $u): Collection {
  $assets=$this->records('asset',$u);$facilities=$this->records('facility',$u);$assetMap=$assets->keyBy('id');
  $linked=$facilities->pluck('data.asset_id')->filter()->map(fn($id)=>(int)$id);
  $facilities=$facilities->map(function($r)use($assetMap){$asset=$assetMap->get((int)($r['data']['asset_id']??0));if($asset)$r['data']=array_replace($r['data'],['quantity'=>$asset['data']['quantity_procured'],'condition'=>$asset['data']['condition'],'location'=>$asset['data']['location']]);return $r;});
  foreach($assets as $asset){if($linked->contains($asset['id']))continue;$text=mb_strtolower(($asset['data']['category']??'').' '.$asset['label']);$category=match(true){(bool)preg_match('/\b(ac|air conditioner|air conditioning)\b/',$text)=>'AC',str_contains($text,'fan')=>'Fan',str_contains($text,'fridge')||str_contains($text,'refrigerator')=>'Fridge',str_contains($text,'dispenser')=>'Water dispenser',str_contains($text,'printer')=>'Printer',default=>null};if(!$category)continue;$facilities->push(['id'=>$asset['id'],'label'=>$asset['label'],'data'=>['category'=>$category,'quantity'=>$asset['data']['quantity_procured'],'condition'=>$asset['data']['condition'],'location'=>$asset['data']['location']],'source'=>'Office Asset Register']);}
  return $facilities;
 }
 public function appraisals(User $u,string $from,string $to): Collection {
  return Appraisal::with('user:id,name,department')->whereIn('user_id',$this->staff($u)->pluck('id'))->get()->filter(function($r)use($from,$to){
   $q=(int)ltrim((string)$r->quarter,'Qq');if($q<1||$q>4||!$r->year)return false;
   $start=Carbon::create((int)$r->year,($q-1)*3+1,1)->startOfDay();$end=$start->copy()->endOfQuarter();
   return $start->toDateString()<=$to&&$end->toDateString()>=$from;
  });
 }
 public function handbookSigned(\Illuminate\Support\Collection $ids): int {
  $documents=HrRegisterRecord::where('kind','staff_document')->get()->filter(fn($r)=>$ids->contains((int)($r->data['staff_id']??0))&&($r->data['document_type']??'')==='Handbook acknowledgement'&&!empty($r->attachment)&&!empty($r->data['received_on'])&&$r->data['received_on']<=today()->toDateString())->pluck('data.staff_id');
  $handbooks=HrRegisterRecord::where('kind','policy')->get()->filter(fn($r)=>str_contains(mb_strtolower($r->data['name']??''),'handbook')&&($r->data['state']??'')==='Approved')->pluck('id');
  $signed=HrRegisterRecord::where('kind','acknowledgement')->get()->filter(fn($r)=>$ids->contains((int)($r->data['staff_id']??0))&&$handbooks->contains((int)($r->data['policy_id']??0))&&!empty($r->data['signed_on'])&&$r->data['signed_on']<=today()->toDateString())->pluck('data.staff_id');
  return $documents->merge($signed)->map(fn($id)=>(int)$id)->unique()->count();
 }
 public function options(string $kind,User $viewer): array {
  $key='hr_register_options.'.$kind.'.'.$viewer->id;$request=request();if($request->attributes->has($key))return $request->attributes->get($key);
  if($kind==='staff')$result=$this->staff($viewer)->pluck('name','id')->all();
  elseif(in_array($kind,['asset','vendor']))$result=$this->records($kind,$viewer)->pluck('label','id')->all();
  else{$s=$this->schema($kind);$result=$s&&self::tabAllowed($viewer,$s['tab'])?$this->records($kind,$viewer)->pluck('label','id')->all():[];}
  $request->attributes->set($key,$result);return $result;
 }
 public function linkedLabel(?string $type,?int $id,User $viewer): ?string {if(!$type||!$id)return null;return $this->options($type,$viewer)[$id]??'Linked record archived / unavailable';}
 public function alerts(User $viewer): array {
  $alerts=[];$add=function($label,$date,$kind,$id)use(&$alerts){if($date && Carbon::parse($date)->lte(today()->addDays(30)))$alerts[]=['label'=>$label,'date'=>substr($date,0,10),'tab'=>$kind,'id'=>$id];};
  if(!self::full($viewer))return [];
  foreach($this->staff($viewer)->where('status','active') as $person){$add('Contract ending: '.$person->name,$person->contract_expires_at?->toDateString(),'people',$person->id);$month=(int)($person->date_of_birth?->month??$person->birthday_month);$day=(int)($person->date_of_birth?->day??$person->birthday_day);foreach([today()->startOfWeek()->year,today()->endOfWeek()->year] as $year){if(checkdate($month,$day,$year)){$birthday=Carbon::create($year,$month,$day)->startOfDay();if($birthday->betweenIncluded(today()->startOfWeek(),today()->endOfWeek())){$alerts[]=['label'=>'Birthday: '.$person->name,'date'=>$birthday->toDateString(),'tab'=>'welfare','id'=>$person->id];break;}}}}
  foreach(['probation'=>['confirmation_due'=>'Probation confirmation'],'vehicle'=>['insurance_expiry'=>'Vehicle insurance','roadworthy_expiry'=>'Roadworthy','service_due'=>'Vehicle service'],'driver'=>['licence_expiry'=>'Driver licence'],'policy'=>['review_on'=>'Policy review']] as $kind=>$fields)foreach(HrRegisterRecord::where('kind',$kind)->get() as $row){
   if($kind==='probation'&&!empty($row->data['confirmed_on']))continue;if($kind==='vehicle'&&($row->data['state']??'')==='Retired')continue;if($kind==='policy'&&($row->data['state']??'')==='Retired')continue;
   foreach($fields as $f=>$label)$add($label.': '.$row->label,$row->data[$f]??null,$this->schema($kind)['tab'],$row->id);
   if($kind==='vehicle'&&isset($row->data['service_due_mileage'],$row->data['mileage'])&&$row->data['mileage']>=$row->data['service_due_mileage'])$alerts[]=['label'=>'Vehicle service mileage reached: '.$row->label,'date'=>today()->toDateString(),'tab'=>'fleet','id'=>$row->id];
  }
  foreach(HrRegisterRecord::where('kind','supply')->get() as $r)if(isset($r->data['quantity'],$r->data['reorder_level'])&&$r->data['quantity']<$r->data['reorder_level'])$alerts[]=['label'=>'Below reorder level: '.$r->label,'date'=>today()->toDateString(),'tab'=>'supplies','id'=>$r->id];
  foreach($this->documentGaps($viewer) as $gap)$alerts[]=['label'=>'Missing staff documents: '.$gap['name'].' — '.implode(', ',$gap['missing']),'date'=>today()->toDateString(),'tab'=>'people','id'=>$gap['id']];
  return collect($alerts)->sortBy('date')->values()->all();
 }
 public function documentGaps(User $viewer): array {
  $documents=HrRegisterRecord::where('kind','staff_document')->get()->filter(fn($r)=>$r->attachment&&!empty($r->data['received_on'])&&$r->data['received_on']<=today()->toDateString());$gaps=[];
  foreach($this->staff($viewer)->where('status','active') as $u){$present=$documents->filter(fn($r)=>(int)($r->data['staff_id']??0)===$u->id)->pluck('data.document_type')->all();
   foreach(['contract_path'=>'Signed contract','job_description_path'=>'Signed JD','national_id_front_path'=>'ID','ghana_card_path'=>'ID','ghana_card_front_path'=>'ID'] as $f=>$label)if(app(HrStaffRecord::class)->document($u,$f))$present[]=$label;
   $missing=array_values(array_diff(['CV','Referees','ID','Certificates','Signed contract','Signed JD','Handbook acknowledgement'],$present));
   if($missing)$gaps[]=['id'=>$u->id,'name'=>$u->name,'missing'=>$missing];
  }return $gaps;
 }
 public function value(mixed $value,array $field,User $viewer): string {
  if($value===null||$value==='')return 'Needs setup';
  $type=$field['type']??'text';if($type==='checkbox')return $value?'Yes':'No';
  if($type==='department')return User::departmentLabel((string)$value);
  if($type==='staff')return User::internalStaff()->find($value)?->name??'Staff record unavailable';
  if($type==='staff_multi')return User::internalStaff()->whereIn('id',(array)$value)->pluck('name')->join(', ');
  if(str_starts_with($type,'record='))return $this->options(substr($type,7),$viewer)[$value]??'Record unavailable';
  return is_array($value)?implode(', ',$value):(string)$value;
 }
}
