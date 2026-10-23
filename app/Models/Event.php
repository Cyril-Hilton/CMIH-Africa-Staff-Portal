<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'updated_by', 'event_key', 'flyer_sha256', 'flyer_fingerprint', 'original_filename', 'filename_key', 'image_disk', 'revision',
        'title',
        'summary',
        'location',
        'starts_at',
        'ends_at',
        'registration_url',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date', 'flyer_fingerprint' => 'array', 'revision' => 'integer',
            'ends_at' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
