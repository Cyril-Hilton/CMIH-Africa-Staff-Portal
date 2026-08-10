<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandFieldActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'brand_activation_id',
        'user_id',
        'staff_role',
        'activity_type',
        'location',
        'units',
        'notes',
        'metadata',
        'evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'units' => 'integer',
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
