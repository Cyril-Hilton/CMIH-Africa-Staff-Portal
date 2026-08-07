<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'client_name',
        'project_lead_id',
        'status',
        'status_update',
        'start_date',
        'end_date',
        'location_brief',
        'duration',
        'budget',
        'share_token',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration' => 'integer',
            'budget' => 'decimal:2',
        ];
    }

    public function projectLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_lead_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function assetLogs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function campaignPhotos(): HasMany
    {
        return $this->hasMany(CampaignPhoto::class);
    }
}
