<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBudget extends Model
{
    protected $fillable = ['created_by', 'task_id', 'title', 'total_amount', 'currency', 'status', 'notes', 'content'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectBudgetItem::class, 'budget_id');
    }

    public function collaborators()
    {
        return $this->belongsToMany(User::class, 'budget_collaborators', 'budget_id', 'user_id')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /** Recalculate total from line items */
    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total');
        $this->save();
    }

    /** Helper to check if user can view the budget */
    public function canView(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        if ($user->access_role === 'super_admin' || $user->job_level === 'super_admin') {
            return true;
        }

        if (strtolower(trim($user->department ?? '')) === 'finance') {
            return true;
        }

        return $this->collaborators()->where('users.id', $user->id)->exists();
    }

    /** Helper to check if user can edit the budget */
    public function canEdit(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        if ($user->access_role === 'super_admin' || $user->job_level === 'super_admin') {
            return true;
        }

        return $this->collaborators()
            ->where('users.id', $user->id)
            ->wherePivot('permission', 'edit')
            ->exists();
    }
}
