<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserGoogleFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_assignment_id',
        'user_id',
        'outlet_id',
        'submitted_at',
        'response_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function formAssignment(): BelongsTo
    {
        return $this->belongsTo(MerchandiserGoogleFormAssignment::class, 'form_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
