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
        'current_choice',
        'purchase_intent',
        'preferred_channel',
        'is_new_to_brand',
        'marketing_consent',
        'data_consent',
        'verification_token',
        'otp_code',
        'otp_verified_at',
        'reward_code',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'is_new_to_brand' => 'boolean',
            'marketing_consent' => 'boolean',
            'data_consent' => 'boolean',
            'otp_verified_at' => 'datetime',
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
