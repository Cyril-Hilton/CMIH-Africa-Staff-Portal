<?php

namespace App\Services;

use App\Models\{CreativeRequest, CrmAccountTarget, CrmPipelineProject, PerformanceRegister, User, WeeklyConsolidatedItem};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Reporting obligations and business results are distinct measures. Missing actuals are never recorded as revenue of zero. */
class DepartmentSourcePerformance
{
    public function forRequest(string $department, string $from, string $to, ?int $owner = null, bool $includeAttendance = true): array
    {
        $key = 'department_source_performance.'.hash('sha256', $department.'|'.$from.'|'.$to.'|'.$owner.'|'.(int)$includeAttendance);
        $request = request();
        if (!$request->attributes->has($key)) $request->attributes->set($key, $this->build($department, $from, $to, $owner, $includeAttendance));
        return $request->attributes->get($key);
    }

    public function build(string $department, string $from, string $to, ?int $owner = null, bool $includeAttendance = true): array
    {
        $department=User::normalizeDepartmentKey($department);
        $records=PerformanceRegister::whereIn('department',User::departmentAliases($department))->whereNotNull('approved_at')
            ->where('status','!=','cancelled')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
            ->when($owner,fn($q)=>$q->where('owner_id',$owner))->get();
        if($department==='finance')$records=$records->filter(fn($r)=>(empty($r->data['_finance_currency'])||($r->period_start->toDateString()>=$from&&$r->period_end->toDateString()<=$to))&&FinanceDashboard::published($r));
        $weekly=WeeklyConsolidatedItem::whereIn('department',User::departmentAliases($department))
            ->whereIn('workflow_state',['legacy','approved','verified','closed'])
            ->where(fn($q)=>$q->whereNull('report_status')->orWhereNotIn('report_status',['draft','submitted','returned']))
            ->whereBetween('week_start',[$from,$to])->when($owner,fn($q)=>$q->where('lead_staff_id',$owner))->get();
        $areas=[]; $metrics=[];
        $area=function(string $name,int $count,string $missing) use (&$areas) {
            $areas[]=['name'=>$name,'records'=>$count,'reported'=>$count>0,'score'=>$count>0?100.0:0.0,
                'state'=>$count>0?'Reported':'Missing report','gap'=>$count>0?null:$missing];
        };
        $count=fn(array $kinds)=>$records->whereIn('kind',$kinds)->count();
        if ($department==='client_relations') {
            $projects=CrmPipelineProject::where('source_period_confirmed',true)->whereDate('week_start','<=',$to)->whereRaw('COALESCE(week_end, week_start) >= ?',[$from])
                ->when($owner,fn($q)=>$q->where('owner_id',$owner))->get();
            $projects=app(ClientRelationsCentre::class)->latest($projects);
            $targets=CrmAccountTarget::where('needs_reconciliation',false)->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
                ->when($owner,fn($q)=>$q->where('owner_id',$owner))->get();
            $clientRecords=$records->where('kind','client');
            $revenue=$projects->filter(fn($p)=>$p->actual_amount!==null)->count()+$targets->filter(fn($p)=>$p->actual_amount!==null)->count()
                +$clientRecords->filter(fn($r)=>isset($r->data['recognized_revenue']))->count();
            $growth=$projects->filter(fn($p)=>$p->new_business||$p->proposed_amount!==null)->count()
                +$clientRecords->filter(fn($r)=>isset($r->data['prior_revenue'])||isset($r->data['new_business'])||isset($r->data['cross_sell_value']))->count();
            $area('Projects & delivery',$projects->count()+$weekly->count(),'No approved project / delivery report for this period.');
            $area('Revenue',$revenue,'No revenue result reported. Project entries do not satisfy revenue reporting.');
            $area('Growth & pipeline',$growth,'No growth / pipeline evidence reported. Project titles alone do not establish growth.');
            // Choose account targets when supplied; repeated project snapshots must not be added to the same account target.
            $targetKeys=$targets->map(fn($r)=>mb_strtolower(trim($r->client_name)).'|'.$r->owner_id);
            $targetRows=$targets->concat($projects->whereNotNull('target_amount')->reject(fn($r)=>$targetKeys->contains(mb_strtolower(trim($r->client_name)).'|'.$r->owner_id)));
            $knownTargets=$targetRows->whereNotNull('target_amount')->filter(fn($r)=>(float)$r->target_amount>0);
            $target=$knownTargets->sum('target_amount');
            $confirmed=$knownTargets->filter(fn($r)=>$r->confirmedRevenue()!==null);
            $actual=$confirmed->sum(fn($r)=>$r->confirmedRevenue());
            $metrics[]=$this->metric('Revenue target achievement',$target>0?$target:null,$confirmed->isNotEmpty()?$actual:null,'GHS',
                $knownTargets->count(),$confirmed->count(),'Finance-confirmed revenue / all recorded targets. Unreported target rows remain in the denominator.');
            $wins=$projects->whereIn('stage',['won','project_ongoing','project_completed'])->count();
            $decided=$wins+$projects->whereIn('stage',['lost','closed_lost'])->count();
            $dueProjects=$projects->filter(fn($p)=>$p->expected_close_date && $p->expected_close_date->toDateString()>=$from && $p->expected_close_date->toDateString()<=min($to,today()->toDateString()));
            $metrics[]=$this->metric('Due project delivery',$dueProjects->count()?:null,$dueProjects->isEmpty()?null:$dueProjects->where('stage','project_completed')->count(),'projects',$dueProjects->count(),$dueProjects->count(),'Completed projects / projects with recorded delivery dates due in this period.');
            $metrics[]=$this->metric('Deal conversion',$decided?:null,$decided?$wins:null,'deals',$decided,$decided,'Won projects / decided projects. No decisions means no conversion rate yet.');
        } elseif ($department==='creatives') {
            $briefs=CreativeRequest::studioWork()->with(['firstSubmission','hodDeadlineSnapshots'])->whereNull('parent_request_id')->where(function($q) use($from,$to) { $q->whereBetween('received_at',[$from.' 00:00:00',$to.' 23:59:59'])
                ->orWhereBetween('requested_deadline',[$from.' 00:00:00',$to.' 23:59:59'])
                ->orWhereBetween('internal_eta',[$from.' 00:00:00',$to.' 23:59:59'])
                ->orWhereBetween('completed_at',[$from.' 00:00:00',$to.' 23:59:59']);
                \App\Services\Creative\CreativeReportingPeriod::includeHistory($q,$from.' 00:00:00',$to.' 23:59:59');
            })->when($owner,fn($q)=>$q->where('assignee_id',$owner))->get();
            $area('Briefs & planning',$briefs->count()+$count(['brief']),'No brief / planning report recorded.');
            $metrics[]=$this->metric('Creative planning completion',$briefs->count()?:null,$briefs->isEmpty()?null:$briefs->filter(fn($b)=>$b->assignee_id&&$b->planned_start&&$b->internal_eta)->count(),'briefs',$briefs->count(),$briefs->count(),'Assigned briefs with a recorded work window / briefs received in scope.');
            $area('Delivery & review',$briefs->filter(fn($b)=>$b->first_submitted_at||$b->submitted_at||$b->completed_at)->count()+$records->where('kind','brief')->filter(fn($r)=>isset($r->data['final_delivered_on']))->count(),'No submission / delivery result reported.');
            $area('Quality & revisions',$briefs->whereNotNull('quality_score')->count()+$records->where('kind','brief')->filter(fn($r)=>isset($r->data['quality_score'])||isset($r->data['revision_rounds']))->count(),'No quality or revision result recorded.');
            $quality=$briefs->whereNotNull('completed_at')->filter(fn($b)=>$b->completed_at->toDateString()>=$from && $b->completed_at->toDateString()<=$to);
            if($quality->isNotEmpty())$metrics[]=$this->metric('Quality score',$quality->count()*100,$quality->whereNotNull('quality_score')->isEmpty()?null:$quality->sum('quality_score'),'quality points',$quality->count(),$quality->whereNotNull('quality_score')->count(),'Recorded verified quality points / maximum available quality points. Missing grades earn zero credit.');
            $due=$briefs->filter(function($brief) use($from,$to) {
                $date=\App\Services\Creative\CreativeDeadlines::scoringDue($brief);
                return $date && $date->toDateString()>=$from && $date->toDateString()<=$to && $date->lte(now());
            });
            $onTime=$due->filter(fn($b)=>$b->first_submitted_at&&$b->first_submitted_at->lte(\App\Services\Creative\CreativeDeadlines::scoringDue($b)));
            $metrics[]=$this->metric('On-time first submissions',$due->count()?:null,$due->isNotEmpty()?$onTime->count():null,'briefs',$due->count(),$due->whereNotNull('first_submitted_at')->count(),'On-time first submissions / all briefs due against the Creative HOD deadline. Missing submissions earn zero credit; future due dates are excluded.');
        } else {
            $categories=match($department) {
                'operations_projects'=>[['Projects & activations',['project','activation','weekly_update','team_allocation']],['Assets & POSM',['posm_deployment']],['Procurement & execution',['fabrication','procurement','supplier','production','milestone']],['Reports, approvals & risks',['client_approval','project_report','risk_issue','risk']]],
                'brands_marketing'=>[['People & team coverage',['people']],['Merchandising performance',['perfect_store','campaign']],['Activations & visibility',['activation','visibility']],['Weekly workstreams',['workstream']]],
                'finance'=>[['Revenue',['revenue','revenue_target','profitability']],['Invoices',['invoice','payable']],['Cash Flow',['cash','receipt','payment']],['Expenses',['expense']],['Commercial Risks',['commercial_risk']],['Payroll',['payroll']],['Taxes & SSNIT',['tax']]],
                'hr_admin'=>[['Workforce & attendance',['workforce']],['Recruitment',['vacancy']],['Employee reviews & cases',['employee_case','improvement_plan']],['Fleet & facilities',['vehicle','fuel','maintenance','trip','asset','facility_request']]],
                default=>[],
            };
            $hrSections=collect(); $adminLines=0;
            if($department==='hr_admin') {
                $hrSections=\App\Models\HrPeriodicReport::with('entries')->where('status','approved')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
                    ->when($owner,fn($q)=>$q->where('prepared_by',$owner))->get()->flatMap(fn($r)=>$r->entries)->countBy('section');
                $adminLines=\App\Models\AdminWeeklyReport::withCount('lines')->where('status','approved')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
                    ->when($owner,fn($q)=>$q->where('prepared_by',$owner))->get()->sum('lines_count');
            }
            foreach($categories as [$name,$kinds]) {
                $n=$count($kinds);
                if($department==='finance'&&$name==='Revenue')$n+=app(ClientRelationsCentre::class)->latest(CrmPipelineProject::where('source_period_confirmed',true)->whereDate('week_start','>=',$from)->whereDate('week_start','<=',$to)->get())->filter(fn($p)=>(!$owner||(int)$p->revenue_finance_by===(int)$owner)&&$p->confirmedRevenue()!==null)->count();
                if($department==='hr_admin') $n+=match($name) {
                    'Workforce & attendance'=>$hrSections->get('metrics',0)+$hrSections->get('attendance_leave',0)+$hrSections->get('employee_admin',0),
                    'Recruitment'=>$hrSections->get('recruitment',0),
                    'Employee reviews & cases'=>$hrSections->get('performance',0)+$hrSections->get('employee_relations',0),
                    'Fleet & facilities'=>$adminLines,
                    default=>0,
                };
                if(in_array('workstream',$kinds,true)||in_array('weekly_update',$kinds,true))$n+=$weekly->count();
                $area($name,$n,'No published '.$name.' report for this period.');
            }
            $pairs=match($department) {
                'operations_projects'=>[['Activation target achievement',['activation'],'target_quantity','actual_quantity']],
                'brands_marketing'=>[['Active staff coverage',['people'],'planned_count','active_count'],['Visibility coverage',['visibility'],'planned_scope','active_scope'],['Visibility deployment',['visibility'],'scheduled','deployed'],['Sales target achievement',['activation'],'target_sales','total_sales'],['Reach target achievement',['activation'],'target_reach','total_reach'],['Outlet coverage',['campaign'],'outlets_planned','outlets_visited'],['Perfect Store achievement',['perfect_store','campaign'],'perfect_store_target_percent','perfect_store_actual_percent']],
                'finance'=>[['Revenue target achievement',['revenue'],'target','actual']],
                'hr_admin'=>[['Workforce coverage',['workforce'],'approved_headcount','actual_headcount'],['Appraisals completed',['workforce'],'reviews_due','reviews_completed']],
                default=>[],
            };
            foreach($pairs as [$name,$kinds,$targetField,$actualField]) {
                $eligible=$records->whereIn('kind',$kinds)->filter(fn($r)=>$name!=='Perfect Store achievement'||($r->data['breakdown']??'overall')==='overall')
                    ->filter(fn($r)=>isset($r->data[$targetField])&&is_numeric($r->data[$targetField])&&(float)$r->data[$targetField]>0);
                if (in_array($name,['Active staff coverage','Workforce coverage'],true)) {
                    $eligible=$eligible->sortBy([['period_start','desc'],['id','desc']])->unique(fn($r)=>$r->kind.'|'.($r->data['cohort']??'').'|'.($r->data['scope']??$r->client??$r->owner_id));
                }
                $unitKey=match($name) {'Activation target achievement'=>'target_measure','Sales target achievement'=>'sales_unit',default=>null};
                $groups=$unitKey?$eligible->groupBy(fn($r)=>trim($r->data[$unitKey]??'Unspecified units')):collect(['units'=>$eligible]);
                if($groups->isEmpty())$groups=collect(['units'=>collect()]);
                foreach($groups as $unit=>$scope) {
                    $measured=$scope->filter(fn($r)=>isset($r->data[$actualField])&&is_numeric($r->data[$actualField]));
                    $metrics[]=$this->metric($name.($unitKey&&$unit!=='units'?' · '.$unit:''),$scope->isEmpty()?null:$scope->sum(fn($r)=>(float)$r->data[$targetField]),
                        $measured->isEmpty()?null:$measured->sum(fn($r)=>(float)$r->data[$actualField]),(string)$unit,$scope->count(),$measured->count(),
                        'Reported actual / all recorded targets for the same measure, including target rows with no reported actual.');
                }
            }
        }
        $metrics = array_merge($metrics, $this->additionalMetrics($department, $records, $weekly, $from, $to));
        if($department==='hr_admin'){
            $live=app(HrRegisterKpis::class)->build($from,$to,$owner);$areas=$live['areas'];
            $legacyMetrics=collect($metrics)->filter(fn($m)=>$m['target']!==null)->map(function($m){
                $tab=str_contains($m['name'],'recruitment')?'recruitment':(str_contains($m['name'],'Appraisals')?'performance':(str_contains($m['name'],'maintenance')?'facilities':(str_contains($m['name'],'fuel')?'fleet':'people')));
                $m['area']=config('hr_workspace.tabs.'.$tab.'.label');return $m;
            });
            $metrics=array_merge($live['metrics'],$legacyMetrics->all());
            $metrics=collect($metrics)->reject(fn($m)=>$m['target']===null&&str_contains($m['name'],'on-time activity')&&collect($metrics)->contains(fn($n)=>$n['area']===$m['area']&&$n['target']!==null))->values()->all();
        }
        if($department==='finance'&&$records->where('kind','revenue_target')->isNotEmpty()){
            $targets=$records->where('kind','revenue_target')->filter(fn($r)=>isset($r->data['target'])&&$r->data['target']>0);
            $ledger=$records->where('kind','revenue')->reject(fn($r)=>($r->data['revenue_type']??'monthly_total')==='direct');$direct=$records->where('kind','revenue')->filter(fn($r)=>($r->data['revenue_type']??'')==='direct');
            $crm=app(ClientRelationsCentre::class)->latest(CrmPipelineProject::where('source_period_confirmed',true)->whereDate('week_start','>=',$from)->whereDate('week_start','<=',$to)->where(fn($q)=>$q->whereNull('week_end')->orWhereDate('week_end','<=',$to))->get())->filter(fn($r)=>(!$owner||(int)$r->revenue_finance_by===(int)$owner)&&$r->confirmedRevenue()!==null);
            $actual=$ledger->isNotEmpty()?$ledger->sum(fn($r)=>(float)($r->data['actual']??0)):$direct->sum(fn($r)=>(float)($r->data['actual']??0))+$crm->sum(fn($r)=>(float)$r->confirmedRevenue());
            $metrics=collect($metrics)->reject(fn($m)=>$m['name']==='Revenue target achievement')->values()->all();
            $metrics[]=$this->metric('Revenue target achievement',$targets->isEmpty()?null:$targets->sum(fn($r)=>(float)$r->data['target']),$actual,'GHS',$targets->count(),$targets->count(),'Published revenue / monthly revenue targets. Additional Finance revenue and jointly confirmed CRM revenue count once; consolidated monthly totals are an alternative source.');
        }
        $areaNames = array_column($areas, 'name');
        foreach ($metrics as &$metric) {
            $metric['area'] = $metric['area'] ?? $this->areaFor($department, $metric['name'], $areaNames);
            $metric['credited'] = min(100, max(0, $metric['achievement'] ?? 0));
        }
        unset($metric);
        // Each required area has one equal share. Additional rows cannot increase its weight.
        foreach ($areas as &$reportArea) {
            $measures = collect($metrics)->where('area', $reportArea['name']);
            $reportArea['achievement_credit'] = $reportArea['reported'] ? (PerformanceMath::creditedWeightedScore($measures) ?? 0.0) : 0.0;
            $reportArea['measured'] = $measures->whereNotNull('achievement')->count();
            $reportArea['results_expected'] = $measures->count();
            $reportArea['result_gap'] = !$reportArea['reported'] ? 'Required report missing · 0 achievement credit'
                : ($measures->isEmpty() || $measures->contains(fn($m)=>$m['target']===null || $m['reported']<$m['expected']) ? 'Target or measurable result needs setup · 0 credit for each missing measure' : null);
        }
        unset($reportArea);
        $reported=collect($areas)->where('reported',true)->count();
        $businessCredit = PerformanceMath::creditedWeightedScore(array_map(fn($a)=>['achievement'=>$a['achievement_credit']],$areas));
        return ['department'=>$department,'areas'=>$areas,'metrics'=>$metrics,'expected'=>count($areas),'reported'=>$reported,'missing'=>count($areas)-$reported,
            'achievement_credit'=>$businessCredit ?? 0.0,
            'scoring_note'=>'Equal share per required reporting area; recorded targets and actuals determine achievement. Missing reports, targets or results earn zero credit. Manager-assigned KPI weights take priority in formal scoring.',
            'attendance'=>$includeAttendance?$this->attendance($department,$from,$to,$owner):null,
            'coverage'=>count($areas)?round(100*$reported/count($areas),1):null,
            'from'=>$from,'to'=>$to,'native_sources'=>$department==='hr_admin','provisional'=>Carbon::parse($to)->endOfDay()->gte(now())];
    }

