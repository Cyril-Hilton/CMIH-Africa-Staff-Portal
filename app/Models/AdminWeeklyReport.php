<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminWeeklyReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(AdminWeeklyReportLine::class, 'report_id');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
