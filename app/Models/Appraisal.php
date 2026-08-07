<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appraisal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quarter',
        'year',
        'self_assessment',
        'self_table_data',
        'manager_review',
        'manager_table_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'self_assessment' => 'json',
            'self_table_data' => 'json',
            'manager_review' => 'json',
            'manager_table_data' => 'json',
            'year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alias for HR matrix views */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Average self-assessment score (1-10) */
    public function getAvgSelfScoreAttribute(): float
    {
        $scores = $this->self_assessment['scores'] ?? [];
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
    }

    /** Average manager review score (1-10) */
    public function getAvgManagerScoreAttribute(): float
    {
        $scores = $this->manager_review['scores'] ?? [];
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
    }

    /** Combined final score average */
    public function getFinalScoreAttribute(): float
    {
        $self    = $this->avg_self_score;
        $manager = $this->avg_manager_score;
        if ($self && $manager) {
            return round(($self + $manager) / 2, 1);
        }
        return $self ?: $manager;
    }

    /** Human-readable status label */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'            => '📝 Draft',
            'self_assessment'  => '📋 Awaiting Self-Assessment',
            'submitted'        => '⏳ Pending Manager Review',
            'manager_reviewed' => '🔍 Pending HR Audit',
            'approved'         => '✅ Approved',
            default            => ucfirst($this->status),
        };
    }
}
