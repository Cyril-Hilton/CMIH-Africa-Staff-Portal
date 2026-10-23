<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceEvidence extends Model
{
    protected $table = 'performance_evidence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
