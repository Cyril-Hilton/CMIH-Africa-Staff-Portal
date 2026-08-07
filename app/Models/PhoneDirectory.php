<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneDirectory extends Model
{
    protected $table = 'phone_directory';

    protected $fillable = [
        'user_id', 'name', 'job_title', 'department', 'phone',
        'extension', 'email', 'category', 'is_vendor', 'company',
    ];

    protected function casts(): array
    {
        return ['is_vendor' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
