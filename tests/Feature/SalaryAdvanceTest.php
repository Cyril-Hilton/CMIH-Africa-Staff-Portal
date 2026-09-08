<?php

namespace Tests\Feature;

use App\Models\SalaryAdvance;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteContent::forgetCachedValues();
    }

    public function test_user_can_submit_salary_advance_to_hr_successfully_with_deduction(): void
    {
        $user = $this->staffUser(['salary' => 4500]);
        $hrManager = $this->hrManager();
        $financeUser = $this->financeUser();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($user)->post(route('portal.finance.advances.store'), [
            'amount' => 8000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 1000,
            'reason' => 'Need money for school fees.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $user->id,
            'amount' => 8000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 1000,
            'reason' => 'Need money for school fees.',
            'status' => 'pending_hr',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $hrManager->id,
            'title' => 'Salary Advance HR Review Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'Salary Advance HR Review Needed',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $financeUser->id,
            'title' => 'Salary Advance HR Review Needed',
        ]);
    }

    public function test_user_cannot_exceed_double_monthly_salary(): void
    {
        $user = $this->staffUser(['salary' => 4500]);

        $response = $this->actingAs($user)->post(route('portal.finance.advances.store'), [
            'amount' => 9001,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 1000,
            'reason' => 'Over limit.',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertDatabaseMissing('salary_advances', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_repayment_style_monthly_deduction_uses_default_minimum_500(): void
    {
        $user = $this->staffUser(['salary' => 4500]);

        $response = $this->actingAs($user)->post(route('portal.finance.advances.store'), [
            'amount' => 5000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 499,
            'reason' => 'Below default monthly deduction.',
        ]);

        $response->assertSessionHasErrors(['monthly_deduction_amount']);
        $this->assertDatabaseMissing('salary_advances', [
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('portal.finance.advances.store'), [
            'amount' => 5000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 500,
            'reason' => 'Accepted at default monthly deduction.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $user->id,
            'monthly_deduction_amount' => 500,
            'status' => 'pending_hr',
        ]);
    }

    public function test_hr_manager_can_change_salary_advance_default_minimum(): void
    {
        $hrManager = $this->hrManager();

        $response = $this->actingAs($hrManager)->post(route('portal.hr.salary-advance-settings.update'), [
            'default_min_monthly_deduction' => 650,
            'default_max_salary_multiplier' => 2,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_contents', [
            'key' => 'salary_advance_default_monthly_deduction_minimum',
            'value' => '650.00',
            'type' => 'money',
            'updated_by' => $hrManager->id,
        ]);
    }

    public function test_hr_manager_can_set_staff_specific_salary_advance_minimum(): void
    {
        $hrManager = $this->hrManager();
        $staff = $this->staffUser([
            'salary' => 4500,
        ]);

        $response = $this->actingAs($hrManager)->post(route('portal.hr.salary-advance-minimum.update', $staff), [
            'min_monthly_deduction' => 300,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('300.00', $staff->refresh()->salary_advance_min_monthly_deduction);

        $lowResponse = $this->actingAs($staff)->post(route('portal.finance.advances.store'), [
            'amount' => 3000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 299,
            'reason' => 'Below agreed terms.',
        ]);

        $lowResponse->assertSessionHasErrors(['monthly_deduction_amount']);

        $acceptedResponse = $this->actingAs($staff)->post(route('portal.finance.advances.store'), [
            'amount' => 3000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 300,
            'reason' => 'At agreed terms.',
        ]);

        $acceptedResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $staff->id,
            'monthly_deduction_amount' => 300,
            'status' => 'pending_hr',
        ]);
    }

    public function test_pay_all_at_once_does_not_require_monthly_installments(): void
    {
        $user = $this->staffUser(['salary' => 4500]);

        SiteContent::create([
            'key' => 'salary_advance_default_monthly_deduction_minimum',
            'value' => '800.00',
            'type' => 'money',
        ]);

        $response = $this->actingAs($user)->post(route('portal.finance.advances.store'), [
            'amount' => 3000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'I will pay this at once.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $user->id,
            'repayment_style' => 'pay_all_at_once',
            'monthly_deduction_amount' => null,
            'status' => 'pending_hr',
        ]);
    }

    public function test_staff_finance_advance_page_shows_effective_monthly_minimum(): void
    {
        $user = $this->staffUser([
            'salary' => 4500,
            'salary_advance_min_monthly_deduction' => 375,
        ]);

        $response = $this->actingAs($user)->get(route('portal.finance.advances.index'));

        $response->assertOk();
        $response->assertSee('Minimum for your current HR terms');
        $response->assertSee('GHC 375.00');
        $response->assertSee('min="375.00"', false);
    }

    public function test_hr_page_shows_salary_advance_terms_controls_and_loan_manager(): void
    {
        $hrManager = $this->hrManager();
        $staff = $this->staffUser([
            'salary' => 2500,
            'salary_advance_min_monthly_deduction' => 450,
        ]);

        SalaryAdvance::create([
            'user_id' => $staff->id,
            'amount' => 1200,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 500,
            'reason' => 'Medical bills.',
            'status' => 'pending_hr',
        ]);

        $response = $this->actingAs($hrManager)->get(route('portal.hr'));

        $response->assertOk();
        $response->assertSee('Salary Advance Installment Terms');
        $response->assertSee('Staff Loan Manager');
        $response->assertSee('Default Installment Min');
        $response->assertSee('GHC 500.00');
        $response->assertSee($staff->name);
        $response->assertSee('450.00');
        $response->assertSee('Medical bills.');
    }

    public function test_hr_manager_approves_terms_before_finance_can_process(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $hrManager = $this->hrManager();
        $financeUser = $this->financeUser();
        $advance = $this->pendingHrAdvance($staff, [
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 600,
        ]);

        $response = $this->actingAs($hrManager)->post(route('portal.hr.salary-advances.action', $advance), [
            'action' => 'approve',
            'approved_monthly_deduction_amount' => 750,
            'repayment_start_date' => '2026-10-01',
            'repayment_months' => 4,
            'feedback' => 'Agreed with staff.',
        ]);

        $response->assertSessionHasNoErrors();

        $advance->refresh();
        $this->assertSame('pending_finance', $advance->status);
        $this->assertSame($hrManager->id, $advance->hr_reviewed_by);
        $this->assertSame(750.0, $advance->approved_monthly_deduction_amount);
        $this->assertSame('2026-10-01', $advance->repayment_start_date->toDateString());
        $this->assertSame(4, (int) $advance->repayment_months);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $financeUser->id,
            'title' => 'Salary Advance Finance Processing Needed',
        ]);
    }

    public function test_finance_cannot_approve_before_hr_review(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $financeUser = $this->financeUser();
        $advance = $this->pendingHrAdvance($staff);

        $response = $this->actingAs($financeUser)->post(route('portal.finance.advances.finance-action', $advance), [
            'action' => 'approve_and_disburse',
            'disbursed_amount' => 3000,
        ]);

        $response->assertSessionHasErrors(['action']);
        $this->assertSame('pending_hr', $advance->refresh()->status);
        $this->assertNull($advance->disbursed_at);
    }

    public function test_finance_can_approve_and_disburse_after_hr_without_cvo(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $hrManager = $this->hrManager();
        $financeUser = $this->financeUser();
        $advance = $this->pendingHrAdvance($staff, [
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 600,
        ]);

        $this->actingAs($hrManager)->post(route('portal.hr.salary-advances.action', $advance), [
            'action' => 'approve',
            'approved_monthly_deduction_amount' => 650,
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($financeUser)->post(route('portal.finance.advances.finance-action', $advance), [
            'action' => 'approve_and_disburse',
            'approved_monthly_deduction_amount' => 650,
            'disbursed_amount' => 3000,
            'repayment_start_date' => '2026-10-01',
        ]);

        $response->assertSessionHasNoErrors();

        $advance->refresh();
        $this->assertSame('repayment_active', $advance->status);
        $this->assertSame($financeUser->id, $advance->finance_reviewed_by);
        $this->assertSame($financeUser->id, $advance->approved_by);
        $this->assertSame($financeUser->id, $advance->disbursed_by);
        $this->assertSame(3000.0, $advance->disbursed_amount);
        $this->assertSame(650.0, $advance->approved_monthly_deduction_amount);
        $this->assertSame('2026-10-01', $advance->repayment_start_date->toDateString());
    }

    public function test_optional_cvo_route_returns_loan_to_finance_for_payment(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $hrManager = $this->hrManager();
        $financeUser = $this->financeUser();
        $cvoUser = $this->superAdmin(['position_title' => 'CVO']);
        $advance = $this->pendingHrAdvance($staff, [
            'repayment_style' => 'pay_all_at_once',
            'monthly_deduction_amount' => null,
        ]);

        $this->actingAs($hrManager)->post(route('portal.hr.salary-advances.action', $advance), [
            'action' => 'approve',
        ])->assertSessionHasNoErrors();

        $this->actingAs($financeUser)->post(route('portal.finance.advances.finance-action', $advance), [
            'action' => 'verify',
        ])->assertSessionHasNoErrors();

        $this->assertSame('pending_cvo', $advance->refresh()->status);

        $response = $this->actingAs($cvoUser)->post(route('portal.finance.advances.cvo-action', $advance), [
            'action' => 'approve',
        ]);

        $response->assertSessionHasNoErrors();
        $advance->refresh();
        $this->assertSame('pending_finance', $advance->status);
        $this->assertSame($cvoUser->id, $advance->approved_by);
        $this->assertSame($cvoUser->id, $advance->cvo_reviewed_by);
    }

    public function test_finance_repayment_tracking_updates_balance_and_marks_fully_paid(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $financeUser = $this->financeUser();
        $advance = SalaryAdvance::create([
            'user_id' => $staff->id,
            'amount' => 3000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'Advance.',
            'status' => 'repayment_active',
            'finance_reviewed_by' => $financeUser->id,
            'finance_reviewed_at' => now(),
            'approved_by' => $financeUser->id,
            'approved_at' => now(),
            'disbursed_by' => $financeUser->id,
            'disbursed_at' => now(),
            'disbursed_amount' => 3000,
        ]);

        $this->actingAs($financeUser)->post(route('portal.finance.advances.repayments.store', $advance), [
            'amount' => 1000,
            'payment_date' => '2026-10-31',
            'payment_method' => 'Payroll deduction',
            'reference' => 'OCT-DED-001',
        ])->assertSessionHasNoErrors();

        $advance->refresh()->load('repayments');
        $this->assertSame('repayment_active', $advance->status);
        $this->assertSame(1000.0, $advance->totalRepaid());
        $this->assertSame(2000.0, $advance->balance());

        $this->actingAs($financeUser)->post(route('portal.finance.advances.repayments.store', $advance), [
            'amount' => 2000,
            'payment_date' => '2026-11-30',
            'payment_method' => 'Payroll deduction',
            'reference' => 'NOV-DED-001',
        ])->assertSessionHasNoErrors();

        $advance->refresh()->load('repayments');
        $this->assertSame('fully_paid', $advance->status);
        $this->assertSame(3000.0, $advance->totalRepaid());
        $this->assertSame(0.0, $advance->balance());
        $this->assertNotNull($advance->fully_paid_at);
    }

    public function test_hr_correction_flow_and_resubmission_returns_to_hr(): void
    {
        $staff = $this->staffUser(['salary' => 4500]);
        $hrManager = $this->hrManager();
        $advance = $this->pendingHrAdvance($staff);

        $responseCorrection = $this->actingAs($hrManager)->post(route('portal.hr.salary-advances.action', $advance), [
            'action' => 'correction',
            'feedback' => 'Please provide a better reason.',
        ]);

        $responseCorrection->assertSessionHasNoErrors();
        $this->assertSame('returned_for_correction', $advance->refresh()->status);
        $this->assertSame('Please provide a better reason.', $advance->hr_feedback);

        $responseResubmit = $this->actingAs($staff)->post(route('portal.finance.advances.resubmit', $advance), [
            'amount' => 4000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'Family emergency bills.',
        ]);

        $responseResubmit->assertSessionHasNoErrors();
        $advance->refresh();
        $this->assertSame('pending_hr', $advance->status);
        $this->assertNull($advance->hr_feedback);
        $this->assertNull($advance->finance_feedback);
    }

    public function test_hr_manager_with_title_variation_receives_notification_and_has_access(): void
    {
        $staff = $this->staffUser(['salary' => 5000]);
        $hrLead = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'Human Resources',
            'position_title' => 'Human Resource Manager',
            'job_title' => 'HR Lead',
        ]);

        $this->assertTrue($hrLead->hasFullHrAccess());

        $response = $this->actingAs($staff)->post(route('portal.finance.advances.store'), [
            'amount' => 4000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 800,
            'reason' => 'Family emergency.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hrLead->id,
            'title' => 'Salary Advance HR Review Needed',
        ]);
    }

    public function test_merchandiser_salary_advance_notifies_brands_team_in_admin_hub(): void
    {
        $merchandiser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'merchandiser',
            'salary' => 3000,
        ]);
        $brandsTeamUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
            'department' => 'brands',
            'job_level' => 'manager',
        ]);

        $response = $this->actingAs($merchandiser)->post(route('merchandisers.loans.store'), [
            'amount' => 2000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 500,
            'reason' => 'Tool kit purchase.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $merchandiser->id,
            'amount' => 2000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $brandsTeamUser->id,
            'title' => 'Merchandiser Salary Advance Approval Needed',
        ]);
    }

    public function test_employee_payout_cap_is_enforced_and_can_be_reset_to_policy(): void
    {
        $hr = $this->hrManager();
        $staff = $this->staffUser(['salary' => 4500]);
        $this->actingAs($hr)->post(route('portal.hr.salary-advance-minimum.update', $staff), [
            'min_monthly_deduction' => 250, 'max_advance_amount' => 1200,
        ])->assertSessionHasNoErrors();
        $payload = ['amount' => 1201, 'repayment_style' => 'pay_all_at_once', 'reason' => 'Cap test'];
        $this->actingAs($staff->fresh())->post(route('portal.finance.advances.store'), $payload)->assertSessionHasErrors('amount');
        $payload['amount'] = 1200;
        $this->post(route('portal.finance.advances.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($hr)->post(route('portal.hr.salary-advance-minimum.update', $staff), [
            'min_monthly_deduction' => null, 'max_advance_amount' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->salary_advance_max_amount);
        $this->assertSame(9000.0, \App\Support\SalaryAdvancePolicy::effectiveMaximumAmount($staff->fresh()));
    }

    public function test_paid_loan_cannot_be_resubmitted_and_have_approvals_erased(): void
    {
        $staff = $this->staffUser();
        $loan = $this->pendingHrAdvance($staff, ['status' => 'repayment_active', 'disbursed_at' => now(), 'disbursed_amount' => 3000]);
        $this->actingAs($staff)->post(route('portal.finance.advances.resubmit', $loan), [
            'amount' => 1000, 'repayment_style' => 'pay_all_at_once', 'reason' => 'Overwrite paid loan',
        ]);
        $this->assertSame('repayment_active', $loan->fresh()->status);
        $this->assertSame(3000.0, (float) $loan->fresh()->amount);
    }

    public function test_hr_assistant_does_not_receive_manager_approval_authority(): void
    {
        $assistant = $this->staffUser(['department' => 'hr_admin', 'position_title' => 'HR Assistant', 'job_title' => 'HR Assistant', 'job_level' => 'assistant']);
        $this->assertFalse($assistant->hasFullHrAccess());
    }

    public function test_brands_team_cannot_approve_internal_staff_loan(): void
    {
        $staff = $this->staffUser();
        $loan = $this->pendingHrAdvance($staff);
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('merchandisers.admin.loans.approve', $loan));
        $this->assertSame('pending_hr', $loan->fresh()->status);
    }

    private function staffUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ], $overrides));
    }

    private function hrManager(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'HR Manager',
        ], $overrides));
    }

    private function financeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'finance',
        ], $overrides));
    }

    private function superAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'access_role' => 'super_admin',
        ], $overrides));
    }

    private function pendingHrAdvance(User $staff, array $overrides = []): SalaryAdvance
    {
        return SalaryAdvance::create(array_merge([
            'user_id' => $staff->id,
            'amount' => 3000,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 500,
            'reason' => 'Advance.',
            'status' => 'pending_hr',
        ], $overrides));
    }
}
