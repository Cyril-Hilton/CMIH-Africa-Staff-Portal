<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SalaryAdvance;
use App\Models\SiteContent;
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

    public function test_user_can_submit_salary_advance_successfully_with_deduction()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

        $financeUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'finance',
        ]);

        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

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
            'status' => 'pending_finance',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $financeUser->id,
            'title' => 'Salary Advance Verification Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'Salary Advance Verification Needed',
        ]);
    }

    public function test_user_cannot_exceed_double_monthly_salary()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

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

    public function test_user_repayment_style_monthly_deduction_uses_default_minimum_500()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

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
            'status' => 'pending_finance',
        ]);
    }

    public function test_hr_manager_can_change_salary_advance_default_minimum()
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'HR Manager',
        ]);

        $response = $this->actingAs($hrManager)->post(route('portal.hr.salary-advance-settings.update'), [
            'default_min_monthly_deduction' => 650,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_contents', [
            'key' => 'salary_advance_default_monthly_deduction_minimum',
            'value' => '650.00',
            'type' => 'money',
            'updated_by' => $hrManager->id,
        ]);
    }

    public function test_hr_manager_can_set_staff_specific_salary_advance_minimum()
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'HR Manager',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
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
        ]);
    }

    public function test_pay_all_at_once_does_not_require_monthly_installments()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

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
        ]);
    }

    public function test_staff_finance_advance_page_shows_effective_monthly_minimum()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
            'salary_advance_min_monthly_deduction' => 375,
        ]);

        $response = $this->actingAs($user)->get(route('portal.finance.advances.index'));

        $response->assertOk();
        $response->assertSee('Minimum for your current HR terms');
        $response->assertSee('GHC 375.00');
        $response->assertSee('min="375.00"', false);
    }

    public function test_hr_page_shows_salary_advance_terms_controls()
    {
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'hr_admin',
            'job_level' => 'manager',
            'position_title' => 'HR Manager',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 2500,
            'salary_advance_min_monthly_deduction' => 450,
        ]);

        $response = $this->actingAs($hrManager)->get(route('portal.hr'));

        $response->assertOk();
        $response->assertSee('Salary Advance Installment Terms');
        $response->assertSee('Current Default');
        $response->assertSee('GHC 500.00');
        $response->assertSee($staff->name);
        $response->assertSee('450.00');
    }

    public function test_full_workflow_finance_verification_to_cvo_approval()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

        $financeUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'finance',
        ]);

        $cvoUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
            'position_title' => 'CVO',
        ]);

        // 1. Create a pending finance advance
        $advance = SalaryAdvance::create([
            'user_id' => $user->id,
            'amount' => 3000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'Advance.',
            'status' => 'pending_finance',
        ]);

        // 2. Finance verify the advance
        $responseFinance = $this->actingAs($financeUser)->post(route('portal.finance.advances.finance-action', $advance), [
            'action' => 'verify',
        ]);

        $responseFinance->assertSessionHasNoErrors();
        $this->assertEquals('pending_cvo', $advance->refresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $cvoUser->id,
            'title' => 'Salary Advance Approval Needed',
        ]);

        // 3. CVO approves the advance
        $responseCvo = $this->actingAs($cvoUser)->post(route('portal.finance.advances.cvo-action', $advance), [
            'action' => 'approve',
        ]);

        $responseCvo->assertSessionHasNoErrors();
        $this->assertEquals('approved', $advance->refresh()->status);
    }

    public function test_finance_correction_flow_and_resubmission()
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'salary' => 4500,
        ]);

        $financeUser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'finance',
        ]);

        // 1. Create a pending advance
        $advance = SalaryAdvance::create([
            'user_id' => $user->id,
            'amount' => 4000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'Advance.',
            'status' => 'pending_finance',
        ]);

        // 2. Finance requests correction
        $responseCorrection = $this->actingAs($financeUser)->post(route('portal.finance.advances.finance-action', $advance), [
            'action' => 'correction',
            'feedback' => 'Please provide a better reason.',
        ]);

        $responseCorrection->assertSessionHasNoErrors();
        $this->assertEquals('returned_for_correction', $advance->refresh()->status);
        $this->assertEquals('Please provide a better reason.', $advance->finance_feedback);

        // 3. User resubmits corrected advance
        $responseResubmit = $this->actingAs($user)->post(route('portal.finance.advances.resubmit', $advance), [
            'amount' => 4000,
            'repayment_style' => 'pay_all_at_once',
            'reason' => 'Family emergency bills.',
        ]);

        $responseResubmit->assertSessionHasNoErrors();
        $this->assertEquals('pending_finance', $advance->refresh()->status);
        $this->assertNull($advance->finance_feedback);
    }
}
