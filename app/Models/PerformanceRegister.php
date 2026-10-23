<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRegister extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime'];
    }
}
