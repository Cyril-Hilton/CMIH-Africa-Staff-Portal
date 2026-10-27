<?php

namespace App\Services;

use App\Models\PerformanceRegister;
use Illuminate\Support\Facades\Schema;

class WeeklyDepartmentTables
{
    /** Shared reporting is built from an explicit field allow-list, never raw register payloads. */
    public function build(string $department, ?string $from = null, ?string $to = null): array
    {
        if (! Schema::hasTable('performance_registers')) {
            return [];
        }
        $definitions = config('weekly_department_tables');
        if ($department !== 'all') {
            $definitions = array_intersect_key($definitions, [$department => true]);
        }
        foreach (config('shared_department_registers') as $key => $sections) {
            if ($department === 'all' || $department === $key) $definitions[$key] = ($definitions[$key] ?? ['title'=>config('performance.departments.'.$key),'columns'=>[]]) + ['sections'=>$sections];
        }
        $result = [];
        foreach ($definitions as $key => $definition) {
            if (! $definition['columns'] && empty($definition['sections'])) {
                continue;
            }
            $flatPage = PerformanceRegister::query()->where('department', $key)->whereNotNull('approved_at')->where('status', '!=', 'cancelled')->when($from, fn ($q) => $q->whereDate('period_end', '>=', $from))->when($to, fn ($q) => $q->whereDate('period_start', '<=', $to))->orderByDesc('period_end')->orderByDesc('id')->paginate(20,['*'],'register_'.$key.'_page')->withQueryString()->appends(['report_view'=>'register']+(request()->routeIs('portal.brands','portal.operations','portal.finance')?['module_view'=>'weekly']:[]))->fragment('staff-weekly-performance');
            $rows = $flatPage->getCollection()->map(function ($record) use ($definition) {
                $row = ['period' => $record->period_start->format('d M').'–'.$record->period_end->format('d M Y')];
                foreach ($definition['columns'] as $field => $label) {
                    $row[$field] = in_array($field, ['kind', 'title', 'status']) ? $record->{$field} : ($record->data[$field] ?? null);
                    if (in_array($field, ['kind', 'status']) && is_string($row[$field])) {
                        $row[$field] = ucwords(str_replace('_', ' ', $row[$field]));
                    }
                }

                return $row;
            })->all();
            $flatPage->setCollection(collect($rows));
            $result[$key] = $definition + ['rows' => $rows, 'pagination'=>$flatPage];
            if (! empty($definition['sections'])) {
                $recordQuery = PerformanceRegister::where('department',$key)->whereNotNull('approved_at')->where('status','!=','cancelled')
                    ->when($from,fn($q)=>$q->whereDate('period_end','>=',$from))->when($to,fn($q)=>$q->whereDate('period_start','<=',$to))
                    ->orderByDesc('period_end')->orderByDesc('id');
                $result[$key]['sections'] = [];
                foreach ($definition['sections'] as $kind=>$fields) {
                    $categoryPage=(clone $recordQuery)->where('kind',$kind)->paginate(10,['*'],'register_'.$key.'_'.$kind.'_page')->withQueryString()->appends(['report_view'=>'register']+(request()->routeIs('portal.brands','portal.operations','portal.finance')?['module_view'=>'weekly']:[]))->fragment('staff-weekly-performance');
                    $records=$categoryPage->getCollection();
                    $names=\App\Models\User::whereIn('id',$records->pluck('owner_id'))->pluck('name','id');
                    $schema = config('department_reporting.'.$key.'.'.$kind,[]);
                    $columns = array_combine($fields,array_map(fn($field)=>ucwords(str_replace('_',' ',$field)),$fields));
                    $sectionRows = $records->where('kind',$kind)->map(function($record)use($fields,$schema,$names){
                        $row = ['reference'=>$record->reference,'title'=>$record->title,'client'=>$record->client,'project'=>$record->project,'owner'=>$names[$record->owner_id]??'Unavailable Owner','status'=>ucwords(str_replace('_',' ',$record->status)),'period'=>$record->period_start->format('d M').'–'.$record->period_end->format('d M Y')];
                        foreach($fields as $field) { if(($schema[$field]??null)==='money') continue; $row[$field]=$record->data[$field]??null; }
                        return $row;
                    })->values()->all();
                    $categoryPage->setCollection(collect($sectionRows));
                    $result[$key]['sections'][$kind]=['title'=>$key==='operations_projects'?config('operations_tracker.labels.'.$kind,ucwords(str_replace('_',' ',$kind))):($key==='brands_marketing' && $kind==='campaign'?'Brand Activities':ucwords(str_replace('_',' ',$kind))),'columns'=>$columns,'rows'=>$sectionRows,'total'=>$categoryPage->total(),'pagination'=>$categoryPage];
                }
            }
        }

        return $result;
    }
}
