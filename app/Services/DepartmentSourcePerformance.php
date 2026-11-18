<?php

namespace App\Services;

use App\Models\{CreativeRequest, CrmAccountTarget, CrmPipelineProject, PerformanceRegister, User, WeeklyConsolidatedItem};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Reporting obligations and business results are distinct measures. Missing actuals are never recorded as revenue of zero. */
class DepartmentSourcePerformance
{
    public function forRequest(string $department, string $from, string $to): array
    {
        $key = 'department_source_performance.'.hash('sha256', $department.'|'.$from.'|'.$to);
        $request = request();
        if (!$request->attributes->has($key)) $request->attributes->set($key, $this->build($department, $from, $to));
        return $request->attributes->get($key);
    }

    public function build(string $department, string $from, string $to, ?int $owner = null): array
    {
        $department=User::normalizeDepartmentKey($department);
        $records=PerformanceRegister::whereIn('department',User::departmentAliases($department))->whereNotNull('approved_at')
            ->where('status','!=','cancelled')->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
            ->when($owner,fn($q)=>$q->where('owner_id',$owner))->get();
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
            $projects=CrmPipelineProject::where('source_period_confirmed',true)->whereBetween('week_start',[$from,$to])
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
            $metrics[]=$this->metric('Deal conversion',$decided?:null,$decided?$wins:null,'deals',$decided,$decided,'Won projects / decided projects. No decisions means no conversion rate yet.');
        } elseif ($department==='creatives') {
            $briefs=CreativeRequest::whereNull('parent_request_id')->where(function($q) use($from,$to) { $q->whereBetween('received_at',[$from.' 00:00:00',$to.' 23:59:59'])
                ->orWhereBetween('requested_deadline',[$from.' 00:00:00',$to.' 23:59:59'])
                ->orWhereBetween('completed_at',[$from.' 00:00:00',$to.' 23:59:59']);
                \App\Services\Creative\CreativeReportingPeriod::includeHistory($q,$from.' 00:00:00',$to.' 23:59:59');
            })->when($owner,fn($q)=>$q->where('assignee_id',$owner))->get();
            $area('Briefs & planning',$briefs->count()+$count(['brief']),'No brief / planning report recorded.');
            $area('Delivery & review',$briefs->filter(fn($b)=>$b->first_submitted_at||$b->submitted_at||$b->completed_at)->count()+$records->where('kind','brief')->filter(fn($r)=>isset($r->data['final_delivered_on']))->count(),'No submission / delivery result reported.');
            $area('Quality & revisions',$briefs->whereNotNull('quality_score')->count()+$records->where('kind','brief')->filter(fn($r)=>isset($r->data['quality_score'])||isset($r->data['revision_rounds']))->count(),'No quality or revision result recorded.');
            $due=$briefs->whereNotNull('requested_deadline');
            $onTime=$due->filter(fn($b)=>$b->first_submitted_at&&$b->first_submitted_at->lte($b->requested_deadline));
            $metrics[]=$this->metric('On-time first submissions',$due->count()?:null,$due->isNotEmpty()?$onTime->count():null,'briefs',$due->count(),$due->whereNotNull('first_submitted_at')->count(),'On-time first submissions / all briefs due in this period. Current-period scores are provisional.');
        } else {
            $categories=match($department) {
                'operations_projects'=>[['Projects & activations',['project','activation','weekly_update','team_allocation']],['Assets & POSM',['posm_deployment']],['Procurement & execution',['fabrication','procurement','supplier','production','milestone']],['Reports, approvals & risks',['client_approval','project_report','risk_issue','risk']]],
                'brands_marketing'=>[['People & team coverage',['people']],['Merchandising performance',['perfect_store','campaign']],['Activations & visibility',['activation','visibility']],['Weekly workstreams',['workstream']]],
                'finance'=>[['Revenue & profitability',['revenue','profitability']],['Receivables & payables',['invoice','payable']],['Cash & expenses',['cash','expense']]],
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
                'brands_marketing'=>[['Sales target achievement',['activation'],'target_sales','total_sales'],['Reach target achievement',['activation'],'target_reach','total_reach'],['Outlet coverage',['campaign'],'outlets_planned','outlets_visited'],['Perfect Store achievement',['perfect_store','campaign'],'perfect_store_target_percent','perfect_store_actual_percent']],
                'finance'=>[['Revenue target achievement',['revenue'],'target','actual']],
                'hr_admin'=>[['Appraisals completed',['workforce'],'reviews_due','reviews_completed']],
                default=>[],
            };
            foreach($pairs as [$name,$kinds,$targetField,$actualField]) {
                $eligible=$records->whereIn('kind',$kinds)->filter(fn($r)=>$name!=='Perfect Store achievement'||($r->data['breakdown']??'overall')==='overall')
                    ->filter(fn($r)=>isset($r->data[$targetField])&&is_numeric($r->data[$targetField])&&(float)$r->data[$targetField]>0);
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
        $reported=collect($areas)->where('reported',true)->count();
        return ['areas'=>$areas,'metrics'=>$metrics,'expected'=>count($areas),'reported'=>$reported,'missing'=>count($areas)-$reported,
            'coverage'=>count($areas)?round(100*$reported/count($areas),1):null,
            'from'=>$from,'to'=>$to,'provisional'=>Carbon::parse($to)->endOfDay()->gte(now())];
    }

    private function metric(string $name,?float $target,?float $actual,string $unit,int $expected,int $reported,string $note): array
    {
        // Score missing results as a gap against known targets; keep the actual amount unknown.
        $achievement=$target!==null&&$target>0?round(100*($actual??0)/$target,2):null;
        return compact('name','target','actual','unit','expected','reported','note','achievement')+
            ['state'=>$target===null?'Target / baseline not recorded':($reported===0?'Missing result · 0% credited':($reported<$expected?'Partial reporting':'Reported'))];
    }
}
