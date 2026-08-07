<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppraisalPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $hrUser;
    protected User $hrAssistant;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status'      => 'active',
            'department'  => 'admin',
        ]);

        $this->manager = User::factory()->create([
            'access_role' => 'manager',
            'status'      => 'active',
            'department'  => 'operations',
        ]);

        // Level 1: HR Manager — has full HR access (position_title + job_level required)
        $this->hrUser = User::factory()->create([
            'access_role'    => 'manager',
            'status'         => 'active',
            'department'     => 'hr_admin',
            'position_title' => 'Manager',
            'job_level'      => 'manager',
        ]);

        // Level 2: HR Assistant — in HR dept but NOT a manager; cannot open cycles
        $this->hrAssistant = User::factory()->create([
            'access_role'    => 'executive',
            'status'         => 'active',
            'department'     => 'hr_admin',
            'position_title' => 'HR Assistant',
            'job_level'      => 'executive',
        ]);

        $this->staffUser = User::factory()->create([
            'access_role' => 'staff',
            'status'      => 'active',
            'department'  => 'operations',
            'line_manager_id' => $this->manager->id,
        ]);

        // Create test metrics
        AppraisalMetric::create(['name' => 'Quality of Work', 'category' => 'General']);
        AppraisalMetric::create(['name' => 'Technical Skills', 'category' => 'Technical']);
    }

    /** HR can open a new appraisal cycle for a staff member */
    public function test_hr_can_open_appraisal_cycle(): void
    {
        $this->actingAs($this->hrUser);

        $response = $this->post(route('portal.appraisals.create'), [
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q2',
            'year'    => 2026,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appraisals', [
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q2',
            'year'    => 2026,
            'status'  => 'draft',
        ]);
    }

    /** Staff can submit a self-assessment with 1-10 scores */
    public function test_staff_can_submit_self_assessment(): void
    {
        $appraisal = Appraisal::create([
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q2',
            'year'    => 2026,
            'status'  => 'draft',
        ]);

        $metrics = AppraisalMetric::all();
        $scores  = $metrics->mapWithKeys(fn ($m) => [$m->id => 8])->toArray();

        $this->actingAs($this->staffUser);
        $response = $this->post(route('portal.appraisals.self.submit', $appraisal), [
            'scores'   => $scores,
            'comments' => 'I delivered all Q2 objectives on schedule.',
        ]);

        $response->assertRedirect(route('portal.appraisals.index'));
        $appraisal->refresh();
        $this->assertEquals('submitted', $appraisal->status);
        $this->assertNotNull($appraisal->self_assessment);
        $this->assertEquals(8.0, $appraisal->avg_self_score);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'title' => 'Appraisal Manager Review Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Appraisal Manager Review Needed',
        ]);
    }

    /** Manager can submit review scorecard (1-10) */
    public function test_manager_can_submit_review(): void
    {
        $appraisal = Appraisal::create([
            'user_id'         => $this->staffUser->id,
            'quarter'         => 'Q2',
            'year'            => 2026,
            'status'          => 'submitted',
            'self_assessment' => ['scores' => [1 => 8, 2 => 7], 'comments' => 'Good quarter.'],
        ]);

        $metrics = AppraisalMetric::all();
        $scores  = $metrics->mapWithKeys(fn ($m) => [$m->id => 9])->toArray();

        $this->actingAs($this->manager);
        $response = $this->post(route('portal.appraisals.manager.submit', $appraisal), [
            'scores'          => $scores,
            'manager_comment' => 'Excellent contributor this quarter.',
        ]);

        $response->assertRedirect(route('portal.appraisals.index'));
        $appraisal->refresh();
        $this->assertEquals('manager_reviewed', $appraisal->status);
        $this->assertEquals(9.0, $appraisal->avg_manager_score);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->hrUser->id,
            'title' => 'Appraisal HR Audit Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdmin->id,
            'title' => 'Appraisal HR Audit Needed',
        ]);
    }

    /** HR can finalise and approve appraisal */
    public function test_hr_can_finalise_appraisal(): void
    {
        $appraisal = Appraisal::create([
            'user_id'         => $this->staffUser->id,
            'quarter'         => 'Q2',
            'year'            => 2026,
            'status'          => 'manager_reviewed',
            'self_assessment' => ['scores' => [1 => 8], 'comments' => 'Good.'],
            'manager_review'  => ['scores' => [1 => 9], 'manager_comment' => 'Great.', 'reviewer_id' => $this->manager->id, 'reviewer_name' => $this->manager->name],
        ]);

        $this->actingAs($this->hrUser);
        $response = $this->post(route('portal.appraisals.audit.submit', $appraisal), [
            'hr_notes'       => 'HR validation confirms outstanding performance.',
            'final_decision' => 'approved',
        ]);

        $response->assertRedirect(route('portal.appraisals.index'));
        $appraisal->refresh();
        $this->assertEquals('approved', $appraisal->status);
    }

    /** Super Admin can unlock any appraisal mid-flight */
    public function test_super_admin_can_unlock_appraisal(): void
    {
        $appraisal = Appraisal::create([
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q3',
            'year'    => 2026,
            'status'  => 'approved',
        ]);

        $this->actingAs($this->superAdmin);
        $response = $this->post(route('portal.appraisals.unlock', $appraisal));

        $response->assertRedirect();
        $appraisal->refresh();
        $this->assertEquals('draft', $appraisal->status);
    }

    /** Regular staff cannot open appraisal cycles (only HR Manager/Super Admin can) */
    public function test_regular_staff_cannot_open_appraisal_cycle(): void
    {
        $this->actingAs($this->staffUser);
        $response = $this->post(route('portal.appraisals.create'), [
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q1',
            'year'    => 2026,
        ]);
        $response->assertStatus(403);
    }

    /** HR Assistants (Level 2) cannot open appraisal cycles */
    public function test_hr_assistant_cannot_open_appraisal_cycle(): void
    {
        $this->actingAs($this->hrAssistant);
        $response = $this->post(route('portal.appraisals.create'), [
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q1',
            'year'    => 2026,
        ]);
        $response->assertStatus(403);
    }

    /** Scores are validated to be between 1 and 10 */
    public function test_scores_must_be_between_1_and_10(): void
    {
        $appraisal = Appraisal::create([
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q2',
            'year'    => 2026,
            'status'  => 'draft',
        ]);

        $metrics = AppraisalMetric::all();
        $scores  = $metrics->mapWithKeys(fn ($m) => [$m->id => 15])->toArray(); // 15 is out of range

        $this->actingAs($this->staffUser);
        $response = $this->post(route('portal.appraisals.self.submit', $appraisal), [
            'scores'   => $scores,
            'comments' => 'Test',
        ]);

        $response->assertSessionHasErrors(['scores.*']);
    }
}
