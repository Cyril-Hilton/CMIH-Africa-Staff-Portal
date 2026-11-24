<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\OperationsReportSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsClockInRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_forms_survive_clock_in_block_and_can_be_submitted_afterwards(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));
        foreach (['staff', 'manager'] as $role) {
            $person = User::factory()->create(['department' => 'operations_projects', 'status' => 'active', 'access_role' => $role]);
            $input = ['kind' => 'weekly_update', 'title' => 'Preserved '.$role.' weekly report',
                'week' => '2026-11-23', 'status' => 'in_progress', 'data' => ['deliverables' => 'Keep the entered report.', 'progress_percent' => 40]];
            $this->actingAs($person)->withHeader('X-Test-Enforce-ClockIn', '1')
                ->post(route('portal.operations.reporting.save'), $input)
                ->assertRedirect(route('dashboard', ['weekly_department' => 'operations_projects', 'report_period' => 'week',
                    'report_date' => '2026-11-23', 'ops_add' => 'weekly_update']).'#operations-entry')
                ->assertSessionHasErrors('attendance')
                ->assertSessionHasInput('title', $input['title'])
                ->assertSessionHasInput('data.deliverables', 'Keep the entered report.');
            $this->assertDatabaseMissing('operations_report_submissions', ['submitted_by' => $person->id]);
            $this->get(route('dashboard', ['weekly_department' => 'operations_projects', 'ops_add' => 'weekly_update',
                'report_date' => '2026-11-23']))->assertOk()->assertSee($input['title'])
                ->assertSee('Keep the entered report.')->assertSee('Open Clock In in another tab');

            Attendance::create(['user_id' => $person->id, 'clock_in_at' => now(), 'daily_objective' => 'Weekly report', 'status' => 'Late']);
            $response = $this->post(route('portal.operations.reporting.save'), $input)->assertSessionHasNoErrors();
            $entry = OperationsReportSubmission::where('submitted_by', $person->id)->sole();
            $this->assertSame($role === 'manager' ? 'published' : 'pending', $entry->state);
            $this->get($response->headers->get('Location'))->assertOk()->assertSee($input['title']);
        }
    }
}
