<?php

namespace Tests\Feature;

use App\Mail\StaffPayslipMail;
use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollAndSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_payroll_page(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
        ]);

        $response = $this->actingAs($user)->get('/portal/payroll');

        $response->assertStatus(200);
        $response->assertSee('Payroll & Banking', false);
        $response->assertSee('Not set', false);
    }

    public function test_full_hr_access_user_can_update_payroll_details(): void
    {
        Storage::fake('local');

        $hr = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'HR Admin',
            'position_title' => 'HR Manager',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'salary' => 0,
        ]);

        $response = $this->actingAs($hr)->post(route('portal.payroll.salary', $staff), [
            'salary' => '5200.50',
            'payroll_deductions' => '100.25',
            'payroll_rewards_bonus' => '300',
            'payroll_notes' => 'Confirmed by HR.',
            'contract' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
            'job_description' => UploadedFile::fake()->create('job-description.pdf', 64, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame(5200.50, (float) $staff->salary);
        $this->assertSame(100.25, (float) $staff->payroll_deductions);
        $this->assertSame(300.0, (float) $staff->payroll_rewards_bonus);
        $this->assertSame('Confirmed by HR.', $staff->payroll_notes);
        $this->assertNotNull($staff->contract_path);
        $this->assertNotNull($staff->job_description_path);
        Storage::disk('local')->assertExists($staff->contract_path);
        Storage::disk('local')->assertExists($staff->job_description_path);
    }

    public function test_regular_staff_cannot_update_payroll_details(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $target = User::factory()->create([
            'status' => 'active',
            'salary' => 0,
        ]);

        $response = $this->actingAs($staff)->post(route('portal.payroll.salary', $target), [
            'salary' => '9000',
        ]);

        $response->assertForbidden();
        $this->assertSame(0.0, (float) $target->fresh()->salary);
    }

    public function test_hr_can_issue_payslip_to_selected_staff_for_selected_month(): void
    {
        Mail::fake();

        $hr = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'HR Admin',
            'position_title' => 'HR Manager',
        ]);
        $selectedStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'email' => 'selected@cmih.africa',
            'salary' => 5000,
        ]);
        $otherStaff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'email' => 'other@cmih.africa',
            'salary' => 5000,
        ]);

        $response = $this->actingAs($hr)->post(route('portal.payroll.distribute'), [
            'period' => '2026-07',
            'recipient_ids' => [$selectedStaff->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payslips', [
            'user_id' => $selectedStaff->id,
            'period' => '2026-07',
        ]);
        $this->assertDatabaseMissing('payslips', [
            'user_id' => $otherStaff->id,
            'period' => '2026-07',
        ]);

        Mail::assertSent(StaffPayslipMail::class, 1);
        Mail::assertSent(StaffPayslipMail::class, fn (StaffPayslipMail $mail) => (int) $mail->staff->id === (int) $selectedStaff->id);
    }

    public function test_user_can_update_banking_details(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $response = $this->actingAs($user)->post('/portal/payroll/banking', [
            'bank_name' => 'Fidelity Bank',
            'bank_branch' => 'Ridge',
            'bank_account_name' => 'John Doe CMIH',
            'bank_account_number' => '10903829103',
            'momo_number' => '0244123456',
            'momo_name' => 'John Momo',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bank_name' => 'Fidelity Bank',
            'bank_branch' => 'Ridge',
            'bank_account_name' => 'John Doe CMIH',
            'bank_account_number' => '10903829103',
            'momo_number' => '0244123456',
            'momo_name' => 'John Momo',
        ]);
    }

    public function test_user_can_filter_pending_tasks(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        // Create one pending and one completed task
        $pendingTask = Task::create([
            'title' => 'Pending Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'Medium',
        ]);

        $completedTask = Task::create([
            'title' => 'Completed Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Completed',
            'priority' => 'Medium',
        ]);

        // Standard access shows both
        $responseAll = $this->actingAs($user)->get('/portal/tasks');
        $responseAll->assertSee('Pending Task');
        $responseAll->assertSee('Completed Task');

        // Filter pending shows only the pending one
        $responsePending = $this->actingAs($user)->get('/portal/tasks?filter=pending');
        $responsePending->assertSee('Pending Task');
        $responsePending->assertDontSee('Completed Task');
    }

    public function test_user_can_filter_overdue_tasks_and_sort_pending_by_due_date(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        Task::create([
            'title' => 'Old Pending Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'due_on' => now()->subDays(3),
        ]);

        Task::create([
            'title' => 'Future Pending Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Open',
            'priority' => 'High',
            'due_on' => now()->addDays(4),
        ]);

        Task::create([
            'title' => 'Completed Past Task',
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'department' => 'operations_projects',
            'status' => 'Completed',
            'priority' => 'Low',
            'due_on' => now()->subDays(6),
        ]);

        $overdue = $this->actingAs($user)->get('/portal/tasks?filter=overdue');
        $overdue->assertOk();
        $overdue->assertSee('Old Pending Task');
        $overdue->assertDontSee('Future Pending Task');
        $overdue->assertDontSee('Completed Past Task');

        $pendingByDueDate = $this->actingAs($user)->get('/portal/tasks?filter=pending&sort=due&direction=asc');
        $pendingByDueDate->assertOk();
        $pendingByDueDate->assertSeeInOrder(['Old Pending Task', 'Future Pending Task']);
        $pendingByDueDate->assertDontSee('Completed Past Task');
    }
}
