<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'metric_type',
        'table_template',
        'default_rows',
    ];

    protected function casts(): array
    {
        return [
            'table_template' => 'array',
            'default_rows' => 'integer',
        ];
    }
}
