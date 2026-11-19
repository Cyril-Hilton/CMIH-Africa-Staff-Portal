<?php

namespace Tests\Feature;

use App\Models\{CreativeRequest, CrmPipelineProject, OperationsHandover, PerformanceEvent, User};
use App\Services\Creative\CreativeWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConnectedProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $department,string $role='staff'): User
    {
        return User::factory()->create(['department'=>$department,'access_role'=>$role,'status'=>'active']);
    }

    private function setupProject(string $role='manager'): array
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $owner=$this->staff('client_relations',$role);
        $creative=$this->staff('creatives','manager');
        $operations=$this->staff('operations_projects','manager');
        $payload=['owner_id'=>$owner->id,'client_name'=>'Example Brand','project_name'=>'Connected launch','stage'=>'project_ongoing','week_start'=>'2026-11-16','week_end'=>'2026-11-22',
            'delivery'=>['creative_required'=>true,'operations_required'=>true,'creative_manager_id'=>$creative->id,'operations_manager_id'=>$operations->id,
                'creative'=>['brief'=>'Design the campaign artwork and print files.','creative_type'=>config('creative.types')[0],'requested_deadline'=>'2026-11-20T16:00'],
                'operations'=>['plan'=>'Install the approved launch assets at the venue.','acceptance_criteria'=>'All installed assets signed off.','event_at'=>'2026-11-27T10:00','setup_due_at'=>'2026-11-26T16:00','location'=>'Accra']]];
        return compact('owner','creative','operations','payload');
    }

    public function test_reported_client_receipt_is_separate_from_portal_creation_and_creative_routing_and_is_audited(): void
    {
        $s=$this->setupProject();$s['payload']['delivery']['client_brief_received_at']='2026-11-10T09:00';
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        $p=CrmPipelineProject::findOrFail($id);$brief=CreativeRequest::firstOrFail();
        $this->assertSame('2026-11-10T09:00:00+00:00',$p->team_workflow['client_brief_received_at']);
        $this->getJson(route('portal.creative.show',$brief))->assertOk()->assertJsonPath('timeline_provenance.client_brief_received_at','2026-11-10T09:00:00+00:00')->assertJsonPath('timeline_provenance.creative_created_at','2026-11-18T12:00:00+00:00')->assertJsonPath('timeline_provenance.client_brief_received_recorded_by',$s['owner']->name);
        $s['payload']['delivery']['client_brief_received_at']='2026-11-11T09:00';
        $this->postJson(route('portal.crm.update',$p),$s['payload']+['revision'=>$p->revision])->assertOk();
        $this->assertTrue(PerformanceEvent::where('entity_type','crm_project')->where('entity_id',$id)->where('before->client_brief_received_at','2026-11-10T09:00:00+00:00')->where('after->client_brief_received_at','2026-11-11T09:00:00+00:00')->exists());
        $this->assertTrue(\App\Models\PerformanceOutbox::where('user_id',$s['creative']->id)->whereNotNull('dashboard_at')->exists());
        $this->assertTrue(\App\Models\PerformanceOutbox::where('user_id',$s['owner']->id)->whereNotNull('dashboard_at')->exists());
        $s['payload']['delivery']['client_brief_received_at']='2027-01-01T09:00';
        $this->postJson(route('portal.crm.update',$p),$s['payload']+['revision'=>$p->fresh()->revision])->assertUnprocessable();
    }
    public function test_creative_and_operations_requirements_are_independent_and_can_be_decided_later(): void
    {
        foreach([[true,false],[false,true],[false,false],[null,null]] as $index=>[$creative,$operations]){
            $s=$this->setupProject();$s['payload']['project_name']='Optional teams '.$index;
            $s['payload']['delivery']['creative_required']=$creative;$s['payload']['delivery']['operations_required']=$operations;
            if(!$creative){unset($s['payload']['delivery']['creative'],$s['payload']['delivery']['creative_manager_id']);}
            if(!$operations){unset($s['payload']['delivery']['operations'],$s['payload']['delivery']['operations_manager_id']);}
            $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
            $this->assertSame($creative===true,CreativeRequest::where('crm_project_id',$id)->exists());
            $this->assertSame($operations===true,OperationsHandover::where('project_id',$id)->exists());
            $this->assertFalse(\App\Models\PerformanceRegister::where('data->crm_project_id',$id)->exists());
        }
    }
    public function test_one_entry_routes_to_both_hods_and_preserves_author_and_submission_time(): void
    {
        $s=$this->setupProject('staff');
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertStatus(202)->json('submission_id');
        $this->assertDatabaseCount('creative_requests',0);$this->assertDatabaseCount('operations_handovers',0);
        $submitted=now();$this->travelTo(now()->addHour());
        $manager=$this->staff('client_relations','manager');
        $this->actingAs($manager)->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertOk();
        $project=CrmPipelineProject::firstOrFail();$brief=CreativeRequest::firstOrFail();$handover=OperationsHandover::firstOrFail();
        $this->assertSame($project->id,$brief->crm_project_id);$this->assertSame($project->id,$handover->project_id);
        $this->assertSame($s['owner']->id,$brief->requester_id);$this->assertSame($s['owner']->id,$handover->requester_id);
        $this->assertSame($s['creative']->id,$brief->reviewer_id);$this->assertNull($brief->assignee_id);$this->assertNull($handover->lead_id);
        $this->assertSame($submitted->toIso8601String(),$project->team_workflow['creative_requested_at']);
        $this->assertNotSame($project->team_workflow['creative_requested_at'],$project->team_workflow['creative_routed_at']);
        $this->assertDatabaseHas('performance_outbox',['user_id'=>$s['creative']->id,'title'=>'Project brief · Connected launch']);
        $this->assertDatabaseHas('performance_outbox',['user_id'=>$s['operations']->id,'title'=>'Project handover · Connected launch']);
        $this->actingAs($s['owner'])->getJson(route('portal.project-journey.accountability',$project))->assertOk()->assertSee('Connected project accountability')->assertSee('Original requested deadlines');
    }

    public function test_hods_can_assign_the_linked_work_to_themselves_and_other_team_sees_responsibilities(): void
    {
        $s=$this->setupProject();
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        $brief=CreativeRequest::firstOrFail();$h=OperationsHandover::firstOrFail();
        $brief=app(CreativeWorkflow::class)->plan($brief,$s['creative'],['revision'=>$brief->revision,'assignee_id'=>$s['creative']->id,'reviewer_id'=>$s['creative']->id,
            'planned_start'=>'2026-11-19 09:00','internal_eta'=>'2026-11-20 16:00','estimated_hours'=>8,'acknowledge_warnings'=>true]);
        $this->actingAs($s['operations'])->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'assign','lead_id'=>$s['operations']->id,'reason'=>'HOD is handling the execution'])->assertOk();
        $this->assertSame($s['creative']->id,$brief->assignee_id);$this->assertSame($s['operations']->id,$h->fresh()->lead_id);
        $this->actingAs($s['owner'])->getJson(route('portal.project-journey.accountability',$id))->assertOk()->assertSee($s['creative']->name)->assertSee($s['operations']->name);
        $this->actingAs($s['operations'])->getJson(route('portal.project-journey.accountability',$id))->assertOk()->assertSee($s['owner']->name);
    }

    public function test_creative_stats_use_hod_deadlines_and_all_pages_including_missing_deliveries(): void
    {
        $s=$this->setupProject();
        foreach([true,false,null] as $index=>$onTime) {
            $brief=app(CreativeWorkflow::class)->create($s['owner'],['title'=>'Deadline test '.$index,'brief'=>'Create the required output','creative_type'=>config('creative.types')[0],'priority'=>'Medium','requested_deadline'=>'2026-11-17 10:00:00']);
            $due=Carbon::parse('2026-11-18 10:00:00');
            $brief->update(['assignee_id'=>$s['creative']->id,'internal_eta'=>$due,'first_submitted_at'=>$onTime===null?null:($onTime?'2026-11-18 09:00:00':'2026-11-18 11:00:00')]);
            if($onTime!==null)$brief->updates()->create(['kind'=>'in_review','author_id'=>$s['creative']->id,'body'=>'Submitted','metadata'=>['internal_eta'=>$due->toIso8601String()]]);
        }
        $this->actingAs($s['creative'])->getJson(route('portal.creative.data',['per_page'=>1,'from'=>'2026-11-16','to'=>'2026-11-22']))
            ->assertOk()->assertJsonPath('pagination.total',3)->assertJsonCount(1,'items')->assertJsonPath('delivery_metrics.due',3)->assertJsonPath('delivery_metrics.on_time',1)->assertJsonPath('delivery_metrics.on_time_credit',33.3);
    }

    public function test_executive_auditor_can_read_restricted_connected_work_without_assignment_or_approval_rights(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        $brief=CreativeRequest::firstOrFail();$brief->update(['visibility'=>'restricted']);
        $auditor=$this->staff('executive');$auditor->update(['permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION,User::HR_FINANCE_AUDIT_PERMISSION]]);
        $this->actingAs($auditor)->getJson(route('portal.project-journey.accountability',$id))->assertOk()->assertSee('Creative HOD delivery commitment');
        $this->getJson(route('portal.creative.show',$brief))->assertOk()->assertJsonPath('can_plan',false);
        $this->postJson(route('portal.creative.plan',$brief),['revision'=>$brief->revision,'assignee_id'=>$auditor->id,'planned_start'=>'2026-11-19 09:00','internal_eta'=>'2026-11-20 16:00','estimated_hours'=>4])->assertForbidden();
    }

    public function test_open_task_dates_can_be_aligned_with_recorded_hod_plans_without_losing_history(): void
    {
        $s=$this->setupProject();
        $this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated();
        $brief=CreativeRequest::firstOrFail();
        $brief=app(CreativeWorkflow::class)->plan($brief,$s['creative'],['revision'=>$brief->revision,'assignee_id'=>$s['creative']->id,'planned_start'=>'2026-11-19 09:00','internal_eta'=>'2026-11-20 16:00','estimated_hours'=>4,'acknowledge_warnings'=>true]);
        $brief->task->update(['due_on'=>'2026-11-19']);
        $this->artisan('creative:sync-hod-deadlines')->assertSuccessful();
        $this->assertSame('2026-11-19',$brief->task->fresh()->due_on->toDateString());
        $this->artisan('creative:sync-hod-deadlines',['--apply'=>true])->assertSuccessful();
        $this->assertSame('2026-11-20',$brief->task->fresh()->due_on->toDateString());
        $this->artisan('creative:sync-hod-deadlines',['--apply'=>true])->assertSuccessful();
        $this->assertSame(1,PerformanceEvent::where('action','hod_deadline_synchronised')->count());
    }

    public function test_requirements_can_be_added_later_without_duplicate_workflows_and_initial_deadline_is_retained(): void
    {
        $s=$this->setupProject();$without=$s['payload'];unset($without['delivery']);
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$without)->assertCreated()->json('id');
        $p=CrmPipelineProject::findOrFail($id);$this->assertNull($p->team_workflow);
        $this->postJson(route('portal.crm.update',$id),$s['payload']+['revision'=>$p->revision])->assertOk();
        $p->refresh();$first=$p->team_workflow['creative_original_deadline'];
        $payload=$s['payload'];$payload['delivery']['creative']['requested_deadline']='2026-11-23T16:00';$payload['delivery']['change_note']='Client supplied an updated delivery request';
        $this->postJson(route('portal.crm.update',$id),$payload+['revision'=>$p->revision])->assertOk();
        $this->assertDatabaseCount('creative_requests',1);$this->assertDatabaseCount('operations_handovers',1);
        $this->assertSame($first,$p->fresh()->team_workflow['creative_original_deadline']);
        $this->assertTrue(PerformanceEvent::where('entity_type','creative_request')->where('action','project_request_changed')->exists());
        $this->postJson(route('portal.crm.update',$id),$payload+['revision'=>$p->revision])->assertConflict();
    }

    public function test_optional_teams_can_be_skipped_and_outsiders_cannot_read_the_project(): void
    {
        $s=$this->setupProject();$s['payload']['delivery']=['creative_required'=>false,'operations_required'=>false];
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        $this->assertDatabaseCount('creative_requests',0);$this->assertDatabaseCount('operations_handovers',0);
        $this->getJson(route('portal.project-journey.accountability',$id))->assertOk()->assertSee('Not required');
        $this->get(route('portal.project-journey',['project'=>$id]))->assertOk()->assertSee('Client Relations final project closeout pending');
        CrmPipelineProject::findOrFail($id)->update(['stage'=>'project_completed']);
        $this->get(route('portal.project-journey',['project'=>$id]))->assertOk()->assertSee('Client Relations project completion recorded');
        $this->actingAs($this->staff('client_relations'))->getJson(route('portal.project-journey.accountability',$id))->assertNotFound();
    }

    public function test_invalid_manager_or_incomplete_brief_does_not_create_a_partial_project(): void
    {
        $s=$this->setupProject();$payload=$s['payload'];$payload['delivery']['creative_manager_id']=$s['operations']->id;
        $this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$payload)->assertUnprocessable()->assertJsonValidationErrors('delivery.creative_manager_id');
        $this->assertDatabaseCount('crm_pipeline_projects',0);
        $payload=$s['payload'];unset($payload['delivery']['creative']['brief']);
        $this->postJson(route('portal.crm.store'),$payload)->assertUnprocessable()->assertJsonValidationErrors('delivery.creative.brief');
        $this->assertDatabaseCount('creative_requests',0);
    }

    public function test_nested_requests_cannot_spoof_workflow_state_assignee_or_project_links(): void
    {
        $s=$this->setupProject();$payload=$s['payload'];
        $payload['delivery']['operations']['stage']='completed';
        $this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$payload)->assertUnprocessable();
        $payload=$s['payload'];$payload['delivery']['creative']['assignee_id']=$s['creative']->id;
        $this->postJson(route('portal.crm.store'),$payload)->assertUnprocessable();
        $this->assertDatabaseCount('crm_pipeline_projects',0);$this->assertDatabaseCount('operations_handovers',0);
    }

    public function test_changing_an_execution_plan_requires_fresh_signoffs_without_erasing_history(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        $h=OperationsHandover::firstOrFail();$h->update(['stage'=>'planning','cr_signoff_at'=>now(),'ops_signoff_at'=>now(),'meeting_held_at'=>now(),'acknowledged_at'=>now()]);
        $p=CrmPipelineProject::findOrFail($id);$payload=$s['payload'];$payload['delivery']['operations']['location']='Kumasi';
        $this->postJson(route('portal.crm.update',$p),$payload+['revision'=>$p->revision])->assertOk();
        $h->refresh();$this->assertNull($h->cr_signoff_at);$this->assertNull($h->ops_signoff_at);$this->assertSame('handover_meeting',$h->stage);
        $this->assertTrue(PerformanceEvent::where('entity_type','operations_handover')->where('action','project_request_changed')->exists());
    }
}
