<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandConsumerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'brand_activation_id',
        'name',
        'phone',
        'email',
        'age_band',
        'gender',
        'location',
        'source',
        'result_type',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
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
}
