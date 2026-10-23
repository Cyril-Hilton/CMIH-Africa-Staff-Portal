<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPeriodicReport extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['revision' => 1, 'status' => 'draft'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'prepared_on' => 'date', 'section_states' => 'array', 'approved_at' => 'datetime'];
    }

    public function entries()
    {
        return $this->hasMany(HrReportEntry::class, 'report_id');
    }

    public function previous()
    {
        return $this->belongsTo(self::class, 'compared_report_id');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
