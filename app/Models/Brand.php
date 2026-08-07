<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    //
    protected $fillable = ['name', 'logo_path', 'logo_dark_path'];
}
