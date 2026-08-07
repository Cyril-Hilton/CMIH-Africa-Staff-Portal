<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicAppraisalBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $managerSameDept;
    protected User $managerOtherDept;
    protected User $hrUser;
    protected User $staffUser;
    protected User $otherStaffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status'      => 'active',
            'department'  => 'admin',
        ]);

        $this->managerSameDept = User::factory()->create([
            'access_role' => 'manager',
            'status'      => 'active',
            'department'  => 'creative',
        ]);

        $this->managerOtherDept = User::factory()->create([
            'access_role' => 'manager',
            'status'      => 'active',
            'department'  => 'finance',
        ]);

        // Level 1: HR Manager — has full HR access
        $this->hrUser = User::factory()->create([
            'access_role'    => 'manager',
            'status'         => 'active',
            'department'     => 'hr_admin',
            'position_title' => 'Manager',
            'job_level'      => 'manager',
        ]);

        $this->staffUser = User::factory()->create([
            'access_role' => 'staff',
            'status'      => 'active',
            'department'  => 'creative',
        ]);

        $this->otherStaffUser = User::factory()->create([
            'access_role' => 'staff',
            'status'      => 'active',
            'department'  => 'creative',
        ]);
    }

    /** HR can store a table-type appraisal metric with column templates */
    public function test_hr_can_create_table_type_metric(): void
    {
        $this->actingAs($this->hrUser);

        $template = [
            ['key' => 'col_1', 'label' => 'Objective', 'type' => 'text'],
            ['key' => 'col_2', 'label' => 'Self Rating', 'type' => 'score']
        ];

        $response = $this->post(route('portal.hr.appraisals.metrics.store'), [
            'name'           => 'Creative Output Grid',
            'category'       => 'General',
            'metric_type'    => 'table',
            'default_rows'   => 4,
            'table_template' => json_encode($template),
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('appraisal_metrics', [
            'name'        => 'Creative Output Grid',
            'metric_type' => 'table',
            'default_rows'=> 4,
        ]);

        $metric = AppraisalMetric::where('name', 'Creative Output Grid')->first();
        $this->assertEquals($template, $metric->table_template);
    }

    /** Table self assessment rates rows and averages them mathematically */
    public function test_table_based_self_assessment_score_averaging(): void
    {
        $metric = AppraisalMetric::create([
            'name'           => 'Dynamic Targets',
            'category'       => 'General',
            'metric_type'    => 'table',
            'default_rows'   => 2,
            'table_template' => [
                ['key' => 'obj', 'label' => 'Objective', 'type' => 'text'],
                ['key' => 'score', 'label' => 'Rating', 'type' => 'score']
            ]
        ]);

        $appraisal = Appraisal::create([
            'user_id' => $this->staffUser->id,
            'quarter' => 'Q2',
            'year'    => 2026,
            'status'  => 'draft',
        ]);

        $tableData = [
            $metric->id => [
                ['obj' => 'Launch CMIH website', 'score' => 6],
                ['obj' => 'Refactor auth flows', 'score' => 10],
            ]
        ];

        $this->actingAs($this->staffUser);
        
        $response = $this->post(route('portal.appraisals.self.submit', $appraisal), [
            'table_data' => $tableData,
            'comments'   => 'Did my best.',
        ]);

        $response->assertRedirect(route('portal.appraisals.index'));
        
        $appraisal->refresh();
        $this->assertEquals('submitted', $appraisal->status);
        
        // Average should be (6 + 10) / 2 = 8
        $this->assertEquals(8, $appraisal->self_assessment['scores'][$metric->id]);
        $this->assertEquals(8.0, $appraisal->avg_self_score);
        $this->assertEquals($tableData, $appraisal->self_table_data);
    }

    /** Access control verification for the Performance Accountability Report */
    public function test_report_route_access_restrictions(): void
    {
        // 1. Standard staff member cannot view another staff's report
        $this->actingAs($this->otherStaffUser);
        $response = $this->get(route('portal.appraisals.report', $this->staffUser));
        $response->assertStatus(403);

        // 2. Manager of another department cannot view the report
        $this->actingAs($this->managerOtherDept);
        $response = $this->get(route('portal.appraisals.report', $this->staffUser));
        $response->assertStatus(403);

        // 3. Manager of the SAME department CAN view the report
        $this->actingAs($this->managerSameDept);
        $response = $this->get(route('portal.appraisals.report', $this->staffUser));
        $response->assertStatus(200);

        // 4. HR Admin CAN view the report
        $this->actingAs($this->hrUser);
        $response = $this->get(route('portal.appraisals.report', $this->staffUser));
        $response->assertStatus(200);

        // 5. Super Admin CAN view the report
        $this->actingAs($this->superAdmin);
        $response = $this->get(route('portal.appraisals.report', $this->staffUser));
        $response->assertStatus(200);
    }
}
