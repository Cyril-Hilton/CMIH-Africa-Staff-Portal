<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformancePolicy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'effective_on' => 'date', 'approved_at' => 'datetime'];
    }
}
