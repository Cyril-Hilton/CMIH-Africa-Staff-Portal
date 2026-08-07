<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserSupervisorAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervisor_id',
        'merchandiser_id',
        'kd_id',
    ];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function merchandiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchandiser_id');
    }

    public function keyDistributor(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }
}
