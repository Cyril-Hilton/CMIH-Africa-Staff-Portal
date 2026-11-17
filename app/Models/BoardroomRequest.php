<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardroomRequest extends Model
{
    public const STATUSES = [
        'pending' => 'Pending approval',
        'approved' => 'Approved',
        'checked_in' => 'In use',
        'completed' => 'Checked out',
        'rejected' => 'Declined',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys(['starts_at', 'ends_at', 'reviewed_at', 'checked_in_at', 'checked_out_at'], 'datetime');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function checkInRecorder()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkOutRecorder()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
