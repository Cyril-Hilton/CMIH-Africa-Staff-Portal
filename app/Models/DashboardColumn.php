<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardColumn extends Model
{
    protected $fillable = ['department', 'column_key', 'label', 'type', 'order'];

    public static function forDepartment(string $dept): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('dashboard_columns')) {
            return collect();
        }

        return static::where('department', $dept)->orderBy('order')->get();
    }
}
