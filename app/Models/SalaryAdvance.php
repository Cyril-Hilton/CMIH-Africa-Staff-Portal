<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryAdvance extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'repayment_style',
        'monthly_deduction_amount',
        'reason',
        'status',
        'finance_feedback',
        'hr_reviewed_by',
        'hr_reviewed_at',
        'hr_feedback',
        'finance_reviewed_by',
        'finance_reviewed_at',
        'cvo_reviewed_by',
        'cvo_reviewed_at',
        'approved_by',
        'approved_at',
        'approved_monthly_deduction_amount',
        'repayment_start_date',
        'repayment_months',
        'disbursed_by',
        'disbursed_at',
        'disbursed_amount',
        'fully_paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'monthly_deduction_amount' => 'float',
        'approved_monthly_deduction_amount' => 'float',
        'disbursed_amount' => 'float',
        'hr_reviewed_at' => 'datetime',
        'finance_reviewed_at' => 'datetime',
        'cvo_reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'repayment_start_date' => 'date',
        'disbursed_at' => 'datetime',
        'fully_paid_at' => 'datetime',
    ];

    /**
     * Get the user who requested the salary advance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function cvoReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cvo_reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(SalaryAdvanceRepayment::class);
    }

    public function approvedMonthlyDeduction(): ?float
    {
        if ($this->repayment_style !== 'monthly_deduction') {
            return null;
        }

        return $this->approved_monthly_deduction_amount ?: $this->monthly_deduction_amount;
    }

    public function totalRepaid(): float
    {
        if ($this->relationLoaded('repayments')) {
            return (float) $this->repayments->sum('amount');
        }

        return (float) $this->repayments()->sum('amount');
    }

    public function balance(): float
    {
        return max((float) $this->amount - $this->totalRepaid(), 0.0);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance() <= 0.009;
    }
}
