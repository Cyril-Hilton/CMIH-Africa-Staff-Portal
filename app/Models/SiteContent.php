<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Cache;

class SiteContent extends Model
{
    use HasFactory;

    protected static array $memoizedValues = [];
    protected static bool $memoizedLoaded = false;
    protected static ?bool $hasTable = null;

    protected $fillable = [
        'key',
        'value',
        'type',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCachedValues());
        static::deleted(fn () => self::forgetCachedValues());
    }

    protected static function allValues(): array
    {
        if (self::$memoizedLoaded) {
            return self::$memoizedValues;
        }

        try {
            if (self::$hasTable === null) {
                self::$hasTable = Schema::hasTable('site_contents');
            }

            if (! self::$hasTable) {
                self::$memoizedLoaded = true;
                return [];
            }

            $values = Cache::remember('site_content_all', 86400, function () {
                return static::query()->pluck('value', 'key')->all();
            });

            self::$memoizedValues = is_array($values) ? $values : [];
        } catch (\Throwable $e) {
            self::$memoizedValues = [];
        }

        self::$memoizedLoaded = true;

        return self::$memoizedValues;
    }

    public static function getValue(string $key, ?string $default = null): string
    {
        $values = self::allValues();
        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? '';
        }

        return $value;
    }

    public static function getImageUrl(string $key, ?string $default = null): string
    {
        $values = self::allValues();
        $value = $values[$key] ?? null;

        if ($value) {
            try {
                // We use the public disk explicitly to bypass slow visibility checks
                // on the default local disk when running on Windows.
                return Storage::disk('public')->url($value);
            } catch (\Throwable $e) {
                // Fallback if Storage::url fails (e.g., missing finfo extension)
                // We return the default or empty string so the site doesn't 500
                return $default ?? '';
            }
        }

        return $default ?? '';
    }

    public static function forgetCachedValues(): void
    {
        Cache::forget('site_content_all');
        self::$memoizedValues = [];
        self::$memoizedLoaded = false;
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
