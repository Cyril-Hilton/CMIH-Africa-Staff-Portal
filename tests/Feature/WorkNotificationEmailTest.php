<?php

namespace Tests\Feature;

use App\Mail\AwardCertificateMail;
use App\Mail\LeaveApprovalNeededMail;
use App\Mail\StaffPayslipMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function hr(array $data = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'HR Manager', 'department' => 'hr_admin', 'access_role' => 'manager',
            'job_level' => 'manager', 'status' => 'active', 'email' => 'hr.login@cmih.africa',
            'contact_email' => 'hr.personal@example.com', 'work_email' => 'hr.work@cmihafrica.com',
        ], $data));
    }

    public function test_explicit_work_mailbox_wins_for_business_and_account_notifications(): void
    {
        $hr = $this->hr();
        $this->assertSame('hr.work@cmihafrica.com', $hr->notificationEmail());
        $this->assertSame('hr.work@cmihafrica.com', $hr->routeNotificationForMail(new ResetPassword('test-token')));
    }

    public function test_legacy_company_contact_is_preserved_but_personal_contact_is_not_used_for_business_mail(): void
    {
        $user = new User(['email' => 'portal.login@cmih.africa', 'contact_email' => 'real.work@cmihafrica.com', 'access_role' => 'staff']);
        $this->assertSame('real.work@cmihafrica.com', $user->notificationEmail());
        $user->contact_email = 'personal@gmail.com';
        $this->assertSame('portal.login@cmih.africa', $user->notificationEmail());
        $user->access_role = 'merchandiser';
        $this->assertSame('personal@gmail.com', $user->notificationEmail());
    }

    public function test_leave_requests_notify_current_hr_work_mailbox_and_follow_the_next_manager(): void
    {
        Mail::fake();
        $hr = $this->hr();
        $staff = User::factory()->create(['status' => 'active', 'access_role' => 'staff', 'job_level' => 'executive', 'leave_balance' => 30]);
        $manager = User::factory()->create(['status' => 'active', 'access_role' => 'manager', 'job_level' => 'manager']);
        $cover = User::factory()->create(['status' => 'active', 'access_role' => 'staff']);
        $request = ['leave_type' => 'annual', 'start_date' => now()->addDays(14)->toDateString(), 'end_date' => now()->addDays(15)->toDateString(), 'line_manager_id' => $manager->id, 'covering_staff_id' => $cover->id];
        $this->actingAs($staff)->post(route('portal.leaves.store'), $request)->assertSessionHasNoErrors();
        Mail::assertSent(LeaveApprovalNeededMail::class, fn ($mail) => $mail->hasTo('hr.work@cmihafrica.com') && ! $mail->hasTo('hr.personal@example.com'));
        Mail::assertNotSent(LeaveApprovalNeededMail::class, fn ($mail) => $mail->hasTo('hr.personal@example.com'));

        $hr->update(['status' => 'inactive']);
        $this->hr(['name' => 'Next HR Manager', 'email' => 'next.hr@cmih.africa', 'contact_email' => 'next.personal@example.com', 'work_email' => 'next.hr@cmihafrica.com']);
        Mail::fake();
        $request['start_date'] = now()->addDays(28)->toDateString();
        $request['end_date'] = now()->addDays(29)->toDateString();
        $this->post(route('portal.leaves.store'), $request)->assertSessionHasNoErrors();
        Mail::assertSent(LeaveApprovalNeededMail::class, fn ($mail) => $mail->hasTo('next.hr@cmihafrica.com'));
        Mail::assertNotSent(LeaveApprovalNeededMail::class, fn ($mail) => $mail->hasTo('hr.work@cmihafrica.com') || $mail->hasTo('next.personal@example.com'));
    }

    public function test_hr_can_set_work_address_without_changing_login_or_personal_details(): void
    {
        $hr = $this->hr();
        $staff = User::factory()->create(['access_role' => 'staff', 'status' => 'active', 'email' => 'staff.login@cmih.africa', 'contact_email' => 'staff.personal@example.com']);
        $this->actingAs($hr)->patch(route('portal.hr.staff.work-email', $staff), ['work_email' => 'Staff.Work@cmihafrica.com'])->assertSessionHasNoErrors();
        $staff->refresh();
        $this->assertSame('staff.work@cmihafrica.com', $staff->work_email);
        $this->assertSame('staff.login@cmih.africa', $staff->email);
        $this->assertSame('staff.personal@example.com', $staff->contact_email);
        $this->patch(route('portal.hr.staff.work-email', $staff), ['work_email' => 'personal@gmail.com'])->assertSessionHasErrors('work_email');
        $this->assertSame('staff.work@cmihafrica.com', $staff->fresh()->work_email);
        $this->actingAs($staff)->patch(route('portal.hr.staff.work-email', $hr), ['work_email' => 'wrong@cmihafrica.com'])->assertForbidden();
    }

    public function test_payslips_and_award_certificates_use_only_the_work_mailbox(): void
    {
        Mail::fake();
        $hr = $this->hr(['salary' => 5000]);
        $this->actingAs($hr)->post(route('portal.payroll.distribute'), ['period' => '2026-09', 'recipient_ids' => [$hr->id]])->assertSessionHasNoErrors();
        Mail::assertSent(StaffPayslipMail::class, fn ($mail) => $mail->hasTo('hr.work@cmihafrica.com') && count($mail->to) === 1);
        $this->post('/portal/awards/lock', ['award_type' => 'employee_of_the_month', 'period' => '2026-09', 'winner_id' => $hr->id, 'winner_score' => 95])->assertSessionHasNoErrors();
        Mail::assertSent(AwardCertificateMail::class, fn ($mail) => $mail->hasTo('hr.work@cmihafrica.com') && count($mail->to) === 1);
    }
}
