<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Task extends Model
{
    use HasFactory;

    public const COMPLETED_STATUSES = ['Completed', 'Approved', 'Paid', 'done', 'completed'];

    public const COMPLETION_REVIEW_CUTOFF = '2026-07-01 18:32:48';

    public const AUTO_CLOCK_COMPLETION_REVIEW_CUTOFF = '2026-07-14 00:00:00';

    public const TASK_CYCLE_START = '2026-07-01 00:00:00';

    public const LEGACY_AUTO_COMPLETED_TITLES = [
        'CMIH Portal Maintainance and Feature Upgrade',
        '3D/4D Product Mockups and Creative Design Development',
        'Overall App Supervision & Staff Management',
    ];

    public const STATUS_PROGRESS = [
        'open' => 10,
        'in progress' => 50,
        'in_progress' => 50,
        'awaiting approval' => 95,
        'awaiting_approval' => 95,
        'completed' => 100,
        'approved' => 100,
        'paid' => 100,
        'done' => 100,
        'sent' => 90,
        'awaiting feedback' => 70,
        'awaiting_feedback' => 70,
        'overdue' => 40,
        'rejected' => 30,
        'cancelled' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function ($task) {
            $statusStr = is_string($task->status) ? trim($task->status) : '';
            $statusLower = strtolower($statusStr);
            $completedLower = ['completed', 'approved', 'paid', 'done'];

            $progressDirty = $task->isDirty('progress');
            $statusDirty = $task->isDirty('status');

            if ($statusDirty && !$progressDirty) {
                // Status changed, sync progress
                if (array_key_exists($statusLower, self::STATUS_PROGRESS)) {
                    $task->progress = self::STATUS_PROGRESS[$statusLower];
                }
            } elseif ($progressDirty && !$statusDirty) {
                // Progress changed, sync status
                if ($task->progress < 100) {
                    if (in_array($statusLower, $completedLower, true)) {
                        $task->status = 'In Progress';
                    }
                } else {
                    if (!in_array($statusLower, $completedLower, true)) {
                        $task->status = 'Completed';
                    }
                }
            } else {
                // Both changed, new record, or other fields changed
                if ($statusDirty && array_key_exists($statusLower, self::STATUS_PROGRESS)) {
                    $task->progress = self::STATUS_PROGRESS[$statusLower];
                }

                $newStatusLower = strtolower(is_string($task->status) ? trim($task->status) : '');
                if ($task->progress < 100) {
                    if (in_array($newStatusLower, $completedLower, true)) {
                        $task->status = 'In Progress';
                    }
                } else {
                    if (!in_array($newStatusLower, $completedLower, true)) {
                        $task->status = 'Completed';
                    }
                }
            }
        });
    }

    protected $fillable = [
        'campaign_id',
        'client_name',
        'title',
        'details',
        'assigned_to',
        'supporting_staff_ids',
        'copied_manager_ids',
        'supporting_roles',
        'assigned_by',
        'department',
        'status',
        'progress',
        'completion_review_status',
        'completion_review_requested_at',
        'completion_reviewed_at',
        'completion_reviewed_by',
        'completion_review_task_id',
        'completion_review_note',
        'priority',
        'due_on',
        'notes_feedback',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'due_on'               => 'datetime',
            'progress'             => 'integer',
            'completion_review_requested_at' => 'datetime',
            'completion_reviewed_at' => 'datetime',
            'supporting_staff_ids' => 'array',
            'copied_manager_ids'   => 'array',
            'custom_fields'        => 'array',
        ];
    }

    public static function progressForStatus(?string $status, int $fallback = 0): int
    {
        $key = strtolower(trim((string) $status));

        return self::STATUS_PROGRESS[$key] ?? $fallback;
    }

    public static function cycleStart(): Carbon
    {
        return Carbon::parse(self::TASK_CYCLE_START)->startOfDay();
    }

    public function scopeRealWork($query)
    {
        return $query
            ->where('created_at', '>=', self::TASK_CYCLE_START)
            ->where(function ($workQuery) {
                $workQuery->whereNull('completion_review_status')
                    ->orWhere('completion_review_status', '!=', 'audit_task');
            });
    }

    public function scopeApprovedForPerformance($query)
    {
        return $query->realWork()
            ->where(function ($reviewQuery) {
                $reviewQuery->where('completion_review_status', 'approved')
                    ->orWhere(function ($statusQuery) {
                        $statusQuery->whereIn('status', self::COMPLETED_STATUSES)
                            ->where(function ($reviewStatusQuery) {
                                $reviewStatusQuery->whereNull('completion_review_status')
                                    ->orWhere('completion_review_status', 'approved');
                            });
                    })
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->legacyCompletedForPerformance();
                    });
            });
    }

    public function scopeLegacyCompletedForPerformance($query)
    {
        return $query->where('created_at', '>=', self::TASK_CYCLE_START)
            ->where(function ($legacyQuery) {
                $legacyQuery
                    ->where(function ($completedBeforeReviewQuery) {
                        $completedBeforeReviewQuery
                            ->where('created_at', '<', self::COMPLETION_REVIEW_CUTOFF)
                            ->where(function ($completedStatusQuery) {
                                $completedStatusQuery
                                    ->whereIn('status', self::COMPLETED_STATUSES)
                                    ->orWhere(function ($retroPendingQuery) {
                                        $retroPendingQuery
                                            ->where('completion_review_status', 'pending')
                                            ->where('progress', '>=', 95);
                                    });
                            });
                    })
                    ->orWhere(function ($autoClockQuery) {
                        $autoClockQuery
                            ->where('created_at', '<', self::AUTO_CLOCK_COMPLETION_REVIEW_CUTOFF)
                            ->whereIn('title', self::LEGACY_AUTO_COMPLETED_TITLES)
                            ->where(function ($autoClockCompletedQuery) {
                                $autoClockCompletedQuery
                                    ->whereIn('status', self::COMPLETED_STATUSES)
                                    ->orWhere(function ($pendingAutoClockQuery) {
                                        $pendingAutoClockQuery
                                            ->where('completion_review_status', 'pending')
                                            ->where('progress', '>=', 95);
                                    });
                                });
                    });
            });
    }

    public function scopePendingFinalSignOff($query)
    {
        return $query->realWork()
            ->whereNot(function ($legacyQuery) {
                $legacyQuery->legacyCompletedForPerformance();
            })
            ->whereNotIn('status', ['Cancelled', 'cancelled'])
            ->where(function ($pendingQuery) {
                $pendingQuery->where(function ($activeStatusQuery) {
                    $activeStatusQuery->whereNotIn('status', self::COMPLETED_STATUSES)
                        ->where(function ($reviewStatusQuery) {
                            $reviewStatusQuery->whereNull('completion_review_status')
                                ->orWhere('completion_review_status', '!=', 'approved');
                        });
                })->orWhere(function ($completedAwaitingReviewQuery) {
                    $completedAwaitingReviewQuery->whereIn('status', self::COMPLETED_STATUSES)
                        ->whereNotNull('completion_review_status')
                        ->where('completion_review_status', '!=', 'approved');
                });
            });
    }

    public function isApprovedForPerformance(): bool
    {
        $reviewStatus = $this->completion_review_status;

        if (
            $reviewStatus === 'audit_task'
            || ! $this->created_at
            || $this->created_at->lt(self::cycleStart())
        ) {
            return false;
        }

        if ($reviewStatus === 'approved') {
            return true;
        }

        if ($this->isLegacyCompletedForPerformance()) {
            return true;
        }

        return in_array($this->status, self::COMPLETED_STATUSES, true)
            && $reviewStatus === null;
    }

    public function isLegacyCompletedForPerformance(): bool
    {
        if (
            $this->completion_review_status === 'audit_task'
            || ! $this->created_at
            || $this->created_at->lt(self::cycleStart())
        ) {
            return false;
        }

        if (
            $this->created_at->lt(Carbon::parse(self::COMPLETION_REVIEW_CUTOFF))
            && (
                in_array($this->status, self::COMPLETED_STATUSES, true)
                || ($this->completion_review_status === 'pending' && (int) $this->progress >= 95)
            )
        ) {
            return true;
        }

        if ($this->created_at->gte(Carbon::parse(self::AUTO_CLOCK_COMPLETION_REVIEW_CUTOFF))) {
            return false;
        }

        return in_array($this->title, self::LEGACY_AUTO_COMPLETED_TITLES, true)
            && (
                in_array($this->status, self::COMPLETED_STATUSES, true)
                || ($this->completion_review_status === 'pending' && (int) $this->progress >= 95)
            );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function completionReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_reviewed_by');
    }

    public function completionReviewTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'completion_review_task_id');
    }

    public function thirdPartyVendors(): HasMany
    {
        return $this->hasMany(ThirdPartyVendor::class, 'assigned_project_id');
    }

    public function projectBudgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($user->status !== 'active' || $user->access_role === 'merchandiser') {
            return false;
        }

        if ($user->isCvoOrSuperAdmin() || $user->hasRole('admin')) {
            return true;
        }

        $isDeveloper = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true);
        if ($isDeveloper) {
            return true;
        }

        return $this->isAssociatedWith($user)
            || (int) ($this->assignee?->line_manager_id ?? 0) === (int) $user->id;
    }

    public function isAssociatedWith(User $user): bool
    {
        $supportingIds = collect($this->supporting_staff_ids ?? [])->map(fn ($id) => (int) $id);
        $copiedManagerIds = collect($this->copied_manager_ids ?? [])->map(fn ($id) => (int) $id);

        return (int) $this->assigned_to === (int) $user->id
            || (int) $this->assigned_by === (int) $user->id
            || $supportingIds->contains((int) $user->id)
            || $copiedManagerIds->contains((int) $user->id);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function creativeComments(): HasMany
    {
        return $this->hasMany(CreativeComment::class);
    }

    /**
     * Helper to retrieve supporting staff users.
     */
    public function getSupportingStaffAttribute()
    {
        if (empty($this->supporting_staff_ids)) {
            return collect();
        }
        return User::whereIn('id', $this->supporting_staff_ids)->get();
    }

    /**
     * Helper to retrieve copied managers.
     */
    public function getCopiedManagersAttribute()
    {
        if (empty($this->copied_manager_ids)) {
            return collect();
        }
        return User::whereIn('id', $this->copied_manager_ids)->get();
    }
}
