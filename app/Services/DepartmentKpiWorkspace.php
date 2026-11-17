<?php
namespace App\Services;

use App\Models\{User,PerformanceKpi,PerformanceObjective,PerformanceObservation,PerformanceTargetPlan};

class DepartmentKpiWorkspace
{
    public function build(User $user, string $department, string $from, string $to): array
    {
        $canAuditDepartment = in_array($department, ['hr_admin', 'finance'], true) && $user->canAuditHrAndFinance();
        abort_unless(PerformanceAccess::executive($user) || PerformanceAccess::department($user) === $department || $canAuditDepartment, 403);
        $manages=PerformanceAccess::manages($user,$department);
        $canReadAll = $manages || $canAuditDepartment;
        $kpis=PerformanceKpi::with('objective')->where('department',$department)->when(!$canReadAll,fn($q)=>$q->where(fn($q)=>$q->where('owner_id',$user->id)->orWhere('reviewer_id',$user->id)))->get();
        $objectives=PerformanceObjective::where('department',$department)->when(!$canReadAll,fn($q)=>$q->where('owner_id',$user->id))->get();
        $observations=PerformanceObservation::with(['kpi','owner'])->whereIn('kpi_id',$kpis->pluck('id'))->whereDate('period_start','>=',$from)->whereDate('period_end','<=',$to)->when(!$canReadAll,fn($q)=>$q->where(fn($q)=>$q->where('owner_id',$user->id)->orWhereHas('kpi',fn($k)=>$k->where('reviewer_id',$user->id))))->get();
        $targetPlans=PerformanceTargetPlan::with(['kpi','owner','observation'])->where('department',$department)->whereIn('kpi_id',$kpis->pluck('id'))->whereDate('period_start','>=',$from)->whereDate('period_end','<=',$to)->when(!$canReadAll,fn($q)=>$q->where(fn($q)=>$q->where('owner_id',$user->id)->orWhere('reviewer_id',$user->id)))->get();
        $users=User::humanAccounts()->where('status','active')->whereIn('department',User::departmentAliases($department))->orderBy('name')->pluck('name','id')->all();
        $kpiRevisions=\App\Models\PerformanceKpiRevision::with('kpi')->whereIn('kpi_id',$kpis->pluck('id'))->latest()->get();
        $summary=app(PerformanceHubReport::class)->build(collect(),$observations,collect(),$from,$to,$kpis,$targetPlans);
        return compact('user','department','manages','kpis','objectives','observations','targetPlans','users','summary','from','to','kpiRevisions');
    }
}
