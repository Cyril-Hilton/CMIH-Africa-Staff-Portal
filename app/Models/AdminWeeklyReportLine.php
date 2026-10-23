<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminWeeklyReportLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['numeric_value' => 'float', 'due_date' => 'date'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
