<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancePromoter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact',
        'city',
        'language',
        'tshirt_size',
        'height',
    ];
}
