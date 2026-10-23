<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceKpiRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'effective_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function kpi()
    {
        return $this->belongsTo(PerformanceKpi::class, 'kpi_id');
    }
}
