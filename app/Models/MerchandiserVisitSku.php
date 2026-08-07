<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserVisitSku extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_visit_skus';

    protected $fillable = [
        'visit_id', 'sku_id', 'osa_quantity', 'npd_present',
        'facing', 'share_of_shelf', 'planogram_compliant',
        'ai_predicted_quantity', 'ai_predicted_facing',
        'ai_predicted_share_of_shelf', 'ai_predicted_planogram_compliant',
        'ai_confidence', 'ai_detection_boxes', 'ai_raw_detection',
    ];

    protected $casts = [
        'npd_present' => 'boolean',
        'planogram_compliant' => 'boolean',
        'share_of_shelf' => 'decimal:2',
        'ai_predicted_planogram_compliant' => 'boolean',
        'ai_predicted_share_of_shelf' => 'decimal:2',
        'ai_confidence' => 'decimal:2',
        'ai_detection_boxes' => 'array',
        'ai_raw_detection' => 'array',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(MerchandiserVisit::class, 'visit_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
