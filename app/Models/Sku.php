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
        'track_osa',
        'osa_drop_size',
        'track_npd',
        'npd_drop_size',
        'track_mhs',
        'mhs_drop_size',
        'reference_image_path',
        'aliases',
        'ai_reference_notes',
    ];

    protected $casts = [
        'aliases' => 'array',
        'track_osa' => 'boolean',
        'osa_drop_size' => 'integer',
        'track_npd' => 'boolean',
        'npd_drop_size' => 'integer',
        'track_mhs' => 'boolean',
        'mhs_drop_size' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
