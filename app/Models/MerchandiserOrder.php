<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiserOrder extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_orders';

    protected $fillable = ['user_id', 'outlet_id', 'kd_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function keyDistributor(): BelongsTo
    {
        return $this->belongsTo(KeyDistributor::class, 'kd_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchandiserOrderItem::class, 'order_id');
    }
}
