<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceOutbox extends Model
{
    protected $table = 'performance_outbox';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dashboard_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    public function user(){return $this->belongsTo(User::class, 'user_id');}
}
