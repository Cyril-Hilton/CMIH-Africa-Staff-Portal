<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseAssetCollaborator extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'granted_by',
        'can_edit',
        'can_import',
        'can_approve',
        'is_active',
        'notes',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
            'can_import' => 'boolean',
            'can_approve' => 'boolean',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
