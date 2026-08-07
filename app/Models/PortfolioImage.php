<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioImage extends Model
{
    protected $fillable = [
        'portfolio_album_id',
        'image_path',
    ];

    public function album()
    {
        return $this->belongsTo(PortfolioAlbum::class, 'portfolio_album_id');
    }
}