    private function areaFor(string $department, string $name, array $areas): ?string
    {
        return match($department) {
            'brands_marketing'=>match(true) {
                str_contains($name,'staff')=>'People & team coverage',
                str_contains($name,'workstream')=>'Weekly workstreams',
                str_contains($name,'Sales')||str_contains($name,'Reach')||str_contains($name,'Visibility')=>'Activations & visibility',
                default=>'Merchandising performance',
            },
            'client_relations'=>str_contains($name,'Revenue')?'Revenue':(str_contains($name,'conversion')?'Growth & pipeline':'Projects & delivery'),
            'creatives'=>str_contains($name,'Quality')||str_contains($name,'approval')?'Quality & revisions':(str_contains($name,'planning')?'Briefs & planning':'Delivery & review'),
            'operations_projects'=>match(true) {
                str_contains($name,'POSM')=>'Assets & POSM',
                str_contains($name,'procurement')||str_contains($name,'production')||str_contains($name,'budget')=>'Procurement & execution',
                str_contains($name,'report')||str_contains($name,'risk')=>'Reports, approvals & risks',
                default=>'Projects & activations',
            },
            'hr_admin'=>str_contains($name,'recruitment')?'Recruitment':(str_contains($name,'Appraisals')?'Employee reviews & cases':((str_contains($name,'maintenance')||str_contains($name,'fuel'))?'Fleet & facilities':'Workforce & attendance')),
            'finance'=>match(true){str_contains($name,'collection')=>'Invoices',str_contains($name,'expense')=>'Expenses',str_contains($name,'cash payment')||str_contains($name,'cash receipt')=>'Cash Flow',str_contains($name,'Payroll')=>'Payroll',str_contains($name,'Statutory')=>'Taxes & SSNIT',str_contains($name,'commercial risk')=>'Commercial Risks',default=>'Revenue'},
            default=>$areas[0]??null,
        };
    }

