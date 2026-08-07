<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'timezone'];

    public function keyDistributors(): HasMany
    {
        return $this->hasMany(KeyDistributor::class);
    }
}
