<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmAccountTarget extends Model
{
    protected $guarded = ['id'];

    public function owner(){return $this->belongsTo(User::class, 'owner_id');}

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target_amount' => 'decimal:2', 'proposed_correction' => 'array', 'needs_reconciliation' => 'boolean'];
    }
}
