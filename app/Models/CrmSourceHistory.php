<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSourceHistory extends Model
{
    protected $table = 'crm_source_history';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cells' => 'array', 'formulas' => 'array', 'hidden_sheet' => 'boolean', 'imported_at' => 'datetime'];
    }
}
