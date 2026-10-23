<?php

namespace Tests\Feature;

use App\Models\AdminWeeklyReport;
use App\Models\CrmWeeklyProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentReportingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $dept, string $role = 'staff'): User
    {
        return User::factory()->create(['department' => $dept, 'access_role' => $role, 'status' => 'active']);
    }

    public function test_crm_financials_are_private_and_weekly_progress_is_percentage_only(): void
    {
        $owner = $this->user('client_relations', 'manager');
        $staff = $this->user('creatives');
        $v = ['client_name' => 'Test client', 'project_name' => 'Privacy pipeline project', 'owner_id' => $owner->id, 'stage' => 'proposal_sent', 'week_start' => '2026-10-19', 'target_amount' => 123457.89, 'actual_amount' => 61728.945, 'proposed_amount' => 999987.12, 'po_amount' => 9000, 'invoiced_amount' => 4500, 'collected_amount' => 2250, 'contact_info' => 'PRIVATE-CONTACT-TOKEN', 'bottlenecks' => 'PRIVATE-FINANCIAL-NOTE', 'reason' => 'Privacy test'];
        $this->actingAs($owner)->post(route('portal.crm.store'), $v)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_weekly_progress', 1);
        app(\App\Services\ClientRelationsPipeline::class)->publish(\App\Models\CrmPipelineProject::first());
        $this->assertDatabaseCount('crm_weekly_progress', 1);
        $this->assertEqualsWithDelta(50, (float) CrmWeeklyProgress::first()->achievement_percent, 0.01);
        $this->get(route('portal.crm.index'))->assertOk()->assertSee('123,457.89')->assertSee('PRIVATE-CONTACT-TOKEN');
        $this->actingAs($staff)->get(route('portal.crm.index'))->assertForbidden();
        $page = $this->get(route('dashboard', ['weekly_department' => 'client_relations']));
        $page->assertOk()->assertSee('Privacy pipeline project')->assertSee('50.0%')->assertDontSee('123,457.89')->assertDontSee('999987.12')->assertDontSee('PRIVATE-CONTACT-TOKEN')->assertDontSee('PRIVATE-FINANCIAL-NOTE');
        $payload = $page->viewData('publicCrmProgress')->first()->toArray();
        $this->assertArrayNotHasKey('target_amount', $payload);
        $this->assertArrayNotHasKey('proposed_amount', $payload);
        $this->actingAs($this->user('finance', 'super_admin'))->get(route('portal.crm.index'))->assertOk()->assertSee('PRIVATE-CONTACT-TOKEN');
    }

    public function test_crm_unknown_targets_are_not_zero_or_guessed_stage_achievement(): void
    {
        $owner = $this->user('client_relations', 'manager');
        $this->actingAs($owner)->post(route('portal.crm.store'), ['client_name' => 'C', 'project_name' => 'Unknown target', 'owner_id' => $owner->id, 'stage' => 'verbal_yes', 'week_start' => '2026-10-19', 'reason' => 'New deal'])->assertSessionHasNoErrors();
        $public = CrmWeeklyProgress::first();
        $this->assertNull($public->achievement_percent);
        $this->assertEquals(90, (float) $public->stage_percent);
    }

    public function test_admin_report_has_its_own_sections_and_does_not_reuse_hr_table(): void
    {
        $admin = $this->user('hr_admin');
        $this->actingAs($admin)->get(route('portal.admin-weekly.index'))->assertOk()->assertSee('General Office Administration')->assertSee('Fleet Management')->assertSee('Driver Management')->assertSee('Asset Management')->assertSee('Priorities for the Next Period');
        $lines = [];
        foreach (config('admin_weekly') as $section => $schema) {
            foreach ($schema['rows'] as $code => $field) {
                $lines[$section][$code] = ['value_state' => 'not_reported'];
            }
        }
        $lines['fleet']['total_vehicles'] = ['value_state' => 'reported', 'numeric_value' => 3];
        $lines['fleet']['vehicles_available'] = ['value_state' => 'reported', 'numeric_value' => 2];
        $this->post(route('portal.admin-weekly.store'), ['period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'lines' => $lines])->assertSessionHasNoErrors();
        $report = AdminWeeklyReport::first();
        $this->assertSame('submitted', $report->status);
        $this->get(route('portal.admin-weekly.index'))->assertOk()->assertSee('Previous approved period');
        $this->actingAs($this->user('hr_admin', 'manager'))->post(route('portal.admin-weekly.approve', $report), ['reason' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->approved_at);
        $this->actingAs($this->user('client_relations'))->get(route('portal.admin-weekly.index'))->assertForbidden();
    }
}
