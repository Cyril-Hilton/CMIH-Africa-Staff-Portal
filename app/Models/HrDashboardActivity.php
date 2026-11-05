<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrDashboardActivity extends Model
{
    public const STATUSES = ['Not Started', 'In Progress', 'Pending Approval', 'On Hold', 'Completed', 'Overdue', 'Cancelled'];

    protected $fillable = ['tab', 'category', 'activity', 'staff_id', 'department', 'opened_on', 'deadline',
        'assigned_to', 'created_by', 'status', 'remarks', 'attachment', 'revision'];

    protected function casts(): array
    {
        return ['attachment' => 'array'];
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
