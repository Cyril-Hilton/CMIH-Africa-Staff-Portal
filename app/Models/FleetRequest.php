<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetRequest extends Model
{
    use HasFactory;

    public const COMPANY_VEHICLES = [
        'toyota_corolla_salon_car' => 'Toyota Corolla Salon Car',
        'office_truck_medium' => 'Office Truck - Medium',
        'office_truck_large' => 'Office Truck - Large',
    ];

    public const RIDE_HAILING_OPTIONS = [
        'uber' => 'Uber',
        'bolt' => 'Bolt',
        'yango' => 'Yango',
    ];

    protected $fillable = [
        'user_id',
        'assistance_type',
        'vehicle_option',
        'pickup_location',
        'destination',
        'requested_date',
        'requested_time',
        'passengers',
        'purpose',
        'notes',
        'status',
        'hr_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'reviewed_at' => 'datetime',
            'passengers' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function optionLabel(): string
    {
        return self::COMPANY_VEHICLES[$this->vehicle_option]
            ?? self::RIDE_HAILING_OPTIONS[$this->vehicle_option]
            ?? $this->vehicle_option;
    }
}
