<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MerchandiserReport extends Model
{
    protected $table = 'merchandiser_reports';

    protected $fillable = [
        'token',
        'created_by',
        'label',
        'sections_config',
        'expires_at',
        'is_revoked',
        'view_count',
        'last_viewed_at',
    ];

    protected $casts = [
        'sections_config'  => 'array',
        'expires_at'       => 'datetime',
        'is_revoked'       => 'boolean',
        'last_viewed_at'   => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return !$this->is_revoked && $this->expires_at->isFuture();
    }

    public function section(string $key): bool
    {
        return (bool) ($this->sections_config[$key] ?? false);
    }
}
