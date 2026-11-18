<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirdPartyVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'hr_details',
        'name',
        'category',
        'assigned_project_id',
        'campaign_id',
        'deliverable_status',
        'performance_review_notes',
    ];

    protected function casts(): array { return ['hr_details'=>'array']; }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'assigned_project_id');
    }
}
