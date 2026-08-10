<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'headline',
        'description',
        'activation_name',
        'activation_type',
        'activation_description',
        'primary_color',
        'secondary_color',
        'accent_color',
        'platform_status',
        'logo_path',
        'logo_dark_path',
    ];

    public function activations(): HasMany
    {
        return $this->hasMany(BrandActivation::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(BrandStaffAssignment::class);
    }

    public function consumerEntries(): HasMany
    {
        return $this->hasMany(BrandConsumerEntry::class);
    }

    public function fieldActivities(): HasMany
    {
        return $this->hasMany(BrandFieldActivity::class);
    }

    public function logoUrl(string $theme = 'light'): ?string
    {
        $path = $theme === 'dark' && $this->logo_dark_path
            ? $this->logo_dark_path
            : $this->logo_path;

        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (Str::startsWith($path, ['images/', 'build/', 'css/', 'js/'])) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}
