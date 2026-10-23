<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfectStoreCategoryTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'sos_target',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sos_target' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
