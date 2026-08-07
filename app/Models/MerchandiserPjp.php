<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiserPjp extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervisor_id',
        'uploaded_by',
        'title',
        'week_start',
        'week_end',
        'kd_ids',
        'merchandiser_ids',
        'latitude',
        'longitude',
        'radius_meters',
        'file_path',
        'status',
        'forwarded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'kd_ids' => 'array',
            'merchandiser_ids' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'radius_meters' => 'integer',
            'forwarded_at' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function clockins(): HasMany
    {
        return $this->hasMany(MerchandiserPjpClockin::class, 'pjp_id');
    }
}
