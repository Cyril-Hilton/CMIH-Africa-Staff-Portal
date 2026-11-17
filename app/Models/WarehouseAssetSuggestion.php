<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseAssetSuggestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reported' => 'array', 'review' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function activation()
    {
        return $this->belongsTo(PerformanceRegister::class, 'activation_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
