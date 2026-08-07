<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period',
        'gross_salary',
        'ssnit_employee',
        'ssnit_employer',
        'paye_tax',
        'other_deductions',
        'bonuses',
        'net_salary',
        'bank_name',
        'account_number',
        'momo_number',
        'issued_at',
        'issued_by',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'ssnit_employee' => 'decimal:2',
        'ssnit_employer' => 'decimal:2',
        'paye_tax' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        try {
            return Carbon::createFromFormat('!Y-m', $this->period)->format('F Y');
        } catch (\Exception $e) {
            return $this->period;
        }
    }
}
