<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\ProjectBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrFinanceAuditAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_exposes_existing_hr_and_finance_records_without_approval_permissions(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/audit-test.pdf', 'receipt');
        $auditor = User::factory()->create([
            'status' => 'active', 'department' => 'executive', 'access_role' => 'staff',
            'permissions_matrix' => [User::HR_FINANCE_AUDIT_PERMISSION, User::EXECUTIVE_ACTIVITY_PERMISSION],
        ]);
        $staff = User::factory()->create(['status' => 'active', 'department' => 'operations_projects', 'salary' => 45781]);
        $claim = PettyCashClaim::create([
            'user_id' => $staff->id, 'amount' => 100, 'currency' => 'GH₵',
            'description' => 'Confidential fixture claim', 'status' => 'Submitted to CVO', 'receipt_path' => 'receipts/audit-test.pdf',
        ]);
        $budget = ProjectBudget::create([
            'created_by' => $staff->id, 'title' => 'Confidential fixture budget', 'status' => 'Submitted to CVO',
            'total_amount' => 200, 'currency' => 'GH₵',
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $staff->id, 'leave_type' => 'Annual', 'start_date' => '2026-11-19', 'end_date' => '2026-11-20',
            'status' => 'pending_hr', 'comments' => 'Confidential fixture leave',
        ]);
        $appraisal = Appraisal::create([
            'user_id' => $staff->id, 'quarter' => 'Q4', 'year' => 2026, 'status' => 'manager_reviewed',
            'manager_review' => ['comments' => 'Confidential fixture appraisal'],
        ]);

        $this->actingAs($auditor);
        $this->get(route('portal.hr'))->assertOk()->assertSee('Read-only HR audit')
            ->assertDontSee(route('portal.hr.leave-balance.update', $staff), false);
        $this->get(route('portal.hr.staff.index'))->assertOk()->assertSee($staff->name)->assertDontSee('Export staff information');
        $this->get(route('portal.hr.staff.show', $staff))->assertOk()->assertSee('45781');
        $this->get(route('portal.leaves.show', $leave))->assertOk()->assertSee('Confidential fixture leave')
            ->assertDontSee(route('portal.leaves.approve', $leave), false);
        $this->get(route('portal.appraisals.audit.form', $appraisal))->assertOk()->assertSee('Confidential fixture appraisal');
        $this->get(route('portal.hr-reporting.attendance'))->assertOk();
        $this->get(route('portal.hr-reporting.index'))->assertOk()->assertDontSee('Create HR draft');
        $this->get(route('portal.admin-weekly.index'))->assertOk()->assertDontSee('Submit Administrator Report');
        $this->get(route('portal.payroll'))->assertOk()->assertSee('Staff Payslips')->assertDontSee('Issue Payslips');
        $this->get(route('portal.finance', ['module_view'=>'tools']))->assertOk()->assertSee('Confidential fixture claim')
            ->assertDontSee('action="'.route('portal.finance.claims.store').'"', false);
        $this->get(route('portal.finance', ['module_view'=>'revenue']))->assertOk()->assertSee('Confirmed Revenue');
        $this->get(route('portal.finance.claims.receipt', $claim))->assertOk()->assertDownload();
        $this->get(route('portal.finance.budgets.index'))->assertOk()->assertSee('Confidential fixture budget');
        $this->get(route('portal.finance.budgets.show', $budget))->assertOk()->assertSee('Confidential fixture budget')
            ->assertDontSee(route('portal.finance.budgets.edit', $budget), false);
        $this->get(route('portal.finance.advances.index'))->assertOk();
        $this->get(route('dashboard', ['weekly_department'=>'hr_admin', 'hr_tab'=>'people']))->assertOk()->assertDontSee('Save HR Entry');
        $this->post(route('portal.leaves.approve', $leave))->assertSessionHasErrors();
        $this->post(route('portal.appraisals.audit.submit', $appraisal))->assertForbidden();
        $this->post(route('portal.hr.leave-balance.update', $staff))->assertForbidden();
        $this->post(route('portal.payroll.distribute'))->assertForbidden();
        $this->post(route('portal.finance.claims.cvo.action', [$claim, 'approve']))->assertForbidden();
        $this->post(route('portal.finance.budgets.cvo.action', [$budget, 'approve']))->assertForbidden();
        $this->post(route('portal.hr-dashboard.activities.store'))->assertForbidden();
        $this->assertSame('pending_hr', $leave->fresh()->status);
        $this->assertSame('Submitted to CVO', $claim->fresh()->status);

        $ordinaryExecutiveStaff = User::factory()->create(['status'=>'active','department'=>'executive','access_role'=>'staff']);
        $this->actingAs($ordinaryExecutiveStaff)->get(route('portal.hr'))->assertForbidden();
        $this->get(route('portal.finance'))->assertOk()->assertDontSee('Confidential fixture claim');
        $this->get(route('portal.finance.claims.receipt', $claim))->assertForbidden();
    }
}
