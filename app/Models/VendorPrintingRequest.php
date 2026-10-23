<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPrintingRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['information' => 'array', 'items' => 'array', 'vendor' => 'array', 'checklist' => 'array', 'revision' => 'integer'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
