<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPipelineProject extends Model
{
    use \App\Models\Concerns\ReviewsRevenue;

    protected $guarded = ['id'];

    protected $attributes = ['revision' => 1];

    protected $hidden = ['source_forecast', 'source_values', 'rate_card_value', 'execution_value', 'proposed_amount', 'po_amount', 'target_amount', 'actual_amount', 'invoiced_amount', 'collected_amount', 'bottlenecks', 'next_action', 'contact_name', 'contact_info', 'contact_position', 'remarks', 'source_reference'];

    protected function casts(): array
    {
        return ['source_values' => 'array', 'source_period_confirmed' => 'boolean', 'source_forecast' => 'decimal:2', 'new_business' => 'boolean', 'rate_card_value' => 'decimal:2', 'execution_value' => 'decimal:2', 'week_start' => 'date', 'expected_close_date' => 'date', 'proposed_amount' => 'decimal:2', 'po_amount' => 'decimal:2', 'target_amount' => 'decimal:2', 'actual_amount' => 'decimal:2', 'invoiced_amount' => 'decimal:2', 'collected_amount' => 'decimal:2'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
