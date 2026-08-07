<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'amount' => 'float',
        'monthly_deduction_amount' => 'float',
    ];

    /**
     * Get the user who requested the salary advance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
