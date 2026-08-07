<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserLocation extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_locations';

    protected $fillable = ['user_id', 'latitude', 'longitude', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
