<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrStaffImport
{
    public function rules(): array
    {
        $rules = [];
        foreach (['name', 'phone', 'residential_address', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relation', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'momo_number', 'momo_name', 'ssnit_number', 'ghana_card_number', 'passport_number', 'national_id_number', 'national_id_type', 'tshirt_size', 'languages_spoken', 'operational_city', 'job_title', 'position_title'] as $field) {
            $rules[$field] = ['string', 'max:255'];
        }
        $rules['contact_email'] = ['email', 'max:255'];
        $rules['nationality_code'] = ['string', 'size:2'];
        $rules['department'] = [Rule::in(['hr_admin', 'finance', 'client_relations', 'operations_projects', 'brands_marketing', 'creatives'])];
        foreach (['date_of_birth', 'start_date', 'contract_expires_at', 'id_expires_at'] as $field) {
            $rules[$field] = ['date_format:Y-m-d'];
        }
        $rules['date_of_birth'][] = 'before:today';
        foreach (['salary', 'payroll_deductions', 'payroll_rewards_bonus', 'salary_advance_min_monthly_deduction', 'salary_advance_max_amount', 'height'] as $field) {
            $rules[$field] = ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }
        $rules['leave_balance'] = ['integer', 'min:0', 'max:366'];
        $rules['payroll_notes'] = ['string', 'max:5000'];
        $rules['birthday_month'] = ['integer', 'between:1,12'];
        $rules['birthday_day'] = ['integer', 'between:1,31'];

        return $rules;
    }

    public function fingerprint(User $user): string
    {
        return hash('sha256', json_encode($user->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    public function preview(string $path, User $viewer, HrStaffRecord $record): array
    {
        $handle = fopen($path, 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! $headers) {
                throw ValidationException::withMessages(['file' => 'The CSV is empty.']);
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $map = [];
            foreach ($record->fields() as $field) {
                $map[strtolower($record->label($field))] = $field;
                $map[$field] = $field;
            }
            $columns = array_map(fn ($header) => $map[strtolower(trim($header))] ?? null, $headers);
            if (in_array(null, $columns, true) || count($columns) !== count(array_unique($columns))) {
                throw ValidationException::withMessages(['file' => 'The CSV has unknown or duplicate column headings. Use an export or the import template.']);
            }
            if (! array_intersect(['id', 'staff_id_number', 'email'], $columns)) {
                throw ValidationException::withMessages(['file' => 'Include Record ID, Staff ID or Email to identify existing staff.']);
            }
            $editable = $this->rules();
            $ignored = array_values(array_diff($columns, array_keys($editable), ['id', 'staff_id_number', 'email']));
            $rows = [];
            $errors = [];
            $seen = [];
            $number = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $number++;
                if ($number > 1001) {
                    throw ValidationException::withMessages(['file' => 'Import up to 1,000 staff at a time.']);
                }
                if (! array_filter($values, fn ($v) => trim((string) $v) !== '')) {
                    continue;
                }
                if (count($values) !== count($columns)) {
                    $errors[] = "Row {$number}: the number of cells does not match the headings.";

                    continue;
                }
                $data = array_combine($columns, array_map(fn ($v) => trim((string) $v), $values));
                // Undo the safe spreadsheet prefix written by our exporter.
                foreach ($data as &$value) {
                    if (preg_match("/^'[\\s\\x00-\\x1f]*[=+@-]/u", $value)) {
                        $value = substr($value, 1);
                    }
                }
                unset($value);
                $query = User::internalStaff();
                $identified = false;
                foreach (['id', 'staff_id_number', 'email'] as $key) {
                    if (($data[$key] ?? '') !== '') {
                        $query->where($key, $data[$key]);
                        $identified = true;
                    }
                }
                $matches = $identified ? $query->limit(2)->get() : collect();
                if ($matches->count() !== 1) {
                    $errors[] = "Row {$number}: identifiers must match exactly one existing internal staff member. No accounts will be created.";

                    continue;
                }
                $user = $matches->first();
                if (isset($seen[$user->id])) {
                    $errors[] = "Row {$number}: this staff member appears more than once.";

                    continue;
                }
                $seen[$user->id] = true;
                $changes = array_intersect_key(array_filter($data, fn ($v) => $v !== ''), $editable);
                if (isset($changes['department'])) {
                    $changes['department'] = User::normalizeDepartmentKey($changes['department']);
                }
                if (isset($changes['nationality_code'])) {
                    $changes['nationality_code'] = strtoupper($changes['nationality_code']);
                }
                $validator = Validator::make($changes, $editable);
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = "Row {$number}: {$error}";
                    }

                    continue;
                }
                if (isset($changes['date_of_birth'])) {
                    $dob = \Illuminate\Support\Carbon::parse($changes['date_of_birth']);
                    $changes['birthday_month'] = $dob->month;
                    $changes['birthday_day'] = $dob->day;
                } elseif (isset($changes['birthday_month']) || isset($changes['birthday_day'])) {
                    $month = (int) ($changes['birthday_month'] ?? $user->birthday_month);
                    $day = (int) ($changes['birthday_day'] ?? $user->birthday_day);
                    if (! checkdate($month, $day, 2000) || ($user->date_of_birth && ($user->date_of_birth->month !== $month || $user->date_of_birth->day !== $day))) {
                        $errors[] = "Row {$number}: birthday is invalid or conflicts with the recorded date of birth. Import Date Of Birth to change it.";

                        continue;
                    }
                }
                $candidate = clone $user;
                $candidate->fill($changes);
                if ($candidate->hasFullHrAccess() !== $user->hasFullHrAccess() || $candidate->isCvoOrSuperAdmin() !== $user->isCvoOrSuperAdmin() || $candidate->canViewAllPayroll() !== $user->canViewAllPayroll()) {
                    $errors[] = "Row {$number}: this change would alter privileged access. Manage access separately.";

                    continue;
                }
                $updates = [];
                $before = [];
                $after = [];
                foreach ($changes as $field => $value) {
                    if ($record->value($candidate, $field) !== $record->value($user, $field)) {
                        $updates[$field] = $value;
                        $before[$field] = $record->value($user, $field);
                        $after[$field] = $record->value($candidate, $field);
                    }
                }
                $rows[] = ['row' => $number, 'id' => $user->id, 'name' => $user->name, 'updates' => $updates, 'before' => $before, 'after' => $after, 'fingerprint' => $this->fingerprint($user)];
            }
            if (! $rows && ! $errors) {
                $errors[] = 'The CSV has no staff data rows.';
            }

            return ['rows' => $rows, 'errors' => $errors, 'ignored' => $ignored];
        } finally {
            fclose($handle);
        }
    }
}
