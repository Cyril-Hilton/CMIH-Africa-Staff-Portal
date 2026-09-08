<?php

namespace App\Support;

use App\Models\SiteContent;
use App\Models\User;

class SalaryAdvancePolicy
{
    public const DEFAULT_MONTHLY_DEDUCTION_MINIMUM = 500.00;
    public const DEFAULT_MAXIMUM_SALARY_MULTIPLIER = 2.00;
    public const DEFAULT_TERMS_NOTE = 'HR reviews each salary advance request, confirms the agreed repayment terms, and then Finance approves and processes payment when funds are available.';
    public const DEFAULT_MINIMUM_KEY = 'salary_advance_default_monthly_deduction_minimum';
    public const DEFAULT_MAXIMUM_MULTIPLIER_KEY = 'salary_advance_default_maximum_salary_multiplier';
    public const TERMS_NOTE_KEY = 'salary_advance_terms_and_conditions_note';

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

    public static function defaultMaximumSalaryMultiplier(): float
    {
        return self::normalizeMultiplier(
            SiteContent::getValue(self::DEFAULT_MAXIMUM_MULTIPLIER_KEY, (string) self::DEFAULT_MAXIMUM_SALARY_MULTIPLIER)
        );
    }

    public static function effectiveMaximumAmount(?User $user): float
    {
        $override = $user?->salary_advance_max_amount;

        if ($override !== null && (float) $override > 0) {
            return self::normalizeMoney($override);
        }

        return self::normalizeMoney(($user?->monthlySalary() ?? 0) * self::defaultMaximumSalaryMultiplier());
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

    public static function setDefaultMaximumSalaryMultiplier(float $multiplier, ?int $updatedBy = null): void
    {
        SiteContent::updateOrCreate(
            ['key' => self::DEFAULT_MAXIMUM_MULTIPLIER_KEY],
            [
                'value' => number_format(self::normalizeMultiplier($multiplier), 2, '.', ''),
                'type' => 'number',
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function termsNote(): string
    {
        return trim((string) SiteContent::getValue(self::TERMS_NOTE_KEY, self::DEFAULT_TERMS_NOTE)) ?: self::DEFAULT_TERMS_NOTE;
    }

    public static function setTermsNote(string $note, ?int $updatedBy = null): void
    {
        SiteContent::updateOrCreate(
            ['key' => self::TERMS_NOTE_KEY],
            [
                'value' => trim($note) ?: self::DEFAULT_TERMS_NOTE,
                'type' => 'text',
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function normalizeMinimum(mixed $value): float
    {
        return self::normalizeMoney($value);
    }

    public static function normalizeMoney(mixed $value): float
    {
        return round(max(0.01, (float) $value), 2);
    }

    public static function normalizeMultiplier(mixed $value): float
    {
        return round(max(0.01, (float) $value), 2);
    }

    public static function minimumValidationMessage(float $minimum): string
    {
        return 'The monthly deduction amount must be at least GHC '.number_format($minimum, 2).'.';
    }

    public static function maximumValidationMessage(float $maximum): string
    {
        return 'The loan amount cannot exceed the current HR-approved maximum of GHC '.number_format($maximum, 2).'.';
    }
}
