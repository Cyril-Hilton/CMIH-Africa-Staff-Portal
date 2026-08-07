<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Update extends Model
{
    use HasFactory;

    protected $table = 'tasks';

    protected $fillable = [
        'assigned_to',
        'assigned_by',
        'title',
        'details',
        'status',
        'priority',
        'progress',
        'due_on',
        'notes_feedback',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'progress' => 'integer',
        ];
    }

    // Alias properties for backward compatibility
    public function getUserIdAttribute()
    {
        return $this->assigned_to;
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['assigned_to'] = $value;
        $this->attributes['assigned_by'] = $value ?? $this->attributes['assigned_by'] ?? null;
    }

    public function getSummaryAttribute()
    {
        return $this->details;
    }

    public function setSummaryAttribute($value)
    {
        $this->attributes['details'] = $value;
    }

    public function getNotesAttribute()
    {
        return $this->notes_feedback;
    }

    public function setNotesAttribute($value)
    {
        $this->attributes['notes_feedback'] = $value;
    }

    public function getTimelineAttribute()
    {
        return null;
    }

    public function setTimelineAttribute($value)
    {
        // ignored
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
