<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'activation_type',
        'status',
        'starts_at',
        'ends_at',
        'target_reach',
        'target_unit',
        'actual_reach',
        'locations',
        'activation_plan',
        'description',
        'banner_path',
        'client_share_token',
        'client_share_expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'target_reach' => 'integer',
            'actual_reach' => 'integer',
            'locations' => 'array',
            'activation_plan' => 'array',
            'client_share_expires_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consumerEntries(): HasMany
    {
        return $this->hasMany(BrandConsumerEntry::class);
    }

    public function fieldActivities(): HasMany
    {
        return $this->hasMany(BrandFieldActivity::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(BrandPublication::class);
    }

    public function clientShareIsActive(): bool
    {
        return ! $this->client_share_expires_at || $this->client_share_expires_at->isFuture();
    }
}
