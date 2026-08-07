<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiserVisit extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_visits';

    protected $fillable = [
        'user_id',
        'outlet_id',
        'route_assignment_id',
        'branded_shelf_available',
        'hangers_available',
        'planogram_id',
        'planogram_score',
        'planogram_notes',
        'planogram_photo_path',
        'sku_entry_mode',
        'ai_detection_status',
        'ai_shelf_photo_path',
        'ai_detection_payload',
        'ai_detection_notes',
        'ai_detection_review_required',
        'ai_detection_completed_at',
    ];

    protected $casts = [
        'branded_shelf_available' => 'boolean',
        'hangers_available' => 'boolean',
        'ai_detection_payload' => 'array',
        'ai_detection_review_required' => 'boolean',
        'ai_detection_completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function routeAssignment(): BelongsTo
    {
        return $this->belongsTo(MerchandiserOutletAssignment::class, 'route_assignment_id');
    }

    public function planogram(): BelongsTo
    {
        return $this->belongsTo(MerchandiserPlanogram::class);
    }

    public function visitSkus(): HasMany
    {
        return $this->hasMany(MerchandiserVisitSku::class, 'visit_id');
    }
}
