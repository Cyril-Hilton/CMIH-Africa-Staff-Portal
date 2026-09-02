<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'leave_type',
        'line_manager_id',
        'covering_staff_id',
        'delegate_line_manager_id',
        'status',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function coveringStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'covering_staff_id');
    }

    public function delegateLineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_line_manager_id');
    }

    public function workingDays(): int
    {
        return self::workingDaysBetween($this->start_date, $this->end_date);
    }

    public static function workingDaysBetween(Carbon|string $startDate, Carbon|string $endDate): int
    {
        $start = ($startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate))->startOfDay();
        $end = ($endDate instanceof Carbon ? $endDate->copy() : Carbon::parse($endDate))->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        $workingDays = 0;
        for ($date = $start; $date->lte($end); $date->addDay()) {
            if (! $date->isWeekend()) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Check if this approved leave request is currently active today.
     */
    public function isActiveToday(): bool
    {
        if ($this->status !== 'approved' || ! $this->start_date || ! $this->end_date) {
            return false;
        }

        $today = today();

        return $this->start_date->lte($today) && $this->end_date->gte($today);
    }
}
