<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceTargetPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target' => 'float', 'weight' => 'float', 'approved_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function kpi()
    {
        return $this->belongsTo(PerformanceKpi::class, 'kpi_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function observation()
    {
        return $this->hasOne(PerformanceObservation::class, 'target_plan_id');
    }
}
