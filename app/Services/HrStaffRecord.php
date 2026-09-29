<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrStaffRecord
{
    // Explicit fields exclude credentials and future system secrets from HR exports.
    public const GROUPS = [
        'Personal information' => ['id', 'staff_id_number', 'name', 'date_of_birth', 'birthday_month', 'birthday_day', 'nationality_code', 'email', 'contact_email', 'phone', 'residential_address', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relation'],
        'Employment' => ['department', 'job_title', 'position_title', 'job_level', 'access_role', 'status', 'start_date', 'contract_expires_at', 'line_manager_name', 'line_manager_id', 'leave_balance', 'id_expires_at', 'id_card_sent_at'],
        'Pay and banking' => ['salary', 'payroll_deductions', 'payroll_rewards_bonus', 'payroll_notes', 'salary_advance_min_monthly_deduction', 'salary_advance_max_amount', 'ssnit_number', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'momo_number', 'momo_name'],
        'Identity' => ['identity_document_type', 'national_id_type', 'national_id_number', 'ghana_card_number', 'passport_number'],
        'Documents' => ['profile_photo_path', 'contract_path', 'job_description_path', 'national_id_front_path', 'national_id_back_path', 'ghana_card_path', 'ghana_card_front_path', 'ghana_card_back_path', 'passport_photo_path'],
        'Other staff details' => ['tshirt_size', 'height', 'languages_spoken', 'operational_city', 'supervisor_id', 'kd_id', 'region_id', 'tm_id', 'dsr_id', 'rsm_id', 'merchandiser_working_days', 'merchandiser_daily_outlet_target', 'merchandiser_outlet_frequency'],
        'Account and change history' => ['requested_department', 'requested_position_title', 'requested_change_at', 'email_verified_at', 'last_login_at', 'previous_login_at', 'last_seen_at', 'last_login_ip', 'last_login_user_agent', 'permissions_matrix', 'mute_sounds', 'created_at', 'updated_at'],
    ];

    public const HISTORIES = [
        'leave' => ['Leave applications', 'leave_applications', 'user_id'],
        'attendance' => ['Attendance', 'attendance', 'user_id'],
        'appraisals' => ['Appraisals', 'appraisals', 'user_id'],
        'payslips' => ['Payslips', 'payslips', 'user_id'],
        'advances' => ['Salary advances', 'salary_advances', 'user_id'],
        'repayments' => ['Salary advance repayments', 'salary_advance_repayments', 'salary_advance_id'],
        'claims' => ['Expense claims', 'petty_cash_claims', 'user_id'],
        'assets' => ['Assigned assets', 'assets', 'assigned_to'],
        'asset_logs' => ['Asset activity', 'asset_logs', 'user_id'],
        'fleet' => ['Transport requests', 'fleet_requests', 'user_id'],
        'tasks' => ['Assigned tasks', 'tasks', 'assigned_to'],
        'awards' => ['Performance awards', 'performance_awards', 'winner_id'],
    ];

    public function fields(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public function label(string $field): string
    {
        return match ($field) {
            'id' => 'Record ID', 'staff_id_number' => 'Staff ID', 'salary' => 'Gross salary (GHS)',
            'start_date' => 'Employment start date', 'created_at' => 'Record created at',
            'momo_number' => 'Mobile money number', 'momo_name' => 'Mobile money name',
            'ssnit_number' => 'SSNIT number', 'line_manager_name' => 'Line manager',
            default => Str::headline($field),
        };
    }

    public function value(User $user, string $field): string
    {
        $value = match ($field) {
            'line_manager_name' => $user->lineManager?->name,
            'department', 'requested_department' => $user->{$field} ? User::departmentLabel($user->{$field}) : null,
            default => $user->{$field},
        };
        if ($value instanceof \DateTimeInterface) {
            return $value->format(in_array($field, ['date_of_birth', 'start_date', 'contract_expires_at', 'id_expires_at']) ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        return $this->text($value);
    }

    public function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : (string) ($value ?? '');
    }

    public function csvRow($handle, array $values): void
    {
        fputcsv($handle, array_map(function ($value) {
            $value = $this->text($value);

            return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value) ? "'".$value : $value;
        }, $values), ',', '"', '', "\r\n");
    }

    public function history(User $user, string $section)
    {
        [, $table, $key] = self::HISTORIES[$section];
        $query = DB::table($table);
        if ($section === 'repayments') {
            $query->whereIn($key, DB::table('salary_advances')->select('id')->where('user_id', $user->id));
        } elseif ($section === 'awards') {
            $query->where(fn ($q) => $q->where('winner_id', $user->id)->orWhere('first_runner_up_id', $user->id)->orWhere('second_runner_up_id', $user->id));
        } else {
            $query->where($key, $user->id);
        }

        return $query->orderByDesc('id');
    }

    public function document(User $user, string $field): ?string
    {
        if (! in_array($field, self::GROUPS['Documents'], true)) {
            return null;
        }
        $relative = preg_replace('#^storage/#', '', (string) $user->{$field});
        if (! $relative || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            return null;
        }
        foreach (['local', 'public'] as $disk) {
            $root = realpath(Storage::disk($disk)->path(''));
            $path = realpath(Storage::disk($disk)->path($relative));
            if ($root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
