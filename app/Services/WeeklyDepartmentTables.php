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
        $result = [];
        foreach ($definitions as $key => $definition) {
            if (! $definition['columns']) {
                continue;
            }
            $rows = PerformanceRegister::query()->where('department', $key)->whereNotNull('approved_at')->where('status', '!=', 'cancelled')->when($from, fn ($q) => $q->whereDate('period_end', '>=', $from))->when($to, fn ($q) => $q->whereDate('period_start', '<=', $to))->orderByDesc('period_end')->limit(100)->get()->map(function ($record) use ($definition) {
                $row = ['period' => $record->period_start->format('d M').'–'.$record->period_end->format('d M Y')];
                foreach ($definition['columns'] as $field => $label) {
                    $row[$field] = in_array($field, ['kind', 'title', 'status']) ? $record->{$field} : ($record->data[$field] ?? null);
                    if (in_array($field, ['kind', 'status']) && is_string($row[$field])) {
                        $row[$field] = ucwords(str_replace('_', ' ', $row[$field]));
                    }
                }

                return $row;
            })->all();
            $result[$key] = $definition + ['rows' => $rows];
        }

        return $result;
    }
}
