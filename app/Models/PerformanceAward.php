<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'award_type',
        'period',
        'winner_id',
        'first_runner_up_id',
        'second_runner_up_id',
        'winner_val',
        'first_runner_up_val',
        'second_runner_up_val',
        'winner_score',
        'first_runner_up_score',
        'second_runner_up_score',
    ];

    protected $casts = [
        'winner_score' => 'decimal:2',
        'first_runner_up_score' => 'decimal:2',
        'second_runner_up_score' => 'decimal:2',
    ];

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function firstRunnerUp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_runner_up_id');
    }

    public function secondRunnerUp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_runner_up_id');
    }

    /**
     * Get label for department code.
     */
    public static function getDepartmentLabel(?string $code): string
    {
        $departments = [
            'hr_admin'            => 'HR & Admin',
            'finance'             => 'Finance',
            'client_relations'    => 'Client Relations',
            'operations_projects' => 'Operations / Projects',
            'brands_marketing'    => 'Brands & Marketing',
            'creatives'           => 'Creatives',
        ];

        return $departments[$code] ?? $code ?? 'N/A';
    }
}
