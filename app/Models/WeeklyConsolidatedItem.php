<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyConsolidatedItem extends Model
{
    use HasFactory;

    protected $attributes = ['workflow_state' => 'legacy', 'revision' => 1, 'risk_level' => 'low', 'measurement_direction' => 'higher'];

    protected $fillable = [
        'source_task_id', 'performance_kpi_id', 'workflow_state', 'record_type', 'team', 'reviewer_id',
        'accepted_at', 'submitted_at', 'verified_at', 'closed_at', 'quality_score', 'risk_level',
        'root_cause', 'management_decision', 'department_data', 'revision', 'escalation_level', 'exception_since',
        'objective', 'kpi_name', 'measurement_unit', 'measurement_direction',
        'target_value', 'actual_value', 'completed_at', 'evidence_url',
        'issue_impact', 'corrective_action', 'action_owner_id', 'action_due_date',
        'department',
        'week_start',
        'week_end',
        'brief_received_on',
        'client_name',
        'campaign_name',
        'lead_staff_id',
        'supporting_staff_ids',
        'supporting_roles',
        'deliverables',
        'target_breakdown',
        'achieved_breakdown',
        'gap_breakdown',
        'status',
        'priority',
        'progress_percent',
        'notes',
        'custom_fields',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'performance_weight' => 'integer', 'weight_approved_at' => 'datetime',            'accepted_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'closed_at' => 'datetime',
            'exception_since' => 'datetime', 'department_data' => 'array', 'quality_score' => 'float',
            'completed_at' => 'datetime',
            'action_due_date' => 'date',
            'target_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'week_start' => 'date',
            'week_end' => 'date',
            'brief_received_on' => 'date',
            'supporting_staff_ids' => 'array',
            'supporting_roles' => 'array',
            'custom_fields' => 'array',
            'progress_percent' => 'integer',
        ];
    }

    public function performanceKpi()
    {
        return $this->belongsTo(PerformanceKpi::class, 'performance_kpi_id');
    }

    public function actionOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_owner_id');
    }

    public function dueDate(): ?\Illuminate\Support\Carbon
    {
        return $this->week_end?->copy()->endOfDay() ?? $this->week_start?->copy()->endOfWeek();
    }

    public function isOverdue(): bool
    {
        return ($this->workflow_state && $this->workflow_state !== 'legacy'
            ? ! in_array($this->workflow_state, ['verified', 'closed'], true) : $this->status !== 'Done')
            && $this->dueDate()?->lt(now());
    }

    public function achievementPercent(): ?float
    {
        if ($this->target_value === null || $this->actual_value === null || (float) $this->target_value === 0.0) {
            return null;
        }
        // Lower-is-better metrics use distance to target; zero actual remains meaningful.
        $target = (float) $this->target_value;
        $actual = (float) $this->actual_value;

        return round($this->measurement_direction === 'lower'
            ? max(0, (1 + ($target - $actual) / $target) * 100)
            : $actual / $target * 100, 1);
    }

    public function variance(): ?float
    {
        return $this->target_value !== null && $this->actual_value !== null
            ? round((float) $this->actual_value - (float) $this->target_value, 4) : null;
    }

    public function leadStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getSupportingStaffAttribute()
    {
        if (empty($this->supporting_staff_ids)) {
            return collect();
        }

        return User::whereIn('id', $this->supporting_staff_ids)->orderBy('name')->get();
    }

    public function getSupportingStaffWithRolesAttribute()
    {
        $roles = $this->supporting_roles ?? [];

        return $this->supporting_staff->map(function (User $staff) use ($roles) {
            $staff->weekly_role = $roles[$staff->id] ?? $roles[(string) $staff->id] ?? null;

            return $staff;
        });
    }

    public function customFieldValue(WeeklyConsolidatedColumn $column): ?string
    {
        $fields = $this->custom_fields ?? [];

        return $fields[$column->column_key] ?? null;
    }

    public function targetLines(): array
    {
        return $this->linesFromText($this->target_breakdown);
    }

    public function achievedLines(): array
    {
        return $this->linesFromText($this->achieved_breakdown);
    }

    public function gapLines(): array
    {
        return $this->linesFromText($this->gap_breakdown);
    }

    public function brandsTaskId(): string
    {
        $fields = $this->custom_fields ?? [];
        $custom = trim((string) ($fields['brands_task_id'] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        return 'WCT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function brandsUpdateStatus(): string
    {
        $notes = trim((string) ($this->notes ?? ''));
        if ($notes !== '') {
            return $notes;
        }

        return $this->status ?: 'Pending';
    }

    private function linesFromText(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
