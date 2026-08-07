<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorPreTicket extends Model
{
    protected $fillable = [
        'created_by', 'visitor_name', 'visitor_company', 'visitor_email',
        'visitor_phone', 'purpose', 'host_id', 'expected_arrival', 'status',
    ];

    protected function casts(): array
    {
        return ['expected_arrival' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
