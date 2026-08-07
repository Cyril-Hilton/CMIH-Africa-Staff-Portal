<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserNativeFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_assignment_id',
        'user_id',
        'outlet_id',
        'template_key',
        'answers',
        'normalized_metrics',
        'source_google_form_url',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'normalized_metrics' => 'array',
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
