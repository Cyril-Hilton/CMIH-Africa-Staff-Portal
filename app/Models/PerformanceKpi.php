<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceKpi extends Model
{
    protected $table = 'performance_kpis';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date', 'green_threshold' => 'float', 'amber_threshold' => 'float', 'weight' => 'float', 'evidence_required' => 'boolean'];
    }

    public function objective()
    {
        return $this->belongsTo(PerformanceObjective::class, 'objective_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function observations()
    {
        return $this->hasMany(PerformanceObservation::class, 'kpi_id');
    }
}
