<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'submitted_by', 'task_id', 'invoice_number', 'supplier_name',
        'description', 'amount', 'currency', 'attachment_path', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
