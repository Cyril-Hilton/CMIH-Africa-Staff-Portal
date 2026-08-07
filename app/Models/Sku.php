<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sku extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand_id',
        'category',
        'reference_image_path',
        'aliases',
        'ai_reference_notes',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
