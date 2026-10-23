<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceObservation extends Model
{
    protected $table = 'performance_observations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target' => 'float', 'actual' => 'float', 'numerator' => 'float', 'denominator' => 'float', 'approved_at' => 'datetime'];
    }

    public function kpi()
    {
        return $this->belongsTo(PerformanceKpi::class, 'kpi_id');
    }

    public function targetPlan()
    {
        return $this->belongsTo(PerformanceTargetPlan::class, 'target_plan_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
