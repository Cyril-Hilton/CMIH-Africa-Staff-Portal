<?php

namespace App\Services;

use App\Models\PerformanceRegister;
use Carbon\Carbon;

class ExecutiveSourceMilestones
{
    public function register(PerformanceRegister $record): array
    {
        $dept = \App\Models\User::normalizeDepartmentKey($record->department);
        $all = config('executive_source_milestones.registers.'.$dept, []);
        $definitions = array_replace($all['*'] ?? [], $all[$record->kind] ?? []);
        $schema = config('department_reporting.'.$dept.'.'.$record->kind, []);
        $out = [];
        foreach ($definitions as $due => $rule) {
            if (isset($schema[$due]) && ! empty($record->data[$due])) {
                $actual = $rule['actual'] ?? null;
                if ($actual && ! isset($schema[$actual])) {
                    $actual = null;
                }
                $out[$due] = $this->result($record->data, $due, $actual, $rule);
            }
        }

        return $out;
    }

    public function hr(string $kind, array $data): array
    {
        $out = [];
        foreach (config('executive_source_milestones.hr_live.'.$kind, []) as $due => $rule) {
            if (! empty($data[$due])) {
                $out[$due] = $this->result($data, $due, $rule['actual'] ?? null, $rule);
            }
        }

        return $out;
    }

    private function result(array $data, string $due, ?string $actual, array $rule): array
    {
        $at = $actual ? ($data[$actual] ?? null) : null;
        if (($rule['mode'] ?? null) === 'settlement') {
            // A part-payment does not close the full commitment.
            $settled = isset($data['value']) && (float) ($data['paid_amount'] ?? 0) + (float) ($data['credited_amount'] ?? 0) >= (float) $data['value'];
            if (! $settled) {
                $at = null;
            } else {
                $dates = [];
                $complete = true;
                foreach (['paid_amount' => 'paid_on', 'credited_amount' => 'credited_on'] as $amount => $date) {
                    if ((float) ($data[$amount] ?? 0) > 0) {
                        if (empty($data[$date])) {
                            $complete = false;
                        } else {
                            $dates[] = $data[$date];
                        }
                    }
                }$at = $complete && $dates ? max($dates) : null;
            }
        }
        // Last service before the next service deadline is not evidence that the next service occurred.
        if (($rule['mode'] ?? null) === 'maintenance' && $at && Carbon::parse($at)->lt(Carbon::parse($data[$due]))) {
            $at = null;
        }
        if ($at && Carbon::parse($at)->startOfDay()->gt(today())) {
            $at = null;
        }

        return ['due' => $data[$due], 'actual' => $at, 'actual_field' => $actual, 'state' => $at ? 'completed' : 'in_progress'];
    }
}
