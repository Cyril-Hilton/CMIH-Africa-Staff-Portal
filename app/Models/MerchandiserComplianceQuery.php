<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserComplianceQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sent_by',
        'channel',
        'subject',
        'message',
        'issues',
        'email_sent',
        'sms_attempted',
        'sms_sent',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issues' => 'array',
            'email_sent' => 'boolean',
            'sms_attempted' => 'boolean',
            'sms_sent' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
