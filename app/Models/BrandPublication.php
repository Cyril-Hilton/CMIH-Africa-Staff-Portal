<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandPublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'brand_activation_id',
        'title',
        'category',
        'status',
        'summary',
        'body',
        'image_path',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
