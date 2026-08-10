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
        'actual_reach',
        'locations',
        'description',
        'client_share_token',
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
}
