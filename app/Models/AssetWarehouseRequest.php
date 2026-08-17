<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetWarehouseRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_CHECK = 'pending_check_approval';
    public const STATUS_APPROVED_TO_CHECK = 'approved_to_check';
    public const STATUS_RETURNED_FOR_CORRECTION = 'returned_for_correction';
    public const STATUS_INSPECTION_SUBMITTED = 'inspection_submitted';
    public const STATUS_APPROVED_FOR_USE = 'approved_for_use';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_RETURNED_PENDING_CLOSURE = 'returned_pending_closure';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'request_code',
        'asset_id',
        'requested_by',
        'reviewed_by',
        'issued_by',
        'closed_by',
        'requested_quantity',
        'requested_for',
        'destination_location',
        'status',
        'purpose',
        'requester_notes',
        'review_note',
        'issue_note',
        'return_note',
        'pre_use_image_path',
        'issue_image_path',
        'return_image_path',
        'approved_to_check_at',
        'approved_for_use_at',
        'issued_at',
        'returned_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_for' => 'date',
            'approved_to_check_at' => 'datetime',
            'approved_for_use_at' => 'datetime',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
            'requested_quantity' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public static function statusLabel(?string $status): string
    {
        return [
            self::STATUS_PENDING_CHECK => 'Awaiting Check Approval',
            self::STATUS_APPROVED_TO_CHECK => 'Approved To Check',
            self::STATUS_RETURNED_FOR_CORRECTION => 'Returned For Correction',
            self::STATUS_INSPECTION_SUBMITTED => 'Inspection Submitted',
            self::STATUS_APPROVED_FOR_USE => 'Approved For Use',
            self::STATUS_ISSUED => 'Issued / In Use',
            self::STATUS_RETURNED_PENDING_CLOSURE => 'Returned, Pending Closure',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_REJECTED => 'Rejected',
        ][$status] ?? 'Pending';
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_CLOSED, self::STATUS_REJECTED], true);
    }
}
