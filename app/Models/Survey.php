<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'event_id',
        'title',
        'slug',
        'description',
        'is_anonymous',
        'status',
        'settings',
        'cmih_logo_path',
        'client_logo_path',
        'client_brand_name',
        'client_logo_path_2',
        'client_brand_name_2',
        'success_message',
        'location_enabled',
        'location_url',
        'location_label',
    ];

    protected $casts = [
        'is_anonymous'     => 'boolean',
        'location_enabled' => 'boolean',
        'settings'         => 'array',
    ];


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
