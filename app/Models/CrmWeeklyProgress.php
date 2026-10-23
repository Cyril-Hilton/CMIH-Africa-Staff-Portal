<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmWeeklyProgress extends Model
{
    protected $table = 'crm_weekly_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'expected_close_date' => 'date'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
