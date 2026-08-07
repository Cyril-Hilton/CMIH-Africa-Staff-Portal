<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserPlanogram extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'channel_type',
        'reference_file_path',
        'playbook_notes',
        'checklist',
        'status',
        'created_by',
    ];

    protected $casts = [
        'checklist' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
