<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'brand_activation_id',
        'user_id',
        'action',
        'context',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(BrandActivation::class, 'brand_activation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
