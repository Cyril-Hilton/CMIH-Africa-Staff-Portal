<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_point',
        'assignee_id',
        'assignee_name',
        'status',
        'comments',
        'due_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayAssigneeAttribute(): string
    {
        if ($this->assignee) {
            return $this->assignee->name;
        }

        return $this->assignee_name ?: 'Unassigned';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match (strtolower($this->status)) {
            'done' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'in_progress' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            'not_done' => 'bg-red-500/10 text-red-400 border-red-500/20',
            default => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status)) {
            'done' => 'Done',
            'in_progress' => 'In Progress',
            'not_done' => 'Not Done',
            default => 'Pending',
        };
    }
}
