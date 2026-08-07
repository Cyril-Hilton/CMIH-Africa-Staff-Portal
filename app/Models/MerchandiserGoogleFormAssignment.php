<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class MerchandiserGoogleFormAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'google_form_url',
        'assigned_user_id',
        'outlet_id',
        'kd_id',
        'brand_id',
        'campaign_id',
        'category',
        'channel_type',
        'google_enabled',
        'native_enabled',
        'native_template_key',
        'starts_on',
        'ends_on',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'google_enabled' => 'boolean',
            'native_enabled' => 'boolean',
        ];
    }

    public function scopeActiveForDate(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(function (Builder $dateQuery) use ($date) {
                $dateQuery->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date->toDateString());
            })
            ->where(function (Builder $dateQuery) use ($date) {
                $dateQuery->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date->toDateString());
            });
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function keyDistributor(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MerchandiserGoogleFormSubmission::class, 'form_assignment_id');
    }

    public function nativeSubmissions(): HasMany
    {
        return $this->hasMany(MerchandiserNativeFormSubmission::class, 'form_assignment_id');
    }
}