    private function attendance(string $department,string $from,string $to,?int $owner): array
    {
        $users=User::internalStaff()->whereIn('department',User::departmentAliases($department))->where('status','active')
            ->when($owner,fn($q)=>$q->whereKey($owner))->get();
        $totals=['expected'=>0,'clocked'=>0,'punctual'=>0];
        foreach($users as $user) {
            $a=PerformanceScoringService::attendanceSummary($user,Carbon::parse($from),Carbon::parse($to));
            $totals['expected']+=$a['expected_workdays']; $totals['clocked']+=$a['attendance_days']; $totals['punctual']+=$a['punctual_days'];
        }
        return $totals+['late'=>max(0,$totals['clocked']-$totals['punctual']),'missing'=>max(0,$totals['expected']-$totals['clocked']),
            'attendance_credit'=>PerformanceMath::ratio($totals['clocked'],$totals['expected'])??0.0,
            'punctuality_credit'=>PerformanceMath::ratio($totals['punctual'],$totals['expected'])??0.0];
    }

    private function additionalMetrics(string $department,Collection $records,Collection $weekly,string $from,string $to): array
    {
        $metrics=[]; $cutoff=min($to,today()->toDateString());
        $due=fn($rows,$key)=>$rows->filter(fn($r)=>!empty($r->data[$key]) && substr($r->data[$key],0,10)>=$from && substr($r->data[$key],0,10)<=$cutoff);
        $ratio=function($name,$rows,$passed,$note) use(&$metrics) {
            if($rows->isEmpty())return;
            $metrics[]=$this->metric($name,(float)$rows->count(),(float)$rows->filter($passed)->count(),'records',$rows->count(),$rows->count(),$note);
        };
        if($department==='brands_marketing') {
            foreach(['coverage'=>'Coverage','osa'=>'On-shelf availability','innovation'=>'Innovation','must_have'=>'Must-have assortment','npd'=>'NPD availability'] as $key=>$label) {
                $rows=$records->whereIn('kind',['perfect_store','campaign'])->filter(fn($r)=>($r->data['breakdown']??'overall')==='overall' && isset($r->data[$key.'_target_percent']) && $r->data[$key.'_target_percent']>0);
                if($rows->isEmpty())continue;
                $measured=$rows->filter(fn($r)=>isset($r->data[$key.'_actual_percent']));
                $metrics[]=$this->metric($label.' target achievement',$rows->sum(fn($r)=>(float)$r->data[$key.'_target_percent']),$measured->isEmpty()?null:$measured->sum(fn($r)=>(float)$r->data[$key.'_actual_percent']),'percentage points',$rows->count(),$measured->count(),'Same-scope actual percentages / recorded target percentages. Missing actuals remain in the target denominator.');
            }
            $latest=$weekly->sortBy([['week_start','desc'],['id','desc']])->unique(fn($r)=>$r->report_series?:'entry-'.$r->id);
            $ratio('Published workstream completion',$latest,fn($r)=>in_array($r->workflow_state,['verified','closed'],true)||($r->report_status==='reviewed'&&$r->status==='Done'),'Completed workstreams with manager publication / latest published workstreams. Distinct from independent task verification.');
        }
        if($department==='operations_projects') {
            $ratio('Due project completion',$due($records->where('kind','project'),'ends_on'),fn($r)=>strtolower($r->data['project_status']??'')==='completed','Completed projects / projects with recorded end dates due in this period.');
            $ratio('Due POSM returns',$due($records->where('kind','posm_deployment'),'expected_return_on'),fn($r)=>isset($r->data['returned_quantity'],$r->data['quantity']) && $r->data['returned_quantity']>=$r->data['quantity'],'Fully returned deployments / deployments due for return; partial returns do not count as complete.');
            $ratio('Due procurement fulfilment',$due($records->where('kind','procurement'),'required_on'),fn($r)=>strtolower($r->data['delivery_status']??'')==='delivered','Delivered procurement / recorded items due.');
            $ratio('Due production completion',$due($records->where('kind','production'),'due_on'),fn($r)=>strtolower($r->data['production_status']??'')==='completed','Completed production / recorded production due.');
            $ratio('On-time project reports',$due($records->where('kind','project_report'),'due_on'),fn($r)=>!empty($r->data['shared_on']) && $r->data['shared_on']<=$r->data['due_on'],'Reports shared by deadline / all dated reports due.');
            $ratio('Due risk resolution',$due($records->whereIn('kind',['risk_issue','risk']),'due_on'),fn($r)=>!empty($r->data['resolved_on']) && $r->data['resolved_on']<=$r->data['due_on'],'Issues resolved by deadline / dated issues due.');
        }
        if($department==='finance') {
            foreach(['payroll'=>'Payroll on-time settlement','tax'=>'Statutory on-time settlement','payment'=>'On-time cash payment','receipt'=>'On-time cash receipt'] as $kind=>$label){
                $rows=$due($records->where('kind',$kind),'due_on');
                if(in_array($kind,['payment','receipt']))$rows=$due($records->where('kind',$kind),'expected_on');
                $ratio($label,$rows,fn($r)=>isset($r->data['value'],$r->data['paid_amount'])&&$r->data['value']>0&&$r->data['paid_amount']>=$r->data['value']&&!empty($r->data['paid_on'])&&$r->data['paid_on']<=($r->data[in_array($kind,['payment','receipt'])?'expected_on':'due_on']??''),'Fully settled by the recorded due date / all obligations due. Missing results earn zero credit.');
            }
            $ratio('On-time commercial risk resolution',$due($records->where('kind','commercial_risk'),'due_on'),fn($r)=>in_array($r->status,['resolved','completed'],true)&&!empty($r->data['resolved_on'])&&$r->data['resolved_on']<=$r->data['due_on'],'Risks resolved by their recorded resolution deadline / risks due.');
            $rows=$due($records->where('kind','invoice'),'due_on')->filter(fn($r)=>isset($r->data['value'])&&$r->data['value']>0);
            if($rows->isNotEmpty()) {
                $known=$rows->filter(fn($r)=>isset($r->data['paid_amount']));
                $target=$rows->sum(fn($r)=>max(0,$r->data['value']-($r->data['credited_amount']??0)));
                $metrics[]=$this->metric('Due invoice collection',$target?:null,$known->isEmpty()?null:$known->sum(fn($r)=>min(max(0,$r->data['value']-($r->data['credited_amount']??0)),$r->data['paid_amount'])),'GHS',$rows->count(),$known->count(),'Payments / invoice amounts due net of credits. Missing payments receive zero achievement credit.');
            }
        }
        if($department==='hr_admin') {
            $ratio('On-time recruitment',$due($records->where('kind','vacancy'),'target_fill_on'),fn($r)=>!empty($r->data['offer_accepted_on'])&&$r->data['offer_accepted_on']<=$r->data['target_fill_on'],'Offers accepted by target fill date / vacancies due.');
            $ratio('On-time maintenance',$due($records->where('kind','maintenance'),'scheduled_on'),fn($r)=>!empty($r->data['completed_on'])&&$r->data['completed_on']<=$r->data['scheduled_on'],'Maintenance completed by its scheduled date / recorded maintenance due.');
        }
        $budgetKinds=match($department){'finance'=>['expense'=>'Operating expense budget compliance'],'operations_projects'=>['project'=>'Project budget compliance'],'hr_admin'=>['fuel'=>'Fleet fuel budget compliance'],default=>[]};
        foreach($budgetKinds as $kind=>$label) {
            $rows=$records->where('kind',$kind)->filter(fn($r)=>isset($r->data['budget'])&&is_numeric($r->data['budget'])&&$r->data['budget']>0);
            if($rows->isEmpty())continue;
            $actualField=$kind==='project'?'actual_cost':($kind==='fuel'?'cost':'actual');
            $known=$rows->filter(fn($r)=>isset($r->data[$actualField])&&is_numeric($r->data[$actualField]));
            $target=$rows->sum(fn($r)=>(float)$r->data['budget']);
            $metric=$this->metric($label,$target,$known->isEmpty()?null:$known->sum(fn($r)=>(float)$r->data[$actualField]),'GHS',$rows->count(),$known->count(),'Budget-weighted lower-is-better score per source. An unreported cost earns zero credit; it is never treated as an underspend.');
            $metric['achievement']=round($rows->sum(fn($r)=>isset($r->data[$actualField])&&is_numeric($r->data[$actualField])?min(100,max(0,PerformanceMath::achievement((float)$r->data['budget'],(float)$r->data[$actualField],'lower')))*$r->data['budget']:0)/$target,2);
            $metrics[]=$metric;
        }
        return $metrics;
    }

    private function metric(string $name,?float $target,?float $actual,string $unit,int $expected,int $reported,string $note): array
    {
        // Score missing results as a gap against known targets; keep the actual amount unknown.
        $achievement=$target!==null&&$target>0?round(100*($actual??0)/$target,2):null;
        return compact('name','target','actual','unit','expected','reported','note','achievement')+
            ['state'=>$target===null?'Target setup needed · 0 credit':($reported===0?'Missing result · 0% credited':($reported<$expected?'Partial reporting':'Reported'))];
    }
}
