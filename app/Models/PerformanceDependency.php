<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceDependency extends Model
{
    protected $table = 'performance_dependencies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function upstream()
    {
        return $this->belongsTo(WeeklyConsolidatedItem::class, 'upstream_id');
    }

    public function downstream()
    {
        return $this->belongsTo(WeeklyConsolidatedItem::class, 'downstream_id');
    }
}
