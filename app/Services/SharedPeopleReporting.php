<?php

namespace App\Services;

use App\Models\AdminWeeklyReport;
use App\Models\HrPeriodicReport;

class SharedPeopleReporting
{
    /** Explicit public numeric projection: no employee names, cases, notes or costs. */
    public function build(string $from, string $to, bool $compare = true): array
    {
        $result = ['hr' => [], 'administrator' => [], 'publication'=>[]];
        foreach (['hr'=>HrPeriodicReport::class,'administrator'=>AdminWeeklyReport::class] as $type=>$model) {
            $scope=$model::whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from);
            if($type==='hr')$scope->where('frequency','weekly');
            $result['publication'][$type]=['approved'=>(clone $scope)->whereNotNull('approved_at')->count(),'awaiting'=>(clone $scope)->whereNull('approved_at')->count()];
        }
        $reports = HrPeriodicReport::with('entries')->whereNotNull('approved_at')->where('frequency', 'weekly')
            ->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->orderByDesc('period_start')->get();
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach ($report->entries->where('section', 'metrics') as $entry) {
                $d = $entry->data;
                if (! in_array($d['metric'] ?? '', ['Headcount', 'New starters', 'Leavers', 'Open vacancies', 'Training completion rate'], true)) {
                    continue;
                }
                $actual = $d['actual'] ?? null;
                if ($d['metric'] === 'Training completion rate') {
                    $actual = PerformanceMath::ratio($d['numerator'] ?? null, $d['denominator'] ?? null, true);
                }
                $result['hr'][] = ['period' => $period, 'group'=>'Key Metrics', 'metric' => ucwords($d['metric']), 'actual' => $actual, 'unit' => $d['metric'] === 'Training completion rate' ? '%' : ''];
            }
        }
        // Share only approved, numeric workforce results. Never project staff names,
        // recruitment notes, compensation, employee cases or personnel documents.
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach (['recruitment'=>'Recruitment','employee_admin'=>'Workforce Changes','attendance_leave'=>'Attendance & Leave'] as $section=>$group) {
                $allowed = config('hr_reporting.'.$section.'.fields.metric.options', []);
                foreach ($report->entries->where('section',$section) as $entry) {
                    $data=$entry->data;
                    if (!in_array($data['metric']??'', $allowed, true)) continue;
                    $number=$data['number']??null;
                    $result['hr'][]=['period'=>$period,'group'=>$group,'metric'=>$data['metric'],'actual'=>is_numeric($number)?0+$number:null,'unit'=>''];
                }
            }
            $training=$report->entries->where('section','training');
            foreach (['trainings_delivered'=>'Training Sessions Delivered','employees_attended'=>'Training Attendances','required_completions'=>'Required Training Completions','completed'=>'Training Completions'] as $field=>$label) {
                if($training->isEmpty()) continue;
                $values=$training->map(fn($entry)=>$entry->data[$field]??null);
                $known=$values->filter(fn($value)=>is_numeric($value));
                $result['hr'][]=['period'=>$period,'group'=>'Training & Development','metric'=>$label,'actual'=>$known->count()===$values->count()?$known->sum():null,'unit'=>''];
            }
        }
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach (['performance'=>'Performance Management', 'priorities'=>'Next Period Priorities'] as $section=>$group) {
                $entries=$report->entries->where('section',$section);
                if ($entries->isEmpty()) continue;
                // Counts and controlled labels only: free-text priorities can contain employee cases.
                foreach (config('hr_reporting.'.$section.'.fields.metric.options',[]) as $label) {
                    $count=$entries->filter(fn($entry)=>($entry->data['metric']??null)===$label)->count();
                    if ($count) $result['hr'][]=['period'=>$period,'group'=>$group,'metric'=>$label,'actual'=>$count,'unit'=>'items'];
                }
                if ($section==='priorities') foreach (['Planned','In progress','Blocked','Completed'] as $status) {
                    $count=$entries->filter(fn($entry)=>($entry->data['status']??null)===$status)->count();
                    $result['hr'][]=['period'=>$period,'group'=>$group,'metric'=>$status.' Priorities','actual'=>$count,'unit'=>'items'];
                }
            }
        }
        $reports = AdminWeeklyReport::with('lines')->whereNotNull('approved_at')
            ->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from)->orderByDesc('period_start')->get();
        $allowed = ['fleet' => ['total_vehicles', 'vehicles_available', 'fuel_litres', 'distance_km'], 'drivers' => ['total_drivers', 'attendance_rate', 'assignments_completed'], 'assets' => ['total_assets', 'assigned_assets', 'missing_assets', 'damaged_assets', 'transferred']];
        foreach ($reports as $report) {
            $period = $report->period_start->format('d M').'–'.$report->period_end->format('d M Y');
            foreach ($report->lines as $line) {
                if (! in_array($line->code, $allowed[$line->section] ?? [], true)) {
                    continue;
                }
                $result['administrator'][] = ['period' => $period, 'metric' => config('admin_weekly.'.$line->section.'.rows.'.$line->code.'.label'), 'group'=>config('admin_weekly.'.$line->section.'.title'), 'actual' => $line->value_state === 'reported' ? $line->numeric_value : null, 'unit' => $line->code==='attendance_rate'?'%':''];
            }
            foreach (['office'=>'Office Administration', 'priorities'=>'Next Period Priorities'] as $section=>$group) {
                $lines=$report->lines->where('section',$section);
                if ($lines->isEmpty()) continue;
                $result['administrator'][]=['period'=>$period,'group'=>$group,'metric'=>'Areas Reported','actual'=>$lines->where('value_state','reported')->count(),'unit'=>''];
                $result['administrator'][]=['period'=>$period,'group'=>$group,'metric'=>'Areas Awaiting Update','actual'=>$lines->where('value_state','not_reported')->count(),'unit'=>''];
                $result['administrator'][]=['period'=>$period,'group'=>$group,'metric'=>'Actions Assigned','actual'=>$lines->filter(fn($line)=>$line->owner_id!==null)->count(),'unit'=>''];
                $result['administrator'][]=['period'=>$period,'group'=>$group,'metric'=>'Actions With Deadlines','actual'=>$lines->filter(fn($line)=>$line->due_date!==null)->count(),'unit'=>''];
            }
            foreach ($report->lines->where('section','office') as $line) {
                $spec=config('admin_weekly.office.rows.'.$line->code);
                if (!$spec) continue;
                $status=strtolower(trim($line->status??''));
                $publicStatus=match($status) {
                    'completed','done','stocked','operational','resolved'=>'Ready',
                    'in progress','in_progress','pending','planned'=>'In Progress',
                    'blocked','faulty','some faults recorded','cctv faulty'=>'Attention Needed',
                    default=>$line->value_state==='not_applicable'?'Not Applicable':'Update Recorded',
                };
                $result['administrator'][]=['period'=>$period,'group'=>'Office Readiness','metric'=>ucwords($spec['label']),'actual'=>$line->value_state==='not_reported'?null:$publicStatus,'unit'=>''];
            }
            $fleet = $report->lines->where('section', 'fleet')->keyBy('code');
            $litres = $fleet->get('fuel_litres')?->value_state==='reported'?$fleet->get('fuel_litres')->numeric_value:null;
            $distance = $fleet->get('distance_km')?->value_state==='reported'?$fleet->get('distance_km')->numeric_value:null;
            $result['administrator'][] = ['period' => $period, 'metric' => 'Fuel Efficiency', 'actual' => $litres !== null && $distance > 0 ? round(100 * $litres / $distance, 2) : null, 'unit' => 'L/100km'];
        }

        if ($compare) {
            $start=\Carbon\CarbonImmutable::parse($from);
            $length=$start->diffInDays(\Carbon\CarbonImmutable::parse($to))+1;
            $previous=$this->build($start->subDays($length)->toDateString(),$start->subDay()->toDateString(),false);
            foreach (['hr','administrator'] as $type) {
                foreach ($result[$type] as &$row) {
                    $matches=collect($previous[$type])->filter(fn($old)=>$old['metric']===$row['metric']&&($old['group']??'')===($row['group']??''));
                    // Only compare an unambiguous matching measure; never add headcount snapshots.
                    $row['previous']=$matches->count()===1?$matches->first()['actual']:null;
                    $row['change']=is_numeric($row['actual'])&&is_numeric($row['previous'])?round($row['actual']-$row['previous'],2):null;
                }
                unset($row);
            }
        }
        return $result;
    }
}
