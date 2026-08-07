<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CollaborativeDocument extends Model
{
    protected $fillable = [
        'title',
        'doc_type',
        'content',
        'file_path',
        'file_name',
        'created_by',
        'current_holder_id',
        'status',
    ];

    /**
     * Get the user who created the document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who is currently holding the action/approval authority.
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    /**
     * Get the collaborators assigned to this document.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_collaborators', 'document_id', 'user_id')
                    ->withPivot('permission')
                    ->withTimestamps();
    }
}
