<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'body',
        'attachment_path',
        'original_attachment_path',
        'dropbox_shared_url',
        'dropbox_archived_at',
        'attachment_type',
        'is_edited',
        'is_deleted',
    ];

    protected $casts = [
        'is_edited'  => 'boolean',
        'is_deleted' => 'boolean',
        'dropbox_archived_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The message this is replying to */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /** Replies to this message */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /** Users who have read this message */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads', 'message_id', 'user_id')
                    ->withPivot('read_at');
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query
            ->where('user_id', '!=', $user->id)
            ->where('is_deleted', false)
            ->whereHas('conversation', function (Builder $conversationQuery) use ($user) {
                $conversationQuery
                    ->where('type', 'broadcast')
                    ->orWhereHas('users', function (Builder $memberQuery) use ($user) {
                        $memberQuery->where('users.id', $user->id);
                    });
            })
            ->whereDoesntHave('readers', function (Builder $readerQuery) use ($user) {
                $readerQuery->where('users.id', $user->id);
            });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return $this->attachment_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->attachment_type === 'video';
    }

    public function hasAttachment(): bool
    {
        return (bool) $this->attachment_path;
    }

    public function attachmentUrl(): ?string
    {
        if ($this->dropbox_shared_url) {
            return $this->dropbox_shared_url;
        }

        return $this->attachment_path ? route('portal.messages.attachment', $this) : null;
    }

    /** Check if a given user has read this message */
    public function isReadBy(User $user): bool
    {
        return $this->readers->contains('id', $user->id);
    }

    /** How many conversation members have read this (excluding sender) */
    public function readCount(): int
    {
        return $this->readers->where('id', '!=', $this->user_id)->count();
    }

    /** Display body (redacted if deleted) */
    public function displayBody(): string
    {
        if ($this->is_deleted) {
            return '🚫 This message was deleted';
        }
        return $this->body ?? '';
    }
}
