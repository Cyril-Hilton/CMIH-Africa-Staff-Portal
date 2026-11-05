<?php

namespace Tests\Feature;

use App\Mail\LeaveApprovalNeededMail;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveMailReviewLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_and_line_manager_email_links_open_the_specific_leave_request(): void
    {
        $hr = User::factory()->create(['name' => 'HR Lead', 'department' => 'hr_admin', 'access_role' => 'manager', 'status' => 'active']);
        $staff = User::factory()->create(['access_role' => 'staff', 'status' => 'active']);
        $manager = User::factory()->create(['name' => 'Operations Manager', 'department' => 'operations_projects', 'access_role' => 'manager', 'status' => 'active']);
        $leave = LeaveApplication::create(['user_id' => $staff->id, 'line_manager_id' => $manager->id, 'leave_type' => 'annual', 'start_date' => '2026-10-05', 'end_date' => '2026-10-16', 'status' => 'pending_manager']);
        $mail = new LeaveApprovalNeededMail($leave, $hr, true);
        $this->assertStringContainsString(route('portal.leaves.show', $leave), $mail->render());
        $this->assertStringContainsString('pending manager', $mail->render());
        $this->assertSame(route('portal.leaves.show', $leave), (new LeaveApprovalNeededMail($leave, $manager))->content()->with['reviewUrl']);
        $this->assertSame('pending_manager', $leave->fresh()->status);
    }
}
