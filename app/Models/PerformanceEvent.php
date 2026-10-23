<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceEvent extends Model
{
    protected $table = 'performance_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit events are retained.'));
    }

    public function actor(){return $this->belongsTo(User::class, 'actor_id');}
}
