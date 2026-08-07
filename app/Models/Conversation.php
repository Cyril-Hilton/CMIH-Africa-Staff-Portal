<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'creator_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
                    ->withPivot('is_admin')
                    ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Display name for the conversation from a given user's perspective.
     */
    public function getDisplayName(User $currentUser): string
    {
        if ($this->type === 'broadcast') {
            return '# All Staff';
        }

        if ($this->name) {
            return $this->name;
        }

        // For DMs: return the other person's name
        if ($this->type === 'direct') {
            $other = $this->users->firstWhere('id', '!=', $currentUser->id);
            return $other ? $other->name : 'Unknown';
        }

        return 'Group Chat';
    }

    /**
     * Display avatar/photo for the conversation.
     */
    public function getDisplayPhoto(User $currentUser): ?string
    {
        if ($this->type === 'direct') {
            $other = $this->users->firstWhere('id', '!=', $currentUser->id);
            return $other ? $other->profilePhotoUrl() : null;
        }

        return null;
    }

    /**
     * Check whether a given user has access to this conversation.
     */
    public function hasAccess(User $user): bool
    {
        if ($this->type === 'broadcast') {
            return true;
        }

        return $this->users->contains('id', $user->id);
    }

    /**
     * Check whether a given user is admin of this group conversation.
     */
    public function isAdmin(User $user): bool
    {
        if ($this->type !== 'group') {
            return false;
        }

        $pivot = $this->users->firstWhere('id', $user->id);
        return $pivot && $pivot->pivot->is_admin;
    }

    /**
     * Latest message preview (for sidebar).
     */
    public function latestMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public function unreadCountFor(User $user): int
    {
        return $this->messages()->unreadFor($user)->count();
    }
}
