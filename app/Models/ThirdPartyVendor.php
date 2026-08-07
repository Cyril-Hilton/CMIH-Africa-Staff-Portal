<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirdPartyVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'assigned_project_id',
        'deliverable_status',
        'performance_review_notes',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'assigned_project_id');
    }
}
