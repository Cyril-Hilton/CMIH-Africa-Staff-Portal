<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReportPack extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Published report packs are immutable.'));
        static::deleting(fn () => throw new \LogicException('Published report packs cannot be deleted.'));
    }
}
