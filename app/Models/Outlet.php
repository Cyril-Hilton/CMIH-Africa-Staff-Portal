<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'kd_id',
        'channel_type',
        'address',
        'latitude',
        'longitude',
        'registered_by',
        'coordinates_locked_at',
        'coordinates_captured_by',
        'coordinates_source',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'coordinates_locked_at' => 'datetime',
    ];

    public function keyDistributor(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function coordinateCapturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinates_captured_by');
    }

    public function assignedMerchandisers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'merchandiser_outlet_user')
            ->withPivot(['assigned_by', 'assigned_at', 'visit_days'])
            ->withTimestamps();
    }

    public function merchandiserAttendances(): HasMany
    {
        return $this->hasMany(MerchandiserAttendance::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(MerchandiserVisit::class);
    }

    public function merchandiserAssignments(): HasMany
    {
        return $this->hasMany(MerchandiserOutletAssignment::class);
    }
}
