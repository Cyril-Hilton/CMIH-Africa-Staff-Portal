<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SalaryAdvance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryAdvanceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_repayment_style_monthly_deduction_requires_minimum_1000()
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
            'monthly_deduction_amount' => 999,
            'reason' => 'Low monthly deduction.',
        ]);

        $response->assertSessionHasErrors(['monthly_deduction_amount']);
        $this->assertDatabaseMissing('salary_advances', [
            'user_id' => $user->id,
        ]);
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
