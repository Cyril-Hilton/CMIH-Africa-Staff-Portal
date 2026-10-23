<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceDependencyRequest extends Model
{
    protected $guarded = ['id'];

    public function canReview(User $reviewer): bool
    {
        if ($this->status !== 'pending' || (int) $this->submitted_by === (int) $reviewer->id) {
            return false;
        }
        $proposer = User::find($this->submitted_by);
        if (! $proposer || ! $this->upstream || ! $this->downstream) {
            return false;
        }
        $other = \App\Services\PerformanceAccess::manages($proposer, $this->upstream->department) ? $this->downstream->department : $this->upstream->department;

        return \App\Services\PerformanceAccess::manages($reviewer, $other);
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
