<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioPayment extends Model
{
    protected $table = 'portfolio_payments';

    protected $fillable = [
        'name',
        'email',
        'item',
        'description',
        'amount',
        'currency',
        'reference',
        'status',
        'raw_response',
    ];

    /**
     * Get a human-readable label for the payment item.
     */
    public function itemLabel(): string
    {
        return [
            'company_profile' => 'Company Profile',
            'design_brief' => 'Design Brief',
            'mockup' => 'Mockup',
            'buy_a_plan' => 'Buy a Plan',
            'buy_a_catalogue' => 'Buy a Catalogue',
        ][$this->item] ?? ucfirst(str_replace('_', ' ', $this->item));
    }
}
