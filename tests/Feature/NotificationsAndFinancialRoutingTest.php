<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PettyCashClaim;
use App\Models\SupplierInvoice;
use App\Models\ProjectBudget;
use App\Models\Notification;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsAndFinancialRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected User $financeUser;
    protected User $cvoUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staffUser = User::factory()->create([
            'name' => 'Staff Jane',
            'email' => 'jane@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'creatives',
        ]);

        $this->financeUser = User::factory()->create([
            'name' => 'Finance John',
            'email' => 'john@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'finance',
        ]);

        $this->cvoUser = User::factory()->create([
            'name' => 'Cyril Hilton',
            'email' => 'cyril@cmih.africa',
            'access_role' => 'super_admin',
            'status' => 'active',
            'job_level' => 'super_admin',
        ]);
    }

    /**
     * Test Petty Cash Claim routing options.
     */
    public function test_petty_cash_claim_routing(): void
    {
        $this->actingAs($this->staffUser);

        // 1. Submit to Finance
        $res = $this->post(route('portal.finance.claims.store'), [
            'amount' => 100.00,
            'currency' => 'USD',
            'description' => 'Test claim to Finance',
            'submit_to' => 'finance',
        ]);
        $res->assertRedirect();
        
        $claim1 = PettyCashClaim::where('description', 'Test claim to Finance')->first();
        $this->assertNotNull($claim1);
        $this->assertEquals('Submitted to Finance', $claim1->status);

        // Verify Finance got notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->financeUser->id,
            'title' => 'New Expense Claim Submitted to Finance',
        ]);

        // 2. Submit to CVO
        $res = $this->post(route('portal.finance.claims.store'), [
            'amount' => 200.00,
            'currency' => 'USD',
            'description' => 'Test claim to CVO',
            'submit_to' => 'cvo',
        ]);
        $res->assertRedirect();

        $claim2 = PettyCashClaim::where('description', 'Test claim to CVO')->first();
        $this->assertNotNull($claim2);
        $this->assertEquals('Submitted to CVO', $claim2->status);

        // Verify CVO got notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->cvoUser->id,
            'title' => 'New Expense Claim Submitted to CVO',
        ]);

        // 3. Finance approves/verifies the one submitted to Finance
        $this->actingAs($this->financeUser);
        $resApprove = $this->post(route('portal.finance.claims.action', [$claim1, 'verify']));
        $resApprove->assertRedirect();
        
        $claim1->refresh();
        $this->assertEquals('Finance Approved', $claim1->status);

        // Verify CVO notified about Finance Approved claim
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->cvoUser->id,
            'title' => 'Petty Cash Claim Approved by Finance',
        ]);
    }

    public function test_finance_claim_receipt_download_is_permission_checked(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('local')->put('receipts/private-claim.txt', 'private claim receipt');

        $claim = PettyCashClaim::create([
            'user_id' => $this->staffUser->id,
            'amount' => 100,
            'currency' => 'GHC',
            'description' => 'Private claim',
            'receipt_path' => 'receipts/private-claim.txt',
            'status' => 'Submitted to Finance',
        ]);

        $outsider = User::factory()->create([
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'operations_projects',
        ]);

        $this->actingAs($this->staffUser)
            ->get(route('portal.finance.claims.receipt', $claim))
            ->assertOk();

        $this->actingAs($this->financeUser)
            ->get(route('portal.finance.claims.receipt', $claim))
            ->assertOk();

        $this->actingAs($this->cvoUser)
            ->get(route('portal.finance.claims.receipt', $claim))
            ->assertOk();

        $this->actingAs($outsider)
            ->get(route('portal.finance.claims.receipt', $claim))
            ->assertForbidden();

        Storage::disk('public')->assertMissing($claim->receipt_path);
    }

    /**
     * Test Supplier Invoice routing options.
     */
    public function test_supplier_invoice_routing(): void
    {
        $this->actingAs($this->staffUser);

        // 1. Submit to Finance
        $res = $this->post(route('portal.finance.invoices.store'), [
            'supplier_name' => 'Supplier A',
            'invoice_number' => 'INV-A',
            'description' => 'Invoice for materials',
            'amount' => 500.00,
            'currency' => 'GH₵',
            'submit_to' => 'finance',
        ]);
        $res->assertRedirect();

        $invoice1 = SupplierInvoice::where('supplier_name', 'Supplier A')->first();
        $this->assertNotNull($invoice1);
        $this->assertEquals('Submitted to Finance', $invoice1->status);

        // Verify Finance got notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->financeUser->id,
            'title' => 'New Supplier Invoice Submitted to Finance',
        ]);

        // 2. Submit to CVO
        $res2 = $this->post(route('portal.finance.invoices.store'), [
            'supplier_name' => 'Supplier B',
            'invoice_number' => 'INV-B',
            'description' => 'Invoice for services',
            'amount' => 600.00,
            'currency' => 'GH₵',
            'submit_to' => 'cvo',
        ]);
        $res2->assertRedirect();

        $invoice2 = SupplierInvoice::where('supplier_name', 'Supplier B')->first();
        $this->assertNotNull($invoice2);
        $this->assertEquals('Submitted to CVO', $invoice2->status);

        // Verify CVO got notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->cvoUser->id,
            'title' => 'New Supplier Invoice Submitted to CVO',
        ]);

        // 3. Finance verifies the one submitted to Finance
        $this->actingAs($this->financeUser);
        $resApprove = $this->post(route('portal.finance.invoices.action', [$invoice1, 'verify']));
        $resApprove->assertRedirect();

        $invoice1->refresh();
        $this->assertEquals('Finance Approved', $invoice1->status);

        // Verify CVO notified about Finance Approved invoice
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->cvoUser->id,
            'title' => 'Supplier Invoice Approved by Finance',
        ]);
    }

    /**
     * Test notification reading and redirection.
     */
    public function test_notification_reading_and_redirection(): void
    {
        $this->actingAs($this->staffUser);

        $notif = Notification::create([
            'user_id' => $this->staffUser->id,
            'title' => 'Assigned to task',
            'message' => 'Go check it out',
            'url' => '/portal/tasks?filter=mine',
            'read_at' => null,
        ]);

        // Check index page
        $indexRes = $this->get(route('portal.announcements'));
        $indexRes->assertStatus(200);
        $indexRes->assertSee('Assigned to task');

        // Click to read redirect
        $readRes = $this->get(route('portal.notifications.read', $notif));
        $readRes->assertRedirect('/portal/tasks?filter=mine');

        $notif->refresh();
        $this->assertNotNull($notif->read_at);

        // Test Mark All as Read
        $notif2 = Notification::create([
            'user_id' => $this->staffUser->id,
            'title' => 'Second Alert',
            'message' => 'Second Alert Message',
            'read_at' => null,
        ]);

        $this->post(route('portal.notifications.readAll'))
            ->assertRedirect();

        $notif2->refresh();
        $this->assertNotNull($notif2->read_at);
    }

    /**
     * Test admin announcements broadcast notifications.
     */
    public function test_admin_announcement_broadcasts_notifications(): void
    {
        $this->actingAs($this->cvoUser);

        $res = $this->post(route('admin.announcements.store'), [
            'title' => 'Important Company Update',
            'body' => '<p><span style="background-color:hsl(0, 0%, 90%);">We have updated&nbsp;our protocols.</span></p>',
            'pinned' => 1,
        ]);
        $res->assertRedirect();

        // Check that staff User got a notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => 'New Company Announcement: Important Company Update',
            'message' => 'We have updated our protocols.',
        ]);

        // Check that CVO user (the publisher) did NOT get a personal notification
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->cvoUser->id,
            'title' => 'New Company Announcement: Important Company Update',
        ]);
    }

    public function test_announcement_pages_show_clean_text_instead_of_editor_html(): void
    {
        Announcement::create([
            'user_id' => $this->cvoUser->id,
            'title' => 'Staff Birthday Spotlight - Salisu Ibrahim',
            'body' => '&lt;p&gt;Happy Birthday, Salisu!&lt;/p&gt;&lt;p&gt;&lt;span style=&quot;background-color:hsl(0, 0%, 90%);color:rgb(0,0,0);&quot;&gt;Thank you&amp;nbsp;for all you bring to the team.&lt;/span&gt;&lt;/p&gt;&lt;p&gt;&amp;nbsp;&lt;/p&gt;',
            'audience_type' => 'all',
        ]);

        $response = $this->actingAs($this->staffUser)->get(route('portal.announcements'));

        $response->assertOk();
        $response->assertSee('Happy Birthday, Salisu!');
        $response->assertSee('Thank you for all you bring to the team.');
        $response->assertDontSee('&lt;p', false);
        $response->assertDontSee('&lt;span', false);
        $response->assertDontSee('&amp;nbsp', false);
        $response->assertDontSee('hsl(0, 0%, 90%)');
    }

    public function test_company_announcements_are_paginated_in_their_own_silent_region(): void
    {
        foreach (range(1, 8) as $index) {
            $announcement = Announcement::create([
                'user_id' => $this->cvoUser->id,
                'title' => "Announcement {$index}",
                'body' => "Announcement body {$index}",
                'audience_type' => 'all',
            ]);
            $announcement->forceFill(['created_at' => now()->subMinutes($index)])->save();
        }

        $firstPage = $this->actingAs($this->staffUser)
            ->get(route('portal.announcements'));

        $firstPage->assertOk()
            ->assertSee('data-silent-region="company-announcements"', false)
            ->assertSee('Announcement 1')
            ->assertSee('Announcement 6')
            ->assertDontSee('Announcement 7')
            ->assertSee('announcements_page=2', false);

        $secondPage = $this->actingAs($this->staffUser)
            ->get(route('portal.announcements', ['announcements_page' => 2]));

        $secondPage->assertOk()
            ->assertSee('Announcement 7')
            ->assertSee('Announcement 8')
            ->assertDontSee('Announcement 1');
    }

    public function test_hr_admin_can_send_targeted_announcement_with_recipient_notifications(): void
    {
        $this->actingAs($this->cvoUser);

        $res = $this->post(route('portal.hr.announcements.store'), [
            'title' => 'Selected Staff Briefing',
            'body' => 'Please review the attached HR update before close of business.',
            'audience_type' => 'selected',
            'recipient_ids' => [$this->staffUser->id],
        ]);

        $res->assertRedirect();

        $announcement = Announcement::where('title', 'Selected Staff Briefing')->firstOrFail();
        $this->assertSame('selected', $announcement->audience_type);
        $this->assertSame([$this->staffUser->id], $announcement->recipient_ids);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => 'HR Announcement: Selected Staff Briefing',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->financeUser->id,
            'title' => 'HR Announcement: Selected Staff Briefing',
        ]);

        $this->actingAs($this->staffUser)
            ->get(route('portal.announcements'))
            ->assertOk()
            ->assertSee('Selected Staff Briefing');

        $this->actingAs($this->financeUser)
            ->get(route('portal.announcements'))
            ->assertOk()
            ->assertDontSee('Selected Staff Briefing');
    }

    public function test_hr_department_staff_can_see_and_send_hr_announcements(): void
    {
        $hrStaff = User::factory()->create([
            'name' => 'Deon HR',
            'email' => 'deon.hr@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'HR Admin',
            'job_level' => 'senior_executive',
        ]);

        $this->actingAs($hrStaff)
            ->get(route('portal.hr'))
            ->assertOk()
            ->assertSee('HR Announcement Blast')
            ->assertDontSee('Employee Lifecycle & Contracts');

        $response = $this->actingAs($hrStaff)->post(route('portal.hr.announcements.store'), [
            'title' => 'HR Desk Notice',
            'body' => 'Please check the HR desk before close of business.',
            'audience_type' => 'selected',
            'recipient_ids' => [$this->staffUser->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'user_id' => $hrStaff->id,
            'title' => 'HR Desk Notice',
            'audience_type' => 'selected',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => 'HR Announcement: HR Desk Notice',
        ]);
    }

    public function test_department_announcements_match_normalized_department_names(): void
    {
        $hrStaff = User::factory()->create([
            'name' => 'HR Sender',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'HR & Admin',
        ]);

        $financeRecipient = User::factory()->create([
            'name' => 'Finance Human Label',
            'access_role' => 'staff',
            'status' => 'active',
            'department' => 'Finance Department',
        ]);

        $response = $this->actingAs($hrStaff)->post(route('portal.hr.announcements.store'), [
            'title' => 'Finance Only Notice',
            'body' => 'Finance team only.',
            'audience_type' => 'departments',
            'department_keys' => ['finance'],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $financeRecipient->id,
            'title' => 'HR Announcement: Finance Only Notice',
        ]);

        $this->actingAs($financeRecipient)
            ->get(route('portal.announcements'))
            ->assertOk()
            ->assertSee('Finance Only Notice');

        $this->actingAs($this->staffUser)
            ->get(route('portal.announcements'))
            ->assertOk()
            ->assertDontSee('Finance Only Notice');
    }

    /**
     * Test birthday notifications are correctly dispatched.
     */
    public function test_birthday_notifications(): void
    {
        $this->actingAs($this->staffUser);

        // Set staffUser's birthday to today
        $today = now();
        $this->staffUser->update([
            'birthday_month' => $today->month,
            'birthday_day' => $today->day,
        ]);

        // Clear cache so it runs fresh
        \Illuminate\Support\Facades\Cache::forget('birthday_wishes_run_' . $today->toDateString());

        // Load the dashboard (which triggers the daily birthday check)
        $res = $this->get(route('dashboard'));
        $res->assertStatus(200);

        // Verify the celebrant (staffUser) got a personal notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => '🎉 Happy Birthday!',
        ]);

        // Verify other active users (e.g. financeUser) got the birthday announcement
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->financeUser->id,
            'title' => "🎂 Birthday Celebration: {$this->staffUser->name}",
        ]);

        // Verify celebrant did NOT get the team broadcast notification to avoid duplication
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => "🎂 Birthday Celebration: {$this->staffUser->name}",
        ]);
    }

    public function test_birthday_reminder_is_sent_one_day_before_to_internal_staff_only(): void
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 6, 30, 8, 0, 0));

        $this->financeUser->update([
            'birthday_month' => 7,
            'birthday_day' => 1,
        ]);

        $merchandiser = User::factory()->create([
            'name' => 'External Merch',
            'email' => 'external-merch-birthday@cmih.africa',
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        \Illuminate\Support\Facades\Cache::forget('birthday_reminders_run_' . now()->toDateString());
        \Illuminate\Support\Facades\Cache::forget('birthday_wishes_run_' . now()->toDateString());

        $response = $this->actingAs($this->staffUser)->get(route('dashboard'));
        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->staffUser->id,
            'title' => "Birthday Tomorrow: {$this->financeUser->name}",
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->financeUser->id,
            'title' => "Birthday Tomorrow: {$this->financeUser->name}",
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $merchandiser->id,
            'title' => "Birthday Tomorrow: {$this->financeUser->name}",
        ]);

        \Illuminate\Support\Carbon::setTestNow();
    }
}
