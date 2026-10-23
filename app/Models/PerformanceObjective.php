<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceObjective extends Model
{
    protected $table = 'performance_objectives';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
