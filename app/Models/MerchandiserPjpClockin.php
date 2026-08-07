<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserPjpClockin extends Model
{
    use HasFactory;

    protected $fillable = [
        'pjp_id',
        'user_id',
        'clocked_in_at',
        'latitude',
        'longitude',
        'distance_from_pjp',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'clocked_in_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'distance_from_pjp' => 'decimal:2',
        ];
    }

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(MerchandiserPjp::class, 'pjp_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
