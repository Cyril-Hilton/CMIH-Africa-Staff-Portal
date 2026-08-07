<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserAttendance extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_attendances';

    protected $fillable = [
        'user_id', 'outlet_id', 'clock_in_type', 'clock_in_time',
        'client_recorded_at', 'sync_token', 'sync_source', 'synced_at',
        'latitude', 'longitude', 'distance_from_outlet', 'status'
    ];

    protected $casts = [
        'clock_in_time' => 'datetime',
        'client_recorded_at' => 'datetime',
        'synced_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'distance_from_outlet' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
