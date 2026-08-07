<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiserOrderItem extends Model
{
    use HasFactory;

    protected $table = 'merchandiser_order_items';

    protected $fillable = ['order_id', 'sku_id', 'quantity'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MerchandiserOrder::class, 'order_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
