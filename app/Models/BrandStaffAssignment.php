<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandStaffAssignment extends Model
{
    use HasFactory;

    public const ROLE_AGENCY = 'agency_staff';
    public const ROLE_SUPPORT = 'supporting_staff';
    public const ROLE_ADMIN = 'brand_admin';

    protected $fillable = [
        'brand_id',
        'user_id',
        'role',
        'is_active',
        'notes',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
