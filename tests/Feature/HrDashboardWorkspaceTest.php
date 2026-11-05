<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsClockedIn;
use App\Models\HrDashboardActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrDashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_workstream_filters_and_confidential_attachment_access(): void
    {
        $this->withoutMiddleware(EnsureUserIsClockedIn::class);
        Storage::fake('local');
        config(['creative.upload_scan_required' => false, 'creative.upload_scanner' => null]);

        $manager = User::factory()->create(['status' => 'active', 'department' => 'hr_admin', 'access_role' => 'manager']);
        $administrator = User::factory()->create([
            'status' => 'active', 'department' => 'hr_admin', 'access_role' => 'admin', 'position_title' => 'Office Administrator',
        ]);
        $outside = User::factory()->create(['status' => 'active', 'department' => 'finance']);
        $data = [
            'tab' => 'recruitment', 'category' => 'Reference checks', 'activity' => 'Confidential recruitment follow-up',
            'opened_on' => '2026-11-02', 'deadline' => '2026-11-06', 'assigned_to' => $manager->id,
            'status' => 'Pending Approval', 'attachment' => UploadedFile::fake()->createWithContent('reference.txt', 'Example'),
        ];
        $this->actingAs($outside)->post(route('portal.hr-dashboard.activities.store'), $data)->assertForbidden();
        $this->actingAs($manager)->post(route('portal.hr-dashboard.activities.store'), $data)->assertRedirect();
        $entry = HrDashboardActivity::sole();
        Storage::disk('local')->assertExists($entry->attachment['path']);
        $url = route('dashboard', ['weekly_department' => 'hr_admin', 'hr_tab' => 'recruitment',
            'hr_period' => 'range', 'hr_from' => '2026-10-26', 'hr_to' => '2026-11-08']);
        $this->get($url.'&hr_owner='.$manager->id)->assertOk()->assertSee('Confidential recruitment follow-up')
            ->assertSee('Show action points')->assertSee('Add HR Entry')->assertDontSee('Add Administrator Entry');
        $this->get($url.'&hr_owner='.$administrator->id)->assertOk()->assertDontSee('Confidential recruitment follow-up');
        $this->get(route('portal.hr-dashboard.activities.attachment', $entry))->assertOk()->assertDownload('reference.txt');
        unset($data['attachment']);
        $this->patch(route('portal.hr-dashboard.activities.update', $entry), array_replace($data, [
            'revision' => 1, 'status' => 'Completed',
        ]))->assertRedirect();
        $this->assertSame('Completed', $entry->fresh()->status);
        $this->actingAs($administrator)->get($url)->assertOk()->assertDontSee('Confidential recruitment follow-up');
        $this->get(route('portal.hr-dashboard.activities.attachment', $entry))->assertNotFound();
        $this->assertFalse($administrator->canViewAllPayroll());
        $this->assertFalse($administrator->canReviewIdentityDocuments());
        $this->get(route('portal.hr.staff.index'))->assertForbidden();
        $this->get(route('portal.hr-reporting.attendance'))->assertForbidden();
        $this->get(route('portal.hr-reporting.index'))->assertForbidden();
        $this->get(route('portal.appraisals.report', $manager))->assertForbidden();
        $this->post(route('portal.hr.appraisals.metrics.store'), [])->assertForbidden();
        $this->post(route('portal.hr-dashboard.activities.store'), $data)->assertForbidden();
        $this->get(route('dashboard', ['weekly_department' => 'hr_admin', 'hr_tab' => 'operations']))
            ->assertOk()->assertSee('Add Administrator Entry')->assertDontSee('Add HR Entry');
        $this->post(route('portal.hr-dashboard.activities.store'), array_replace($data, [
            'tab' => 'operations', 'category' => 'Office supplies and stationery',
            'activity' => 'Order office supplies', 'assigned_to' => $administrator->id,
        ]))->assertRedirect();
        $this->assertDatabaseHas('hr_dashboard_activities', ['tab' => 'operations', 'activity' => 'Order office supplies']);
        HrDashboardActivity::where('activity', 'Order office supplies')->sole()->update(['status' => 'In Progress']);
        HrDashboardActivity::create([
            'tab' => 'operations', 'category' => 'Repairs and maintenance', 'activity' => 'Inspect the office door',
            'opened_on' => '2026-11-03', 'assigned_to' => $administrator->id, 'status' => 'Completed',
        ]);
        $operations = ['weekly_department' => 'hr_admin', 'hr_tab' => 'operations',
            'hr_period' => 'range', 'hr_from' => '2026-11-01', 'hr_to' => '2026-11-08'];
        $this->get(route('dashboard', $operations + ['hr_sort' => 'activity', 'hr_direction' => 'asc']))
            ->assertOk()->assertSeeInOrder(['Inspect the office door', 'Order office supplies']);
        $this->get(route('dashboard', $operations + ['hr_sort' => 'assignee', 'hr_status' => 'Completed', 'hr_q' => 'door']))
            ->assertOk()->assertSee('Inspect the office door')->assertDontSee('Order office supplies');


    }
}
