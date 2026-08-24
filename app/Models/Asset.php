<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'condition',
        'assigned_to',
        'location',
        'notes',
        'image_path',
        'added_by',
        'brand',
        'asset_tag',
        'serial_number',
        'category',
        'asset_value',
        'po_quantity',
        'quantity_procured',
        'owner',
        'asset_use_type',
        'remodel_status',
        'remodel_notes',
        'warehouse_location',
        'warehouse_quantity',
        'is_warehouse_tracked',
        'warehouse_notes',
        'last_handled_by',
        'last_handled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_warehouse_tracked' => 'boolean',
            'warehouse_quantity' => 'integer',
            'asset_value' => 'decimal:2',
            'po_quantity' => 'integer',
            'quantity_procured' => 'integer',
            'last_handled_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function lastHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_handled_by');
    }

    public function assetLogs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function warehouseRequests(): HasMany
    {
        return $this->hasMany(AssetWarehouseRequest::class);
    }
}
