<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosmLedger extends Model
{
    protected $table = 'posm_ledgers';

    protected $fillable = [
        'created_by', 'item_name', 'item_type', 'client_brand',
        'quantity_in', 'quantity_out', 'location', 'notes', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in'  => 'integer',
            'quantity_out' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getQuantityBalanceAttribute(): int
    {
        return max(0, $this->quantity_in - $this->quantity_out);
    }
}
