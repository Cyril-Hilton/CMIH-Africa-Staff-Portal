<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WeeklyConsolidatedColumn extends Model
{
    protected $fillable = [
        'user_id',
        'department',
        'column_key',
        'label',
        'type',
        'order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function makeKey(string $label): string
    {
        $key = Str::slug($label, '_');

        return $key !== '' ? $key : 'column_' . Str::random(8);
    }
}
