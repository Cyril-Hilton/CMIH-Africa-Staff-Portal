<?php
namespace App\Services;

use App\Models\{User,PerformanceRegister,CrmPipelineProject,SupplierInvoice,PettyCashClaim,PerformanceEvent,CrmFinancialRegister,CrmClientRecord};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** One read model for the Finance module and executive dashboard. No copied ledgers. */
class FinanceDashboard
{
    /** Published departmental reporting is visible to all active internal staff. */
    public static function canViewDashboard(User $user): bool
    {
        return $user->isActive() && !$user->isServiceAccount()
            && !in_array($user->access_role, [...User::MERCHANDISER_FIELD_ROLES, User::BRAND_PROMOTER_ROLE], true);
    }
    public static function canRead(User $user): bool
    {
        return $user->isActive() && ($user->isCvoOrSuperAdmin() || $user->canAuditHrAndFinance() || (PerformanceAccess::department($user)==='finance' && \App\Services\Creative\CreativeAccess::staff($user)));
    }
    public static function canWrite(User $user): bool
    {
        return $user->isActive() && ($user->isCvoOrSuperAdmin() || (PerformanceAccess::department($user)==='finance' && \App\Services\Creative\CreativeAccess::staff($user)));
    }
    public static function manager(User $user): bool
    {
        return $user->isActive() && self::canWrite($user) && (int)$user->id===(int)config('finance_reporting.manager_user_id');
    }
    public static function canReview(User $user,PerformanceRegister $row): bool
    {
        return self::manager($user) && (int)$row->submitted_by!==$user->id;
    }
    public static function published(PerformanceRegister $row): bool
    {
        if(!$row->approved_at || $row->status==='cancelled' || !in_array($row->data['_finance_currency']??'GHS',['GHS','GHC','GH₵','GH¢'],true))return false;
        if(($row->data['_finance_workflow']??null)==='manager_review')return (int)$row->approved_by===(int)config('finance_reporting.manager_user_id');
        return !$row->approved_by?empty($row->data['_finance_currency']):((int)$row->approved_by!==(int)$row->owner_id&&(int)$row->approved_by!==(int)$row->submitted_by);
    }
    public const TABS=['overview'=>'Overview','revenue'=>'Revenue','invoices'=>'Invoices','cashflow'=>'Cash Flow','expenses'=>'Expenses','risks'=>'Commercial Risks','payroll'=>'Payroll','taxes'=>'Taxes & SSNIT'];
    public const GROUPS=['revenue'=>['revenue','revenue_target','profitability'],'invoices'=>['invoice','payable'],'cashflow'=>['receipt','payment','cash'],'expenses'=>['expense'],'risks'=>['commercial_risk'],'payroll'=>['payroll'],'taxes'=>['tax']];
    public const LABELS = ['revenue'=>'Revenue entries','revenue_target'=>'Monthly revenue targets','profitability'=>'Project profitability','invoice'=>'Client invoices','payable'=>'Supplier payables','receipt'=>'Cash receipts','payment'=>'Cash payments','cash'=>'Cash account balances','expense'=>'Expenses','commercial_risk'=>'Commercial risks','payroll'=>'Payroll groups','tax'=>'Taxes & SSNIT'];
    public const COLUMNS = [
        'revenue'=>['period'=>'Period','title'=>'Revenue / source','target'=>'Target','actual'=>'Actual revenue','achievement'=>'Achieved','revenue_status'=>'Status','notes'=>'Comments'],
        'revenue_target'=>['period'=>'Month','title'=>'Target','target'=>'Revenue target','notes'=>'Comments'],
        'profitability'=>['period'=>'Period','project'=>'Project','revenue'=>'Revenue','direct_cost'=>'Direct costs','variable_cost'=>'Variable costs','gross_profit'=>'Gross profit','notes'=>'Comments'],
        'invoice'=>['period'=>'Period','client'=>'Client','project'=>'Project','event_date'=>'Event date','processing_state'=>'Invoice status','account_lead'=>'Account lead','value'=>'Invoice value','notes'=>'Comments'],
        'payable'=>['period'=>'Period','supplier'=>'Supplier','project'=>'Project','value'=>'Amount','due_on'=>'Due date','paid_amount'=>'Paid','notes'=>'Comments'],
        'receipt'=>['paid_on'=>'Received on','title'=>'Receipt','client'=>'Client','counterparty'=>'Received from','project'=>'Project','value'=>'Expected amount','expected_on'=>'Expected date','paid_amount'=>'Received','status'=>'Status','notes'=>'Comments'],
        'payment'=>['paid_on'=>'Paid on','title'=>'Payment','client'=>'Client','counterparty'=>'Payee','project'=>'Project','value'=>'Expected amount','expected_on'=>'Expected date','paid_amount'=>'Paid','status'=>'Status','notes'=>'Comments'],
        'cash'=>['period'=>'Period','account_ref'=>'Account','closing_balance'=>'Closing balance','inflow'=>'Inflow','outflow'=>'Outflow','forecast'=>'Forecast','notes'=>'Comments'],
        'expense'=>['period'=>'Period','title'=>'Expense','project'=>'Project','cost_center'=>'Cost centre','budget'=>'Budget','actual'=>'Actual expense','notes'=>'Comments'],
        'commercial_risk'=>['risk_type'=>'Risk','client'=>'Client','project'=>'Project','account_lead'=>'Account lead','amount_at_risk'=>'Amount at risk','due_on'=>'Due date','mitigation'=>'Action / mitigation','notes'=>'Comments'],
        'payroll'=>['period'=>'Month','staff_group'=>'Payroll group','headcount'=>'Headcount','value'=>'Group total','paid_amount'=>'Paid','due_on'=>'Due date','status'=>'Status','notes'=>'Comments'],
        'tax'=>['period'=>'Month','tax_type'=>'Obligation','value'=>'Amount','paid_amount'=>'Paid','due_on'=>'Due date','filing_state'=>'Filing status','notes'=>'Comments'],
    ];
    public static function notify(PerformanceRegister $row): void
    {
        $event=PerformanceEvent::where('entity_type','register')->where('entity_id',$row->id)->max('id');
        $tab=collect(self::GROUPS)->search(fn($kinds)=>in_array($row->kind,$kinds,true))?:'overview';
        $recipients=User::internalStaff()->where('status','active')->get()->filter(fn($u)=>self::manager($u)||in_array($u->id,[(int)$row->owner_id,(int)$row->submitted_by],true));
        foreach($recipients as $u)PerformanceWorkflow::notify($u->id,'finance-record-'.$row->id.'-'.$event,$row->approved_at?'Finance entry reviewed':'Finance entry awaiting review',$row->title.' · '.$row->reference,route('portal.finance',['finance_tab'=>$tab,'finance_date'=>$row->period_start->toDateString()]));
    }
    public function build(Request $request): array
    {
        abort_unless(self::canViewDashboard($request->user()),403);
        $v=$request->validate(['finance_tab'=>'nullable|in:'.implode(',',array_keys(self::TABS)),'finance_period'=>'nullable|in:day,week,month,year,custom','finance_date'=>'nullable|date_format:Y-m-d','finance_from'=>'nullable|date_format:Y-m-d','finance_to'=>'nullable|date_format:Y-m-d|after_or_equal:finance_from','finance_owner'=>'nullable|integer|exists:users,id','finance_status'=>'nullable|in:all,pending,approved,open,closed','finance_q'=>'nullable|string|max:150','finance_basis'=>'nullable|in:ledger,crm,combined,direct','finance_kind'=>'nullable|in:'.implode(',',array_keys(config('department_reporting.finance'))),'finance_edit'=>'nullable|integer']);
        $tab=$v['finance_tab']??((request('module_view')==='revenue'||request()->boolean('revenue_view'))?'revenue':'overview');$period=$v['finance_period']??(($request->routeIs('dashboard') || $request->attributes->get('dashboard_reporting_period'))?($request->filled('weekly_from')&&$request->filled('weekly_to')?'custom':'week'):'month');$date=Carbon::parse($v['finance_date']??(($request->routeIs('dashboard') || $request->attributes->get('dashboard_reporting_period'))?$request->input('weekly_from',today()->toDateString()):today()->toDateString()));
        [$from,$to]=match($period){'day'=>[$date->copy(),$date->copy()],'week'=>[$date->copy()->startOfWeek(),$date->copy()->endOfWeek()],'year'=>[$date->copy()->startOfYear(),$date->copy()->endOfYear()],'custom'=>[Carbon::parse($v['finance_from']??$request->input('weekly_from',$date->copy()->startOfMonth())),Carbon::parse($v['finance_to']??$request->input('weekly_to',$date->copy()->endOfMonth()))],default=>[$date->copy()->startOfMonth(),$date->copy()->endOfMonth()]};
        abort_if($from->gt($to)||$from->diffInDays($to)>3660,422,'Choose a date range of up to ten years.');$from=$from->startOfDay();$to=$to->endOfDay();
        $owner=$v['finance_owner']??null;$status=$v['finance_status']??'all';$search=trim($v['finance_q']??'');
        $base=PerformanceRegister::where('department','finance')->where(fn($q)=>$q->where(fn($p)=>$p->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from))->orWhere(fn($p)=>$p->whereIn('kind',['receipt','payment'])->where('data->paid_on','>=',$from->toDateString())->where('data->paid_on','<=',$to->toDateString())))
            ->when($owner,fn($q)=>$q->where('owner_id',$owner))->when($search,fn($q)=>$q->where(fn($x)=>$x->where('title','like','%'.$search.'%')->orWhere('reference','like','%'.$search.'%')->orWhere('client','like','%'.$search.'%')->orWhere('project','like','%'.$search.'%')));
        if (!self::canRead($request->user())) $base->whereNotNull('approved_at');
        $base->when($status==='pending',fn($q)=>$q->whereNull('approved_at'))->when($status==='approved',fn($q)=>$q->whereNotNull('approved_at'))
            ->when($status==='open',fn($q)=>$q->whereNotIn('status',['completed','paid','resolved','cancelled']))->when($status==='closed',fn($q)=>$q->whereIn('status',['completed','paid','resolved','cancelled']));
        $all=(clone $base)->get();
        $approved=$all->filter(fn($r)=>self::published($r));
        // Additive amounts are counted only when their entire recorded period is selected.
        $measured=$approved->filter(fn($r)=>$r->period_start->gte($from)&&$r->period_end->lte($to));
        $cash=$approved->whereIn('kind',['receipt','payment'])->filter(fn($r)=>!empty($r->data['paid_on'])&&$r->data['paid_on']>=$from->toDateString()&&$r->data['paid_on']<=$to->toDateString());
        $cashSum=fn($kind)=>FinanceReportingMath::sumKnown($cash->where('kind',$kind),'paid_amount');
        $sum=fn($kind,$field)=>FinanceReportingMath::sumKnown($measured->where('kind',$kind),$field);
        $crm=CrmPipelineProject::with('owner:id,name')->where('source_period_confirmed',true)->whereDate('week_start','>=',$from)->whereDate('week_start','<=',$to)
            ->where(fn($q)=>$q->whereNull('week_end')->orWhereDate('week_end','<=',$to))->when($search,fn($q)=>$q->where(fn($x)=>$x->where('project_name','like','%'.$search.'%')->orWhere('client_name','like','%'.$search.'%')))->get();
        $crm=app(ClientRelationsCentre::class)->latest($crm)->filter(fn($r)=>!$owner||(int)$r->revenue_finance_by===(int)$owner);
        $crm=$crm->filter(fn($r)=>match($status){'approved'=>$r->confirmedRevenue()!==null,'pending'=>$r->confirmedRevenue()===null,'open'=>!in_array($r->stage,['completed','lost','cancelled']), 'closed'=>in_array($r->stage,['completed','lost','cancelled']), default=>true});
        $confirmed=$crm->filter(fn($r)=>$r->confirmedRevenue()!==null);
        $direct=$measured->where('kind','revenue')->filter(fn($r)=>($r->data['revenue_type']??'monthly_total')==='direct');
        $ledger=$measured->where('kind','revenue')->reject(fn($r)=>($r->data['revenue_type']??'monthly_total')==='direct');
        $basis=$v['finance_basis']??($ledger->isNotEmpty()?'ledger':'combined');
        $directTotal=FinanceReportingMath::sumKnown($direct,'actual');$crmTotal=$confirmed->isEmpty()?null:round($confirmed->sum(fn($r)=>(float)$r->confirmedRevenue()),2);
        $actual=match($basis){'ledger'=>FinanceReportingMath::sumKnown($ledger,'actual'),'direct'=>$directTotal,'crm'=>$crmTotal,default=>FinanceReportingMath::combine($crmTotal,$directTotal)};
        $target=$measured->where('kind','revenue_target')->isNotEmpty()?$sum('revenue_target','target'):FinanceReportingMath::sumKnown($ledger,'target');
        $summary=['revenue'=>$actual,'target'=>$target,'achievement'=>$target>0&&$actual!==null?round($actual/$target*100,2):null,'receipts'=>$cashSum('receipt'),'payments'=>$cashSum('payment'),'expenses'=>$sum('expense','actual'),'pending'=>$all->whereNull('approved_at')->count(),'partial'=>$approved->count()-$measured->count(),
            'receivable'=>FinanceReportingMath::balances($approved->where('kind','invoice')),
            'payable'=>FinanceReportingMath::balances($approved->where('kind','payable')),
            'expected_receipts'=>$approved->where('kind','receipt')->filter(fn($r)=>is_numeric($r->data['value']??null)&&(!isset($r->data['paid_amount'])||(float)$r->data['value']>(float)$r->data['paid_amount']))->count(),
            'pending_payments'=>$approved->where('kind','payment')->filter(fn($r)=>is_numeric($r->data['value']??null)&&(!isset($r->data['paid_amount'])||(float)$r->data['value']>(float)$r->data['paid_amount']))->count(),
            'risks'=>$approved->where('kind','commercial_risk')->whereNotIn('status',['resolved','completed','paid'])->count()];
        $summary['incomplete_amounts']=$approved->filter(function($r){$fields=match($r->kind){'revenue','expense'=>['actual'],'revenue_target'=>['target'],'invoice','payable'=>['value','paid_amount'],'receipt','payment'=>['paid_amount','paid_on'],default=>[]};return collect($fields)->contains(fn($key)=>!isset($r->data[$key])||$r->data[$key]==='');})->count();
        $summary['pending_finance']=$summary['pending'];$summary['pending_crm']=$crm->filter(fn($r)=>$r->actual_amount!==null&&$r->confirmedRevenue()===null)->count();$summary['pending']+=$summary['pending_crm'];
        $monthlyTargets=$approved->where('kind','revenue_target')->keyBy(fn($row)=>$row->period_start->format('Y-m'));
        $months=[];$cursor=$from->copy()->startOfMonth();
        while($cursor->lte($to)){
            $key=$cursor->format('Y-m');$inMonth=fn($r)=>$r->period_start->format('Y-m')===$key;
            $monthCrm=$confirmed->filter(fn($r)=>$r->week_start->format('Y-m')===$key);
            $crmAmount=$monthCrm->isEmpty()?null:round($monthCrm->sum(fn($r)=>(float)$r->confirmedRevenue()),2);
            $directAmount=FinanceReportingMath::sumKnown($direct->filter($inMonth),'actual');
            $months[]=['label'=>$cursor->format('M Y'),'revenue'=>match($basis){'ledger'=>FinanceReportingMath::sumKnown($ledger->filter($inMonth),'actual'),'direct'=>$directAmount,'crm'=>$crmAmount,default=>FinanceReportingMath::combine($crmAmount,$directAmount)},'receipts'=>FinanceReportingMath::sumKnown($cash->where('kind','receipt')->filter(fn($r)=>substr($r->data['paid_on'],0,7)===$key),'paid_amount'),'expenses'=>FinanceReportingMath::sumKnown($measured->where('kind','expense')->filter($inMonth),'actual')];
            $cursor->addMonth();
        }
        $records=(clone $base)->when($tab!=='overview',fn($q)=>$q->whereIn('kind',self::GROUPS[$tab]))
            ->when($status==='pending',fn($q)=>$q->whereNull('approved_at'))->when($status==='approved',fn($q)=>$q->whereNotNull('approved_at'))
            ->when($status==='open',fn($q)=>$q->whereNotIn('status',['completed','paid','resolved','cancelled']))->when($status==='closed',fn($q)=>$q->whereIn('status',['completed','paid','resolved','cancelled']))->latest('period_start')->latest('id')->paginate(15,['*'],'finance_page')->withQueryString();
        $tables=[];
        foreach(self::GROUPS[$tab]??[] as $tableKind){
            $tables[$tableKind]=(clone $base)->where('kind',$tableKind)->latest('period_start')->latest('id')->paginate(10,['*'],'finance_'.$tableKind.'_page',max(1,(int)filter_var($request->input('finance_'.$tableKind.'_page',1),FILTER_VALIDATE_INT)))->withQueryString();
        }
        $displayRows=$records->getCollection()->concat(collect($tables)->flatMap(fn($page)=>$page->getCollection()));
        $staff=User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases('finance'))->orderBy('name')->get();
        $names=User::whereIn('id',$displayRows->flatMap(fn($r)=>[$r->owner_id,$r->submitted_by,$r->approved_by]))->pluck('name','id');
        $kind=$v['finance_kind']??(self::GROUPS[$tab][0]??'revenue');if($tab!=='overview'&&!in_array($kind,self::GROUPS[$tab],true))$kind=self::GROUPS[$tab][0];$edit=null;if(!empty($v['finance_edit'])){$edit=PerformanceRegister::where('department','finance')->findOrFail($v['finance_edit']);abort_unless(self::canWrite($request->user())&&((int)$edit->submitted_by===$request->user()->id||PerformanceAccess::manages($request->user(),'finance')),403);$kind=$edit->kind;}
        $nativeInvoices=SupplierInvoice::whereBetween('created_at',[$from,$to])->when(!self::canRead($request->user()),fn($q)=>$q->where('submitted_by',$request->user()->id))->when($owner,fn($q)=>$q->where('submitted_by',$owner))->when($search,fn($q)=>$q->where(fn($x)=>$x->where('supplier_name','like','%'.$search.'%')->orWhere('invoice_number','like','%'.$search.'%')))->when($status==='pending',fn($q)=>$q->where('status','like','%Submitted%'))->when($status==='approved',fn($q)=>$q->where('status','like','%Approved%'))->when($status==='closed',fn($q)=>$q->whereIn('status',['Paid','Rejected','Cancelled']))->when($status==='open',fn($q)=>$q->whereNotIn('status',['Paid','Rejected','Cancelled']))->latest()->paginate(10,['*'],'supplier_page')->withQueryString();
        $nativeClaims=PettyCashClaim::whereBetween('created_at',[$from,$to])->when(!self::canRead($request->user()),fn($q)=>$q->where('user_id',$request->user()->id))->when($owner,fn($q)=>$q->where('user_id',$owner))->when($search,fn($q)=>$q->where('description','like','%'.$search.'%'))->when($status==='pending',fn($q)=>$q->where('status','like','%Submitted%'))->when($status==='approved',fn($q)=>$q->where('status','like','%Approved%'))->when($status==='closed',fn($q)=>$q->whereIn('status',['Paid','Rejected','Cancelled']))->when($status==='open',fn($q)=>$q->whereNotIn('status',['Paid','Rejected','Cancelled']))->latest()->paginate(10,['*'],'claim_page')->withQueryString();
        $projects=app(ClientRelationsCentre::class)->latest(CrmPipelineProject::with('owner:id,name')->where('source_period_confirmed',true)->orderBy('project_name')->get());
        if($edit&&!empty($edit->data['_crm_project_id'])&&!$projects->contains('id',(int)$edit->data['_crm_project_id'])){ $retained=CrmPipelineProject::with('owner:id,name')->find($edit->data['_crm_project_id']);if($retained)$projects->push($retained); }
        $handovers=\App\Models\OperationsHandover::with('lead:id,name')->whereNotNull('handover_approved_at')->get();
        $projectNames=$projects->keyBy('id');$handoverNames=$handovers->keyBy('id');
        // Read the original CRM trackers directly; PO/invoice declarations are not revenue or cash receipts.
        $poRows=CrmFinancialRegister::with('owner:id,name')->where(function($q)use($from,$to,$period,$date){$q->whereBetween('as_of',[$from->toDateString(),$to->toDateString()]);if($period==='year')$q->orWhere(fn($y)=>$y->whereNull('as_of')->where('source_year',$date->year));})
            ->when($owner,fn($q)=>$q->whereRaw('1=0'))->when($search,fn($q)=>$q->where(fn($x)=>$x->where('client_name','like','%'.$search.'%')->orWhere('project_name','like','%'.$search.'%')))->orderByDesc('as_of')->latest('id')->get();
        $yearOnlyCount=CrmFinancialRegister::whereNull('as_of')->where('source_year',$date->year)->when($search,fn($q)=>$q->where(fn($x)=>$x->where('client_name','like','%'.$search.'%')->orWhere('project_name','like','%'.$search.'%')))->count();
        $poRows=$poRows->map(fn($r)=>(object)['client_name'=>$r->client_name,'project_name'=>$r->project_name,'owner'=>$r->owner,'po_amount'=>$r->po_amount,'invoiced_amount'=>$r->invoiced_amount,'po_balance'=>$r->po_balance,'po_status'=>$r->po_status,'invoice_status'=>$r->invoice_status,'source_period'=>$r->as_of?'CRM tracker · '.$r->as_of->format('d M Y'):'CRM tracker · '.$r->source_year.' · year-only source']);
        $pipelineAmounts=$crm->filter(fn($r)=>!$owner&&($r->po_amount!==null||$r->invoiced_amount!==null))->map(fn($r)=>(object)['client_name'=>$r->client_name,'project_name'=>$r->project_name,'owner'=>$r->owner,'po_amount'=>$r->po_amount,'invoiced_amount'=>$r->invoiced_amount,'po_balance'=>$r->po_amount!==null&&$r->invoiced_amount!==null?(float)$r->po_amount-(float)$r->invoiced_amount:null,'po_status'=>ucwords(str_replace('_',' ',$r->po_state??'')),'invoice_status'=>'Reported project snapshot','source_period'=>'Project report · '.$r->week_start->format('d M Y')]);
        $poRows=$poRows->concat($pipelineAmounts)->values();
        $crInvoices=CrmClientRecord::with('owner:id,name')->where('kind','invoice')->where(function($q)use($from,$to){$q->where(fn($d)=>$d->where('data->invoice_date','>=',$from->toDateString())->where('data->invoice_date','<=',$to->toDateString()))->orWhere(fn($d)=>$d->whereNull('data->invoice_date')->whereBetween('created_at',[$from,$to]));})
            ->when($owner,fn($q)=>$q->whereRaw('1=0'))->when($search,fn($q)=>$q->where(fn($x)=>$x->where('client_name','like','%'.$search.'%')->orWhere('reference','like','%'.$search.'%')))->latest('id')->paginate(10,['*'],'crm_invoice_page')->withQueryString();
        $history=PerformanceEvent::with('actor:id,name')->where('entity_type','register')->whereIn('entity_id',$displayRows->pluck('id'))->latest('id')->limit(300)->get()->groupBy('entity_id');
        $sourcePerformance=app(DepartmentSourcePerformance::class)->build('finance',$from->toDateString(),$to->toDateString(),$owner? (int)$owner:null);
        return ['finance'=>compact('tab','period','date','from','to','owner','status','search','basis','summary','months','monthlyTargets','records','tables','staff','names','kind','edit','crm','nativeInvoices','nativeClaims','history','sourcePerformance','projects','handovers','projectNames','handoverNames','poRows','crInvoices','yearOnlyCount')];
    }
}
