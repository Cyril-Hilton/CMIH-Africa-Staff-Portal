<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserPcmClockin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kd_id',
        'clocked_in_at',
        'client_recorded_at',
        'sync_token',
        'sync_source',
        'synced_at',
        'latitude',
        'longitude',
        'distance_from_kd',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'clocked_in_at' => 'datetime',
            'client_recorded_at' => 'datetime',
            'synced_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'distance_from_kd' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keyDistributor(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }
}
