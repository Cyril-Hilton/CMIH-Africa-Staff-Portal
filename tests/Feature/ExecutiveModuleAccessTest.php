<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClientRelationsPipeline;
use App\Services\PerformanceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cvo_and_super_admin_have_all_department_and_new_reporting_modules(): void
    {
        $executives = [User::factory()->create(['department' => 'creatives', 'access_role' => 'staff', 'job_level' => 'CVO', 'position_title' => 'Chief Visionary Leader', 'status' => 'active']), User::factory()->create(['department' => 'finance', 'access_role' => 'super_admin', 'status' => 'active'])];
        foreach ($executives as $user) {
            $this->assertTrue(ClientRelationsPipeline::member($user));
            $this->assertTrue($user->canManageOperationsPrintingRequests());
            $this->assertTrue($user->canEditWarehouseAssets());
            $this->assertTrue($user->hasFullHrAccess());
            $this->actingAs($user);
            foreach (array_keys(config('performance.departments')) as $department) {
                $this->assertTrue(PerformanceAccess::manages($user, $department));
                $this->get(route('portal.performance.index', ['department' => $department]))->assertOk();
            }
            foreach (['portal.crm.index', 'portal.hr-reporting.index', 'portal.admin-weekly.index', 'portal.printing-requests.index', 'portal.printing-requests.create', 'portal.hr', 'portal.finance', 'portal.operations', 'portal.brands', 'portal.creative', 'portal.assets.warehouse.index', 'admin.users', 'admin.content', 'admin.tasks', 'admin.announcements', 'admin.events', 'admin.brands', 'admin.portfolio', 'admin.portfolio-payments', 'admin.settings'] as $route) {
                $this->get(route($route))->assertOk();
            }
            $this->get(route('admin.dashboard'))->assertRedirect(route('dashboard',['weekly_department'=>'all']));
            $this->get(route('portal.performance.index'))->assertSee('Executive Business Summary')->assertSee('Cash Position')->assertSee('Vendor Printing Requests')->assertSee('Client Relations')->assertSee('HR &amp; Admin Module', false);
        }
    }

    public function test_hr_tools_are_semantic_collapsed_panels_with_existing_actions(): void
    {
        $user = User::factory()->create(['department' => 'hr_admin', 'access_role' => 'super_admin', 'status' => 'active']);
        $response = $this->actingAs($user)->get(route('portal.hr'))->assertOk();
        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $panels = $xpath->query('//details[summary[contains(@class,"hr-module-summary")]]');
        $this->assertSame(12, $panels->length);
        foreach ($panels as $panel) {
            $this->assertFalse($panel->hasAttribute('open'));
            $this->assertGreaterThan(0, $xpath->query('.//form | .//a | .//table', $panel)->length, trim($xpath->query('./summary', $panel)->item(0)->textContent));
        }
        $response->assertSee('HR & Admin Workspaces', false)->assertSee('People &amp; Employment', false)->assertSee('Leave &amp; Attendance', false)->assertSee('Pay &amp; Benefits', false)->assertSee('Workplace Services')->assertSee('Reports &amp; Presentation', false)->assertDontSee('Blueprint.docx')->assertSee('Staff communications')->assertSee('Leave management')->assertSee('Scheduled visitors')->assertSee('Contacts &amp; vendors', false);
    }

    public function test_regular_other_department_manager_still_cannot_access_private_modules(): void
    {
        $user = User::factory()->create(['department' => 'creatives', 'access_role' => 'manager', 'status' => 'active']);
        $this->actingAs($user);
        foreach (['portal.crm.index', 'portal.hr-reporting.index', 'portal.admin-weekly.index', 'portal.printing-requests.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
