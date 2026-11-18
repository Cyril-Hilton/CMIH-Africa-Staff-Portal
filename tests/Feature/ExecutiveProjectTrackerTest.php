<?php

namespace Tests\Feature;

use App\Models\{CreativeRequest, CrmPipelineProject, OperationsHandover, PerformanceRegister, Task, User, WeeklyConsolidatedItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutiveProjectTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(array $permissions = [User::EXECUTIVE_ACTIVITY_PERMISSION]): User
    {
        return User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'executive','permissions_matrix'=>$permissions]);
    }

    private function tracker(array $filters=[])
    {
        return $this->get(route('portal.project-journey.tracker', $filters+['report_date'=>'2026-11-16']));
    }

    private function register(User $owner, array $attributes=[]): PerformanceRegister
    {
        return PerformanceRegister::create($attributes+['department'=>'operations_projects','kind'=>'activation','reference'=>(string)Str::uuid(),
            'title'=>'Unlinked activation','owner_id'=>$owner->id,'period_start'=>'2026-11-16','period_end'=>'2026-11-22',
            'status'=>'in_progress','data'=>[],'approved_at'=>now(),'approved_by'=>$owner->id]);
    }

    public function test_tracker_connects_recorded_project_stages_team_and_activation_sources_without_inventing_handover(): void
    {
        $this->travelTo(now()->setDate(2026,11,18)->setTime(12,0));
        $viewer=$this->viewer();
        $owner=User::factory()->create(['department'=>'client_relations']);
        $creative=User::factory()->create(['department'=>'creatives']);
        $ops=User::factory()->create(['department'=>'operations_projects']);
        $support=User::factory()->create(['department'=>'brands_marketing']);
        $project=CrmPipelineProject::create(['client_name'=>'Sample Client','project_name'=>'Connected launch','stage'=>'won',
            'week_start'=>'2026-11-16','source_period_confirmed'=>true,'owner_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id]);
        $brief=CreativeRequest::create(['uuid'=>(string)Str::uuid(),'reference'=>'CR-TRACK','title'=>'Launch artwork','crm_project_id'=>$project->id,
            'requester_id'=>$owner->id,'assignee_id'=>$creative->id,'supporting_staff_ids'=>[$support->id],
            'stage'=>'completed','completed_at'=>now(),'visibility'=>'shared']);
        $this->actingAs($viewer);
        $response=$this->tracker()->assertOk()->assertSee('Connected launch')->assertSee($support->name);
        $row=$response->viewData('tracker')['records']->first();
        $this->assertSame('Operations handover',$row['current']);
        $this->assertSame('current',$row['steps'][3]['state']);
        $this->assertSame('waiting',$row['steps'][4]['state']);
        $this->assertFalse($row['complete']);
        $this->assertNotEmpty($row['gaps']);
        $handover=OperationsHandover::create(['project_id'=>$project->id,'requester_id'=>$owner->id,'lead_id'=>$ops->id,
            'title'=>'Launch handover','plan'=>'Build and deploy','acceptance_criteria'=>'Photos and sign-off','location'=>'Accra','priority'=>'High',
            'event_at'=>'2026-11-20 12:00','setup_due_at'=>'2026-11-20 10:00','stage'=>'planning','cr_signoff_at'=>now(),'ops_signoff_at'=>now()]);
        $activation=$this->register($ops,['title'=>'Launch activation','data'=>['crm_project_id'=>$project->id,'collaborator_ids'=>[$support->id]]]);
        $this->register($ops,['kind'=>'posm_deployment','title'=>'Launch assets','data'=>['activation_id'=>$activation->id]]);
        $response=$this->tracker(['activity_department'=>'operations_projects','activity_staff'=>$support->id])->assertOk()->assertSee('Launch assets');
        $tracker=$response->viewData('tracker');
        $this->assertSame(1,$tracker['counts']['projects']);
        $this->assertSame(0,$tracker['counts']['weekly']);
        $row=$tracker['records']->first();
        $this->assertSame('Execution',$row['current']);
        $this->assertSame($ops->id,$row['owner_id']);
        $this->assertSame('current',$row['steps'][4]['state']);
        $handover->update(['stage'=>'completed','submitted_at'=>now(),'verified_at'=>now()]);
        $this->assertFalse($this->tracker()->viewData('tracker')['records']->first()['complete']);
        $activation->update(['status'=>'completed']);
        $this->assertTrue($this->tracker()->viewData('tracker')['records']->first()['complete']);
    }

    public function test_direct_creative_briefs_and_unlinked_activations_remain_visible_and_weekly_data_respects_period_and_approval(): void
    {
        $this->travelTo(now()->setDate(2026,11,18)->setTime(12,0));
        $viewer=$this->viewer(); $owner=User::factory()->create(['department'=>'creatives']);
        $task=Task::create(['title'=>'Direct creative work','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'department'=>'creatives','status'=>'Open','priority'=>'Medium']);
        CreativeRequest::create(['uuid'=>(string)Str::uuid(),'reference'=>'CR-DIRECT','title'=>'Direct creative work','task_id'=>$task->id,
            'requester_id'=>$owner->id,'assignee_id'=>$owner->id,'received_at'=>now(),'stage'=>'in_progress']);
        $this->register($owner);
        $this->register($owner,['title'=>'Unapproved activation','approved_at'=>null]);
        $this->register($owner,['kind'=>'weekly_update','title'=>'Previous week work','period_start'=>'2026-11-09','period_end'=>'2026-11-15']);
        WeeklyConsolidatedItem::create(['department'=>'brands_marketing','week_start'=>'2026-11-16','week_end'=>'2026-11-22',
            'campaign_name'=>'Published workstream','deliverables'=>'Deliver the approved plan','lead_staff_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,
            'workflow_state'=>'approved','report_status'=>'reviewed','status'=>'In Progress','progress_percent'=>50]);
        WeeklyConsolidatedItem::create(['department'=>'brands_marketing','week_start'=>'2026-11-16','week_end'=>'2026-11-22',
            'campaign_name'=>'Unpublished draft','deliverables'=>'Draft plan','lead_staff_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,
            'workflow_state'=>'approved','report_status'=>'draft','status'=>'Planned']);
        $this->actingAs($viewer);
        $r=$this->tracker(['tracker_type'=>'all'])->assertOk()->assertSee('Direct creative work')->assertSee('Unlinked activation')
            ->assertSee('Published workstream')->assertDontSee('Unapproved activation')->assertDontSee('Unpublished draft')->assertDontSee('Previous week work');
        $t=$r->viewData('tracker');
        $this->assertSame(2,$t['counts']['projects']);
        $this->assertSame(0,$t['counts']['tasks']);
        $this->assertSame(1,$t['counts']['weekly']);
        foreach (['month','year'] as $period) $this->tracker(['report_period'=>$period,'tracker_type'=>'weekly'])->assertOk()->assertSee('Previous week work');
        $this->tracker(['report_date'=>'2026-11-09','tracker_type'=>'weekly'])->assertOk()->assertSee('Previous week work')->assertDontSee('Published workstream');
        $this->get(route('dashboard'))->assertOk()->assertSee('Live Project & Team Tracker',false)->assertSee('aria-label="Tracker work types"',false);
    }

    public function test_tracker_preserves_confidentiality_and_requires_executive_access(): void
    {
        $this->travelTo(now()->setDate(2026,11,18)->setTime(12,0));
        $viewer=$this->viewer(); $owner=User::factory()->create(['department'=>'finance']);
        $this->register($owner,['department'=>'finance','kind'=>'report','title'=>'Private finance record']);
        $task=Task::create(['title'=>'Private finance task','assigned_to'=>$owner->id,'assigned_by'=>$owner->id,'department'=>'finance','status'=>'Open','priority'=>'Medium']);
        CreativeRequest::create(['uuid'=>(string)Str::uuid(),'reference'=>'CR-PRIVATE','title'=>'Private creative brief','visibility'=>'private','requester_id'=>$owner->id,'received_at'=>now()]);
        $this->actingAs($viewer)->tracker(['tracker_type'=>'all'])->assertOk()->assertDontSee('Private finance')->assertDontSee('Private creative brief');
        $viewer->update(['permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION]]);
        $this->actingAs($viewer->fresh())->tracker(['tracker_type'=>'all'])->assertOk()->assertSee('Private finance record')->assertSee('Private finance task')->assertDontSee('Private creative brief');
        $ordinary=User::factory()->create(['status'=>'active','department'=>'operations_projects','access_role'=>'staff']);
        $this->actingAs($ordinary)->tracker()->assertForbidden();
        $this->actingAs($viewer)->tracker(['report_date'=>'bad-date'])->assertSessionHasErrors('report_date');
    }
}
