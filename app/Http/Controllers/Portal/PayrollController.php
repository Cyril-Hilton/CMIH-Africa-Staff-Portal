<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\User;
use App\Services\GhanaPayrollCalculator;
use App\Mail\StaffPayslipMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    /**
     * Display the payroll and banking configuration view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $salary = $this->enteredSalary($user);

        // Fetch staff personal payslips with pagination & period search filter
        $periodFilter = $request->query('period');
        $hasPayslipsTable = Schema::hasTable('payslips');

        $myPayslips = $hasPayslipsTable
            ? Payslip::where('user_id', $user->id)
                ->when($periodFilter, fn ($q) => $q->where('period', $periodFilter))
                ->latest()
                ->paginate(10)
                ->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        $canViewAllPayroll = $user->canViewAllPayroll();

        $staffPayroll = null;
        if ($canViewAllPayroll) {
            $staffMembers = User::internalStaff()->orderBy('name')->get();
            $staffPayroll = $staffMembers->map(function (User $staff) {
                $calc = GhanaPayrollCalculator::calculate(
                    (float) ($staff->salary ?? 0),
                    (float) ($staff->payroll_deductions ?? 0),
                    (float) ($staff->payroll_rewards_bonus ?? 0)
                );
                return [
                    'user' => $staff,
                    'calculation' => $calc,
                ];
            });
        }

        return view('portal.payroll', compact('user', 'salary', 'myPayslips', 'staffPayroll', 'periodFilter'));
    }

    /**
     * Update user banking and payment details.
     */
    public function updateBanking(Request $request): RedirectResponse
    {
        $request->validate([
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'momo_number' => ['nullable', 'string', 'max:32'],
            'momo_name' => ['nullable', 'string', 'max:255'],
            'ssnit_number' => ['nullable', 'string', 'max:32'],
        ]);

        $user = Auth::user();
        $user->update($request->only([
            'bank_name',
            'bank_branch',
            'bank_account_name',
            'bank_account_number',
            'momo_number',
            'momo_name',
            'ssnit_number',
        ]));

        return back()->with('status', 'Payment and banking details successfully updated.');
    }

    /**
     * HR Action: Update staff salary, bonuses, and contract documents.
     */
    public function updateSalary(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasFullHrAccess(), 403);

        $validated = $request->validate([
            'salary' => ['nullable', 'numeric', 'min:0'],
            'payroll_deductions' => ['nullable', 'numeric', 'min:0'],
            'payroll_rewards_bonus' => ['nullable', 'numeric', 'min:0'],
            'payroll_notes' => ['nullable', 'string', 'max:2000'],
            'contract' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'job_description' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $updates = [
            'salary' => filled($validated['salary'] ?? null) ? $validated['salary'] : 0,
            'payroll_deductions' => filled($validated['payroll_deductions'] ?? null) ? $validated['payroll_deductions'] : null,
            'payroll_rewards_bonus' => filled($validated['payroll_rewards_bonus'] ?? null) ? $validated['payroll_rewards_bonus'] : null,
            'payroll_notes' => $validated['payroll_notes'] ?? null,
        ];

        if ($request->hasFile('contract')) {
            if ($user->contract_path) {
                Storage::disk('local')->delete($user->contract_path);
                Storage::disk('public')->delete($user->contract_path);
            }
            $updates['contract_path'] = $request->file('contract')->store('contracts', 'local');
        }

        if ($request->hasFile('job_description')) {
            if ($user->job_description_path) {
                Storage::disk('local')->delete($user->job_description_path);
                Storage::disk('public')->delete($user->job_description_path);
            }
            $updates['job_description_path'] = $request->file('job_description')->store('job_descriptions', 'local');
        }

        $user->update($updates);

        return back()->with('status', 'Payroll details updated for '.$user->name.'.');
    }

    /**
     * HR Action: Generate and email payslips to all active internal staff.
     */
    public function distributePayslips(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canViewAllPayroll(), 403);

        $period = $request->input('period', now()->format('Y-m'));

        $activeStaff = User::internalStaff()->where('status', 'active')->get();
        $count = 0;

        foreach ($activeStaff as $staff) {
            $gross = (float) ($staff->salary ?? 0);
            $deductions = (float) ($staff->payroll_deductions ?? 0);
            $bonuses = (float) ($staff->payroll_rewards_bonus ?? 0);

            $calc = GhanaPayrollCalculator::calculate($gross, $deductions, $bonuses);

            $payslip = Payslip::updateOrCreate(
                [
                    'user_id' => $staff->id,
                    'period' => $period,
                ],
                [
                    'gross_salary' => $calc['gross_salary'],
                    'ssnit_employee' => $calc['ssnit_employee'],
                    'ssnit_employer' => $calc['ssnit_employer'],
                    'paye_tax' => $calc['paye_tax'],
                    'other_deductions' => $calc['other_deductions'],
                    'bonuses' => $calc['bonuses'],
                    'net_salary' => $calc['net_salary'],
                    'bank_name' => $staff->bank_name,
                    'account_number' => $staff->bank_account_number,
                    'momo_number' => $staff->momo_number,
                    'issued_at' => now(),
                    'issued_by' => $request->user()->id,
                ]
            );

            // Send Email to recipient primary email and contact email
            $recipientEmails = array_unique(array_filter([$staff->contact_email, $staff->email]));
            if (! empty($recipientEmails)) {
                try {
                    Mail::to($recipientEmails)->send(new StaffPayslipMail($payslip, $staff));
                } catch (\Exception $e) {
                    // Log error gracefully without breaking loop
                    logger()->error("Failed sending payslip mail to {$staff->name}: " . $e->getMessage());
                }
            }

            $count++;
        }

        $periodLabel = Carbon::createFromFormat('!Y-m', $period)->format('F Y');
        return back()->with('status', "🎉 Success! Monthly payslips generated & emailed to {$count} staff members for {$periodLabel}.");
    }

    /**
     * Download or view a single payslip document
     */
    public function downloadPayslip(Request $request, Payslip $payslip)
    {
        $viewer = $request->user();
        abort_unless((int) $viewer->id === (int) $payslip->user_id || $viewer->canViewAllPayroll(), 403);

        $staff = $payslip->user ?: $viewer;

        return view('emails.staff-payslip', [
            'payslip' => $payslip,
            'staff' => $staff,
        ]);
    }

    /**
     * HR Action: Export Staff Directory to CSV / Excel
     */
    public function exportStaffDirectory(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canViewAllPayroll(), 403);

        $staffMembers = User::internalStaff()->orderBy('name')->get();
        $fileName = 'cmih_staff_directory_' . date('Y-m-d') . '.csv';

        $response = new StreamedResponse(function () use ($staffMembers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Staff ID', 'Full Name', 'Primary Email', 'Contact Email', 'Phone Number',
                'Date of Birth', 'Age', 'Department', 'Position Title', 'Job Level',
                'Access Role', 'Status', 'Hire Date', 'SSNIT Number', 'Bank Name',
                'Bank Branch', 'Bank Account Name', 'Bank Account Number', 'MoMo Number',
                'Gross Salary (GHS)', 'Emergency Contact Name', 'Emergency Contact Phone'
            ]);

            foreach ($staffMembers as $staff) {
                $dob = $staff->date_of_birth ? Carbon::parse($staff->date_of_birth)->format('Y-m-d') : ($staff->birthday_month && $staff->birthday_day ? "Month: {$staff->birthday_month}, Day: {$staff->birthday_day}" : 'N/A');
                $age = $staff->date_of_birth ? Carbon::parse($staff->date_of_birth)->age : 'N/A';

                fputcsv($handle, [
                    $staff->id,
                    $staff->name,
                    $staff->email,
                    $staff->contact_email ?: $staff->email,
                    $staff->phone_number ?: 'N/A',
                    $dob,
                    $age,
                    User::departmentLabel($staff->department),
                    $staff->position_title ?: 'N/A',
                    $staff->job_level ?: 'N/A',
                    $staff->access_role ?: 'staff',
                    ucfirst($staff->status ?: 'active'),
                    $staff->created_at ? $staff->created_at->format('Y-m-d') : 'N/A',
                    $staff->ssnit_number ?: 'N/A',
                    $staff->bank_name ?: 'N/A',
                    $staff->bank_branch ?: 'N/A',
                    $staff->bank_account_name ?: 'N/A',
                    $staff->bank_account_number ?: 'N/A',
                    $staff->momo_number ?: 'N/A',
                    number_format((float) ($staff->salary ?? 0), 2, '.', ''),
                    $staff->emergency_contact_name ?: 'N/A',
                    $staff->emergency_contact_phone ?: 'N/A',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        return $response;
    }

    /**
     * HR Action: Export Monthly Payroll Register to CSV / Excel
     */
    public function exportPayrollRegister(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canViewAllPayroll(), 403);

        $staffMembers = User::internalStaff()->orderBy('name')->get();
        $fileName = 'cmih_payroll_register_' . date('Y-m') . '.csv';

        $response = new StreamedResponse(function () use ($staffMembers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Staff ID', 'Full Name', 'Department', 'Bank Name', 'Account Number', 'MoMo Number',
                'Gross Base Salary (GHS)', 'SSNIT Employee 5.5% (GHS)', 'SSNIT Employer 13% (GHS)',
                'Taxable Income (GHS)', 'GRA PAYE Tax (GHS)', 'Deductions (GHS)', 'Bonuses (GHS)', 'Net Pay (GHS)'
            ]);

            foreach ($staffMembers as $staff) {
                $calc = GhanaPayrollCalculator::calculate(
                    (float) ($staff->salary ?? 0),
                    (float) ($staff->payroll_deductions ?? 0),
                    (float) ($staff->payroll_rewards_bonus ?? 0)
                );

                fputcsv($handle, [
                    $staff->id,
                    $staff->name,
                    User::departmentLabel($staff->department),
                    $staff->bank_name ?: 'N/A',
                    $staff->bank_account_number ?: 'N/A',
                    $staff->momo_number ?: 'N/A',
                    number_format($calc['gross_salary'], 2, '.', ''),
                    number_format($calc['ssnit_employee'], 2, '.', ''),
                    number_format($calc['ssnit_employer'], 2, '.', ''),
                    number_format($calc['taxable_income'], 2, '.', ''),
                    number_format($calc['paye_tax'], 2, '.', ''),
                    number_format($calc['other_deductions'], 2, '.', ''),
                    number_format($calc['bonuses'], 2, '.', ''),
                    number_format($calc['net_salary'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        return $response;
    }

    public function downloadDocument(Request $request, User $user, string $type)
    {
        $fields = [
            'contract' => 'contract_path',
            'job-description' => 'job_description_path',
            'national-id-front' => 'national_id_front_path',
            'national-id-back' => 'national_id_back_path',
            'passport-document' => 'passport_photo_path',
            'ghana-card-front' => 'ghana_card_front_path',
            'ghana-card-back' => 'ghana_card_back_path',
            'passport-photo' => 'passport_photo_path',
        ];

        if (! isset($fields[$type])) {
            abort(404);
        }

        $viewer = $request->user();
        if ((int) $viewer->id !== (int) $user->id) {
            $identityTypes = [
                'national-id-front',
                'national-id-back',
                'passport-document',
                'ghana-card-front',
                'ghana-card-back',
                'passport-photo',
            ];
            $canDownload = in_array($type, $identityTypes, true)
                ? $viewer->canReviewIdentityDocuments()
                : $viewer->hasFullHrAccess();

            abort_unless($canDownload, 403);
        }

        $documentPath = match ($type) {
            'national-id-front' => $user->nationalIdFrontDocumentPath(),
            'national-id-back' => $user->nationalIdBackDocumentPath(),
            'ghana-card-front' => $user->ghana_card_front_path ?: $user->ghana_card_path,
            default => $user->{$fields[$type]},
        };

        $path = ltrim(str_replace('\\', '/', (string) $documentPath), '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $name = str($user->name)->slug()->append('-'.$type.'.'.pathinfo($path, PATHINFO_EXTENSION))->toString();

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $name);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $name);
        }

        abort(404);
    }

    private function enteredSalary(User $user): ?float
    {
        $salary = $user->salary;

        return $salary !== null && (float) $salary > 0 ? (float) $salary : null;
    }
}
