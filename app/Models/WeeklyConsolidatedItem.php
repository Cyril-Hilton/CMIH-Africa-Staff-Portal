<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyConsolidatedItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'department',
        'week_start',
        'week_end',
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
            'week_start' => 'date',
            'week_end' => 'date',
            'supporting_staff_ids' => 'array',
            'supporting_roles' => 'array',
            'custom_fields' => 'array',
            'progress_percent' => 'integer',
        ];
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

        return 'WCT-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
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
