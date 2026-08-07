<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserOutletAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'outlet_id',
        'visit_id',
        'assigned_date',
        'assigned_start_at',
        'assigned_end_at',
        'sequence',
        'status',
        'source',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'assigned_start_at' => 'datetime',
            'assigned_end_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(MerchandiserVisit::class, 'visit_id');
    }
}
