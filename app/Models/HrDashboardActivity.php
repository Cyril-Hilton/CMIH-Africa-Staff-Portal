<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrDashboardActivity extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    public const STATUSES = ['Not Started', 'In Progress', 'Pending Approval', 'On Hold', 'Completed', 'Cancelled'];

    protected $fillable = ['tab', 'category', 'activity', 'staff_id', 'department', 'opened_on', 'deadline',
        'assigned_to', 'created_by', 'status', 'remarks', 'attachment', 'revision', 'supporting_staff_ids','priority','linked_type','linked_id','completed_at','approved_at','approved_by'];

    protected function casts(): array
    {
        return ['supporting_staff_ids'=>'array','attachment' => 'array','completed_at'=>'datetime','approved_at'=>'datetime'];
    }

    public function isOverdue(?string $asOf=null): bool
    {
        return $this->deadline && $this->deadline < ($asOf??today()->toDateString()) && !in_array($this->status,['Completed','Cancelled']);
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
