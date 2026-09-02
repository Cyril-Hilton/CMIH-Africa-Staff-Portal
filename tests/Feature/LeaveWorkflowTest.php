<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\User;
use App\Mail\LeaveApplicantStatusMail;
use App\Mail\LeaveApprovalNeededMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_leave_working_days_exclude_weekends(): void
    {
        $this->assertSame(4, LeaveApplication::workingDaysBetween('2026-09-03', '2026-09-08'));
        $this->assertSame(0, LeaveApplication::workingDaysBetween('2026-09-05', '2026-09-06'));
    }

    public function test_hr_manager_can_review_all_staff_leave_requests_from_hr_module(): void
    {
        $hr = User::factory()->create([
            'name' => 'HR Manager',
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'position_title' => 'HR Manager',
            'job_level' => 'manager',
        ]);
        $staff = User::factory()->create([
            'name' => 'Leave Applicant',
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $cover = User::factory()->create(['status' => 'active']);
        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => '2026-09-03',
            'end_date' => '2026-09-08',
            'leave_type' => 'annual',
            'status' => 'pending_hr',
            'covering_staff_id' => $cover->id,
            'comments' => 'Please review this request.',
        ]);

        $response = $this->actingAs($hr)->get(route('portal.hr'));

        $response->assertOk();
        $response->assertSee('All Staff Leave Manager');
        $response->assertSee($staff->name);
        $response->assertSee('Working Days');
        $response->assertSee(route('portal.leaves.approve', $leave), false);
        $response->assertSee(route('portal.leaves.return', $leave), false);

        $returnResponse = $this->actingAs($hr)->post(route('portal.leaves.return', $leave), [
            'rejection_comments' => 'Please add a clearer handover note.',
        ]);

        $returnResponse->assertRedirect();
        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'returned_for_correction',
            'comments' => 'Returned for Correction: Please add a clearer handover note.',
        ]);
    }

    public function test_leave_portal_renders_pending_approval_return_action(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'line_manager_id' => $manager->id,
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
            'leave_type' => 'annual',
            'status' => 'pending_manager',
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response = $this->actingAs($manager)->get(route('portal.leaves'));

        $response->assertStatus(200);
        $response->assertSee(route('portal.leaves.return', $leave), false);
    }

    public function test_returned_leave_can_be_corrected_and_resubmitted(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $newCover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(8),
            'leave_type' => 'annual',
            'status' => 'returned_for_correction',
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
            'comments' => 'Returned for Correction: Please choose a staff member from your department.',
        ]);

        $page = $this->actingAs($staff)->get(route('portal.leaves'));
        $page->assertOk();
        $page->assertSee('triggerResubmit', false);
        $page->assertSeeText('Edit Leave Request', false);

        $response = $this->actingAs($staff)->post(route('portal.leaves.resubmit', $leave), [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $newCover->id,
            'comments' => 'Corrected cover colleague.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $leave->refresh();
        $this->assertEquals('pending_manager', $leave->status);
        $this->assertEquals($newCover->id, $leave->covering_staff_id);
        $this->assertEquals('Corrected cover colleague.', $leave->comments);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'title' => 'Leave Approval Needed',
        ]);
    }

    public function test_pending_leave_can_be_updated_before_approval(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(8),
            'leave_type' => 'annual',
            'status' => 'pending_manager',
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response = $this->actingAs($staff)->post(route('portal.leaves.resubmit', $leave), [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
            'comments' => 'Updated before manager review.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $leave->refresh();
        $this->assertSame('pending_manager', $leave->status);
        $this->assertEquals(now()->addDays(10)->toDateString(), $leave->start_date->toDateString());
        $this->assertEquals(now()->addDays(12)->toDateString(), $leave->end_date->toDateString());
        $this->assertSame('Updated before manager review.', $leave->comments);
    }

    public function test_approved_leave_cannot_be_extended_in_place(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'leave_type' => 'annual',
            'status' => 'approved',
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response = $this->actingAs($staff)->post(route('portal.leaves.resubmit', $leave), [
            'leave_type' => 'annual',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response->assertSessionHasErrors('leave');
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertEquals(now()->addDay()->toDateString(), $leave->fresh()->end_date->toDateString());
    }

    public function test_finalized_leave_cannot_be_rejected_or_returned_for_correction(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $approvedLeave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'leave_type' => 'annual',
            'status' => 'approved',
            'covering_staff_id' => $cover->id,
        ]);

        $rejectResponse = $this->actingAs($superAdmin)->post(route('portal.leaves.reject', $approvedLeave), [
            'rejection_comments' => 'Trying to mutate finalized leave.',
        ]);
        $rejectResponse->assertSessionHasErrors('leave');
        $this->assertSame('approved', $approvedLeave->fresh()->status);

        $returnResponse = $this->actingAs($superAdmin)->post(route('portal.leaves.return', $approvedLeave), [
            'rejection_comments' => 'Trying to send back finalized leave.',
        ]);
        $returnResponse->assertSessionHasErrors('leave');
        $this->assertSame('approved', $approvedLeave->fresh()->status);
    }

    public function test_pending_hr_leave_can_be_updated_before_final_approval(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
            'leave_type' => 'annual',
            'status' => 'pending_hr',
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response = $this->actingAs($staff)->post(route('portal.leaves.resubmit', $leave), [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
            'comments' => 'Adjusted before final approval.',
        ]);

        $response->assertSessionHasNoErrors();
        $leave->refresh();
        $this->assertSame('pending_manager', $leave->status);
        $this->assertEquals(now()->addDays(7)->toDateString(), $leave->start_date->toDateString());
    }

    public function test_acting_line_manager_rights_ignore_future_pending_rejected_and_non_manager_leave(): void
    {
        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
        ]);
        $nonManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
        ]);
        $actingLM = User::factory()->create(['status' => 'active']);
        $cover = User::factory()->create(['status' => 'active']);

        LeaveApplication::create([
            'user_id' => $lineManager->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(5),
            'leave_type' => 'annual',
            'status' => 'approved',
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        LeaveApplication::create([
            'user_id' => $lineManager->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(2),
            'leave_type' => 'annual',
            'status' => 'pending_hr',
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        LeaveApplication::create([
            'user_id' => $lineManager->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(2),
            'leave_type' => 'annual',
            'status' => 'rejected',
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        LeaveApplication::create([
            'user_id' => $nonManager->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(2),
            'leave_type' => 'annual',
            'status' => 'approved',
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        $this->assertFalse($actingLM->isActingLineManagerFor($lineManager->id));
        $this->assertFalse($actingLM->isActingLineManagerFor($nonManager->id));
        $this->assertEmpty($actingLM->activeDelegatedManagerIds());
    }

    public function test_tier1_staff_leave_workflow(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);

        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        $hr = User::factory()->create([
            'status'         => 'active',
            'access_role'    => 'manager',
            'department'     => 'hr_admin',
            'position_title' => 'Manager',
            'job_level'      => 'manager',
        ]);

        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'contact_email' => 'cover@cmih.africa',
        ]);

        // 1. Submit application
        $response = $this->actingAs($staff)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(), // 5 days
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
            'comments' => 'Handover notes',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('leave_applications', [
            'user_id' => $staff->id,
            'status' => 'pending_manager',
            'line_manager_id' => $manager->id,
        ]);

        $leave = LeaveApplication::first();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'title' => 'Leave Approval Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'Leave Approval Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $cover->id,
            'title' => 'Leave Cover Duty Assigned',
        ]);

        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($manager) {
            return $mail->hasTo($manager->contact_email ?: $manager->email);
        });

        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($hr) {
            return $mail->hasTo($hr->contact_email ?: $hr->email);
        });

        // 2. Line Manager approves -> routes to pending_hr
        $responseApprove1 = $this->actingAs($manager)->post("/portal/leaves/{$leave->id}/approve");
        $responseApprove1->assertRedirect();
        
        $leave->refresh();
        $this->assertEquals('pending_hr', $leave->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id,
            'title' => 'Leave Approval Needed',
            'url' => route('portal.hr'),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'Leave Approval Needed',
            'url' => route('portal.leaves'),
        ]);

        // 3. HR Manager final sign-off
        $responseApprove2 = $this->actingAs($hr)->post("/portal/leaves/{$leave->id}/approve");
        $responseApprove2->assertRedirect();

        $leave->refresh();
        $this->assertEquals('approved', $leave->status);

        // Balance decremented
        $staff->refresh();
        $this->assertEquals(25, $staff->leave_balance);

        // Email cover notification sent
        Mail::assertSent(\App\Mail\LeaveCoverNotificationMail::class, function ($mail) use ($cover) {
            return $mail->hasTo($cover->contact_email);
        });
        Mail::assertSent(LeaveApplicantStatusMail::class, function ($mail) use ($staff) {
            return $mail->hasTo($staff->contact_email ?: $staff->email)
                && $mail->statusLabel === 'Approved';
        });
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'title' => 'Leave Request Approved',
        ]);
    }

    public function test_resend_leave_approval_prompt_command_sends_pending_barakah_emails(): void
    {
        $barakah = User::factory()->create([
            'name' => 'Alfah Barakah Zakaria',
            'email' => 'abarakah@cmih.africa',
            'contact_email' => 'abarakah.personal@example.com',
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 30,
        ]);
        $lineManager = User::factory()->create([
            'name' => 'Barakah Line Manager',
            'email' => 'line.manager@cmih.africa',
            'contact_email' => 'line.manager.personal@example.com',
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);
        $hrManager = User::factory()->create([
            'name' => 'HR Manager',
            'email' => 'hr.manager@cmih.africa',
            'contact_email' => 'hr.manager.personal@example.com',
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'position_title' => 'Manager',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $barakah->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
            'leave_type' => 'annual',
            'status' => 'pending_manager',
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $this->artisan('leave:resend-approval-prompts', [
            '--email' => 'abarakah@cmih.africa',
            '--send' => true,
        ])->assertExitCode(0);

        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($lineManager, $leave) {
            return $mail->leave->is($leave)
                && $mail->approver->is($lineManager)
                && $mail->hasTo($lineManager->contact_email);
        });
        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($hrManager, $leave) {
            return $mail->leave->is($leave)
                && $mail->approver->is($hrManager)
                && $mail->hasTo($hrManager->contact_email);
        });
        $this->assertDatabaseHas('notifications', [
            'user_id' => $lineManager->id,
            'title' => 'Leave Approval Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hrManager->id,
            'title' => 'Leave Approval Needed',
        ]);
    }

    public function test_resend_leave_request_notice_can_send_for_non_pending_barakah_leave(): void
    {
        $barakah = User::factory()->create([
            'name' => 'Alfah Barakah Zakaria',
            'email' => 'abarakah@cmih.africa',
            'contact_email' => 'abarakah@cmihafrica.com',
            'status' => 'active',
            'access_role' => 'staff',
        ]);
        $lineManager = User::factory()->create([
            'name' => 'Masana Alfah',
            'email' => 'masanaalfah@cmih.africa',
            'contact_email' => 'amasana@cmihafrica.com',
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
        ]);
        $hrManager = User::factory()->create([
            'name' => 'HR Manager',
            'email' => 'hr.manager@cmih.africa',
            'contact_email' => 'hr.manager@cmihafrica.com',
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'hr_admin',
            'position_title' => 'Manager',
            'job_level' => 'manager',
        ]);
        $cover = User::factory()->create([
            'status' => 'active',
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $barakah->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
            'leave_type' => 'annual',
            'status' => 'returned_for_correction',
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $this->artisan('leave:resend-approval-prompts', [
            '--leave-id' => $leave->id,
            '--request-notice' => true,
            '--send' => true,
        ])->assertExitCode(0);

        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($lineManager) {
            return $mail->requestNotice === true
                && $mail->approver->is($lineManager)
                && $mail->hasTo($lineManager->contact_email);
        });
        Mail::assertSent(LeaveApprovalNeededMail::class, function ($mail) use ($hrManager) {
            return $mail->requestNotice === true
                && $mail->approver->is($hrManager)
                && $mail->hasTo($hrManager->contact_email);
        });
        $this->assertDatabaseHas('notifications', [
            'user_id' => $lineManager->id,
            'title' => 'Leave Request Notice',
        ]);
    }

    public function test_tier2_manager_leave_workflow(): void
    {
        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
            'leave_balance' => 30,
        ]);

        $cvo = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
            'job_level' => 'super_admin',
        ]);

        $hr = User::factory()->create([
            'status'         => 'active',
            'access_role'    => 'manager',
            'department'     => 'hr_admin',
            'position_title' => 'Manager',
            'job_level'      => 'manager',
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
        ]);

        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        // 1. Submit leave request -> line manager first
        $response = $this->actingAs($manager)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // 2 days
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $cover->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_applications', [
            'user_id' => $manager->id,
            'status' => 'pending_manager',
            'line_manager_id' => $lineManager->id,
        ]);

        $leave = LeaveApplication::first();

        // 2. Line Manager approves -> routes to final approval queue
        $responseApprove1 = $this->actingAs($lineManager)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('pending_hr', $leave->status);

        // 3. CVO / HR / Super Admin can final approve
        $responseApprove2 = $this->actingAs($cvo)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('approved', $leave->status);
    }

    public function test_tier3_hr_leave_workflow(): void
    {
        $hr = User::factory()->create([
            'status'         => 'active',
            'access_role'    => 'manager',
            'department'     => 'hr_admin',
            'position_title' => 'Manager',
            'job_level'      => 'manager',
            'leave_balance'  => 30,
        ]);

        $cvo = User::factory()->create([
            'status' => 'active',
            'access_role' => 'admin',
            'job_level' => 'super_admin',
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
        ]);

        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        // 1. Submit leave request -> line manager first
        $response = $this->actingAs($hr)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // 2 days
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $cover->id,
        ]);

        $response->assertSessionHasNoErrors();
        $leave = LeaveApplication::first();
        $this->assertEquals('pending_manager', $leave->status);

        // 2. Line manager approves -> final queue
        $this->actingAs($lineManager)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('pending_hr', $leave->status);

        // Applicant cannot final-approve their own HR leave
        $selfApprove = $this->actingAs($hr)->post("/portal/leaves/{$leave->id}/approve");
        $selfApprove->assertSessionHasErrors(['leave']);
        $leave->refresh();
        $this->assertEquals('pending_hr', $leave->status);

        // 3. CVO approves -> final approval
        $responseApprove = $this->actingAs($cvo)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('approved', $leave->status);
    }

    public function test_super_admin_final_approval_after_line_manager_stage(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'leave_balance' => 30,
        ]);

        $superAdmin = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
        ]);

        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        $leave = LeaveApplication::create([
            'user_id' => $staff->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
            'leave_type' => 'annual',
            'status' => 'pending_manager',
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
        ]);

        // Super Admin cannot bypass the line-manager-first stage.
        $response = $this->actingAs($superAdmin)->post("/portal/leaves/{$leave->id}/approve");
        $response->assertSessionHasErrors(['leave']);
        $leave->refresh();
        $this->assertEquals('pending_manager', $leave->status);

        $this->actingAs($lineManager)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('pending_hr', $leave->status);

        $this->actingAs($superAdmin)->post("/portal/leaves/{$leave->id}/approve");
        $leave->refresh();
        $this->assertEquals('approved', $leave->status);
    }

    public function test_insufficient_leave_balance(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'executive',
            'leave_balance' => 3,
        ]);

        $manager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'job_level' => 'manager',
        ]);

        $cover = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $response = $this->actingAs($staff)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(), // 5 days (greater than 3)
            'line_manager_id' => $manager->id,
            'covering_staff_id' => $cover->id,
        ]);

        $response->assertSessionHasErrors(['leave_balance']);
        $this->assertEquals(0, LeaveApplication::count());
    }

    public function test_line_manager_must_appoint_acting_line_manager_when_applying_for_leave(): void
    {
        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'leave_balance' => 30,
        ]);

        $cover = User::factory()->create(['status' => 'active']);
        $routingLM = User::factory()->create(['status' => 'active', 'access_role' => 'manager', 'job_level' => 'manager']);

        // 1. Submit without delegate_line_manager_id should fail for line manager
        $responseFail = $this->actingAs($lineManager)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'line_manager_id' => $routingLM->id,
            'covering_staff_id' => $cover->id,
        ]);

        $responseFail->assertSessionHasErrors(['delegate_line_manager_id']);

        // 2. Submit with delegate_line_manager_id succeeds
        $actingLM = User::factory()->create(['status' => 'active']);

        $responseSuccess = $this->actingAs($lineManager)->post('/portal/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'line_manager_id' => $routingLM->id,
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        $responseSuccess->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_applications', [
            'user_id' => $lineManager->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);
    }

    public function test_acting_line_manager_can_approve_tasks_and_leaves_during_leave_period(): void
    {
        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
            'leave_balance' => 30,
        ]);
        $actingLM = User::factory()->create(['status' => 'active']);
        $subordinate = User::factory()->create([
            'status' => 'active',
            'line_manager_id' => $lineManager->id,
        ]);
        $cover = User::factory()->create(['status' => 'active']);
        $hrManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'super_admin',
        ]);

        // Line manager has an approved active leave TODAY with $actingLM as delegate
        $leave = LeaveApplication::create([
            'user_id' => $lineManager->id,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(5),
            'leave_type' => 'annual',
            'status' => 'approved',
            'covering_staff_id' => $cover->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        $this->assertTrue($actingLM->isActingLineManagerFor($lineManager->id));

        // Subordinate submits a task needing line manager completion review
        $task = \App\Models\Task::create([
            'title' => 'Subordinate deliverable',
            'assigned_to' => $subordinate->id,
            'assigned_by' => $subordinate->id,
            'department' => 'operations_projects',
            'status' => 'Awaiting Approval',
            'completion_review_status' => 'pending',
            'custom_fields' => ['completion_manager_id' => $lineManager->id],
        ]);

        // Acting LM approves subordinate's task completion
        $response = $this->actingAs($actingLM)->post(route('portal.tasks.completion-review', $task), [
            'action' => 'approve',
            'review_comment' => 'Approved on behalf of Line Manager on leave',
        ]);

        $response->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertEquals('Completed', $task->status);
        $this->assertEquals('approved', $task->completion_review_status);

        // Subordinate submits leave request to $lineManager
        $subLeave = LeaveApplication::create([
            'user_id' => $subordinate->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
            'leave_type' => 'annual',
            'status' => 'pending_manager',
            'line_manager_id' => $lineManager->id,
            'covering_staff_id' => $cover->id,
        ]);

        // Acting LM can approve subordinate's leave request
        $responseLeaveApprove = $this->actingAs($actingLM)->post(route('portal.leaves.approve', $subLeave));
        $responseLeaveApprove->assertSessionHasNoErrors();

        $subLeave->refresh();
        $this->assertEquals('pending_hr', $subLeave->status);
    }

    public function test_acting_line_manager_delegation_expires_after_leave_end_date(): void
    {
        $lineManager = User::factory()->create([
            'status' => 'active',
            'access_role' => 'manager',
            'job_level' => 'manager',
        ]);
        $actingLM = User::factory()->create(['status' => 'active']);

        // Leave ended yesterday
        $leave = LeaveApplication::create([
            'user_id' => $lineManager->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(1),
            'leave_type' => 'annual',
            'status' => 'approved',
            'covering_staff_id' => $actingLM->id,
            'delegate_line_manager_id' => $actingLM->id,
        ]);

        // Delegation is no longer active today
        $this->assertFalse($actingLM->isActingLineManagerFor($lineManager->id));
        $this->assertEmpty($actingLM->activeDelegatedManagerIds());
    }
}
