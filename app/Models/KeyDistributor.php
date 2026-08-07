<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeyDistributor extends Model
{
    use HasFactory;

    protected $table = 'key_distributors';

    protected $fillable = ['name', 'region_id', 'address', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'kd_id');
    }

    public function merchandisers(): HasMany
    {
        return $this->hasMany(User::class, 'kd_id');
    }
}
