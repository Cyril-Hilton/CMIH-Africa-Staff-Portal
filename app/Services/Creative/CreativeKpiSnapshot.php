<?php
namespace App\Services\Creative;
use App\Models\{PerformanceKpi,PerformanceObservation,PerformanceTargetPlan};
use App\Services\PerformanceHubReport;
use Illuminate\Support\Carbon;
class CreativeKpiSnapshot {
 public function forPeriod(Carbon $from,Carbon $to):array {
  $definitions=PerformanceKpi::where('department','creatives')->whereHas('objective',fn($q)=>$q->whereDate('starts_on','<=',$to)->whereDate('ends_on','>=',$from))->get();
  $observations=PerformanceObservation::with('kpi')->whereIn('kpi_id',$definitions->pluck('id'))->whereNotNull('approved_at')->whereDate('period_start','>=',$from)->whereDate('period_end','<=',$to)->get();
  $plans=PerformanceTargetPlan::whereIn('kpi_id',$definitions->pluck('id'))->whereDate('period_start','>=',$from)->whereDate('period_end','<=',$to)->get();
  $report=app(PerformanceHubReport::class)->build(collect(),$observations,collect(),$from->toDateString(),$to->toDateString(),$definitions,$plans);
  if($definitions->isEmpty()){
   $source=app(\App\Services\DepartmentSourcePerformance::class)->build('creatives',$from->toDateString(),$to->toDateString(),null,false);
   return ['from'=>$from->toDateString(),'to'=>$to->toDateString(),'items'=>array_map(fn($m)=>['name'=>$m['name'],'achievement'=>$m['has_measured_result']?$m['credited']:null,'coverage'=>$m['reported'],'rag'=>!$m['has_measured_result']?'setup':($m['credited']>=100?'green':'amber'),'gap'=>!$m['has_measured_result']],$source['metrics'])];
  }
  // Shared projection exposes achievement only, never private target/actual amounts or source notes.
  return ['from'=>$from->toDateString(),'to'=>$to->toDateString(),'items'=>$report['kpis']->map(fn($row)=>['name'=>$row['kpi']->name,'achievement'=>$row['achievement'],'coverage'=>$row['coverage'],'rag'=>$row['rag']])->all()];
 }
}
