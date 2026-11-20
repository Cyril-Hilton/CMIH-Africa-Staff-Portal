<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationsReportSubmission extends Model
{
    use \App\Models\Concerns\ReportingArchive;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'revision' => 'integer'];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
