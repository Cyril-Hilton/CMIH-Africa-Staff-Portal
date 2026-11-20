<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRegister extends Model
{
    use \App\Models\Concerns\ReportingArchive;

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime'];
    }
}
