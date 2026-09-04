<?php

namespace App\Support;

use App\Models\SiteContent;
use App\Models\User;

class SalaryAdvancePolicy
{
    public const DEFAULT_MONTHLY_DEDUCTION_MINIMUM = 500.00;
    public const DEFAULT_MINIMUM_KEY = 'salary_advance_default_monthly_deduction_minimum';

    public static function defaultMonthlyDeductionMinimum(): float
    {
        return self::normalizeMinimum(
            SiteContent::getValue(self::DEFAULT_MINIMUM_KEY, (string) self::DEFAULT_MONTHLY_DEDUCTION_MINIMUM)
        );
    }

    public static function effectiveMonthlyDeductionMinimum(?User $user): float
    {
        $override = $user?->salary_advance_min_monthly_deduction;

        if ($override !== null && (float) $override > 0) {
            return self::normalizeMinimum($override);
        }

        return self::defaultMonthlyDeductionMinimum();
    }

    public static function setDefaultMonthlyDeductionMinimum(float $amount, ?int $updatedBy = null): void
    {
        SiteContent::updateOrCreate(
            ['key' => self::DEFAULT_MINIMUM_KEY],
            [
                'value' => number_format(self::normalizeMinimum($amount), 2, '.', ''),
                'type' => 'money',
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function normalizeMinimum(mixed $value): float
    {
        return round(max(0.01, (float) $value), 2);
    }

    public static function minimumValidationMessage(float $minimum): string
    {
        return 'The monthly deduction amount must be at least GHC '.number_format($minimum, 2).'.';
    }
}
