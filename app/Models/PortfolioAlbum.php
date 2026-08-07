<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioAlbum extends Model
{
    protected $fillable = [
        'title',
        'brand',
        'cover_image',
        'description',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function images()
    {
        return $this->hasMany(PortfolioImage::class);
    }
}
