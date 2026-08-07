<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    public const PUNCTUAL_STATUSES = [
        'on time',
        'on-time',
        'on_time',
        'ontime',
        'early',
    ];

    protected $fillable = [
        'user_id',
        'clock_in_at',
        'clock_out_at',
        'daily_objective',
        'status',
        'overtime_minutes',
        'latitude',
        'longitude',
        'remote_notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'overtime_minutes' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePunctual($query)
    {
        return $query->where(function ($punctualQuery) {
            $punctualQuery
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), self::PUNCTUAL_STATUSES)
                ->orWhereTime('clock_in_at', '<=', '09:00:00');
        });
    }
}
