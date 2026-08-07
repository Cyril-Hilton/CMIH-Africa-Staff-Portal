<?php

namespace App\Services;

class GhanaPayrollCalculator
{
    /**
     * Calculate SSNIT Employee Contribution (5.5%)
     */
    public static function ssnitEmployee(float $grossSalary): float
    {
        return round($grossSalary * 0.055, 2);
    }

    /**
     * Calculate SSNIT Employer Contribution (13.0%)
     */
    public static function ssnitEmployer(float $grossSalary): float
    {
        return round($grossSalary * 0.13, 2);
    }

    /**
     * Calculate Ghana GRA PAYE Monthly Income Tax
     */
    public static function calculatePayeTax(float $taxableIncome): float
    {
        if ($taxableIncome <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $income = $taxableIncome;

        // Bracket 1: First GHS 490 @ 0%
        if ($income <= 490) {
            return 0.0;
        }
        $income -= 490;

        // Bracket 2: Next GHS 110 @ 5%
        $taxable = min($income, 110);
        $tax += $taxable * 0.05;
        $income -= $taxable;
        if ($income <= 0) return round($tax, 2);

        // Bracket 3: Next GHS 130 @ 10%
        $taxable = min($income, 130);
        $tax += $taxable * 0.10;
        $income -= $taxable;
        if ($income <= 0) return round($tax, 2);

        // Bracket 4: Next GHS 3,166.67 @ 17.5%
        $taxable = min($income, 3166.67);
        $tax += $taxable * 0.175;
        $income -= $taxable;
        if ($income <= 0) return round($tax, 2);

        // Bracket 5: Next GHS 16,000 @ 25%
        $taxable = min($income, 16000);
        $tax += $taxable * 0.25;
        $income -= $taxable;
        if ($income <= 0) return round($tax, 2);

        // Bracket 6: Next GHS 30,500 @ 30%
        $taxable = min($income, 30500);
        $tax += $taxable * 0.30;
        $income -= $taxable;
        if ($income <= 0) return round($tax, 2);

        // Bracket 7: Above GHS 50,396.67 @ 35%
        $tax += $income * 0.35;

        return round($tax, 2);
    }

    /**
     * Complete Payroll Calculation for a Staff Member
     */
    public static function calculate(float $grossSalary, float $otherDeductions = 0.0, float $bonuses = 0.0): array
    {
        $ssnitEmployee = self::ssnitEmployee($grossSalary);
        $ssnitEmployer = self::ssnitEmployer($grossSalary);
        $taxableIncome = max(0, $grossSalary - $ssnitEmployee);
        $payeTax = self::calculatePayeTax($taxableIncome);

        $totalDeductions = round($ssnitEmployee + $payeTax + $otherDeductions, 2);
        $netSalary = round(($grossSalary - $totalDeductions) + $bonuses, 2);

        return [
            'gross_salary' => round($grossSalary, 2),
            'ssnit_employee' => $ssnitEmployee,
            'ssnit_employer' => $ssnitEmployer,
            'taxable_income' => round($taxableIncome, 2),
            'paye_tax' => $payeTax,
            'other_deductions' => round($otherDeductions, 2),
            'total_deductions' => $totalDeductions,
            'bonuses' => round($bonuses, 2),
            'net_salary' => max(0, $netSalary),
        ];
    }
}
