<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrReportEntry extends Model
{
    use \App\Models\Concerns\ReportingArchive;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
