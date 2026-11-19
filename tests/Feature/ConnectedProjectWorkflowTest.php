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

    public function test_weekly_modal_and_edit_form_offer_all_request_fields_and_explain_automatic_timestamps(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');
        foreach([route('dashboard',['weekly_department'=>'client_relations']),route('portal.crm.index')] as $url){
            $page=$this->get($url)->assertOk();
            foreach(['delivery[client_brief_received_at]','delivery[creative][brief]','delivery[creative][requested_deadline]','delivery[creative][reference_url]','delivery[operations][plan]','delivery[operations][acceptance_criteria]','delivery[operations][setup_due_at]','delivery[operations][event_at]','delivery[operations][execution_due_at]','delivery[operations][plan_url]','delivery[operations][location]'] as $name)$page->assertSee('name="'.$name.'"',false);
            $page->assertSee('cannot be backdated');
        }
        $this->get(route('portal.crm.index'))->assertSee('Current team commitments, request dates')->assertSee(route('portal.project-journey.accountability',$id),false);
    }

    public function test_direct_linked_creative_deadline_is_not_mislabelled_as_a_client_relations_request_in_edit_form(): void
    {
        $s=$this->setupProject();$payload=$s['payload'];unset($payload['delivery']);
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$payload)->assertCreated()->json('id');$project=CrmPipelineProject::findOrFail($id);
        $brief=app(CreativeWorkflow::class)->create($s['creative'],['title'=>'Direct artwork brief','brief'=>'Design the client artwork','creative_type'=>config('creative.types')[0],'priority'=>'Medium','requested_deadline'=>'2026-11-24T16:00']);
        app(\App\Services\Creative\CreativeProjectConnection::class)->update($brief,$s['creative'],$brief->revision,$id);
        $service=app(\App\Services\ConnectedProjectWorkflow::class);
        $this->assertNull($service->formValues($project)['creative']['requested_deadline']);
        app(\App\Services\Creative\CreativeProjectConnection::class)->update($brief->fresh(),$s['creative'],$brief->fresh()->revision,$id,'2026-11-23T12:00');
        $this->assertSame('2026-11-23T12:00',$service->formValues($project)['creative']['requested_deadline']);
        $summary=app(\App\Services\ConnectedProjectSummary::class)->build($project,$s['owner']);
        $this->assertSame('2026-11-23 12:00',$summary['work'][0]['requested']->format('Y-m-d H:i'));
    }

    public function test_updated_reference_links_and_hod_tag_round_trip_without_changing_creative_schedule(): void
    {
        $s=$this->setupProject();$s['payload']['delivery']['creative']['reference_url']='https://example.com/original';$s['payload']['delivery']['operations']['plan_url']='https://example.com/plan';
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');$p=CrmPipelineProject::findOrFail($id);$brief=CreativeRequest::firstOrFail();
        $brief=app(CreativeWorkflow::class)->plan($brief,$s['creative'],['revision'=>$brief->revision,'assignee_id'=>$s['creative']->id,'planned_start'=>'2026-11-19T09:00','internal_eta'=>'2026-11-21T10:00','estimated_hours'=>8,'acknowledge_warnings'=>true]);
        $newHod=$this->staff('creatives','manager');$payload=$s['payload'];$payload['delivery']['creative']['reference_url']='https://example.com/revised';$payload['delivery']['creative_manager_id']=$newHod->id;$payload['delivery']['operations']['plan_url']='https://example.com/revised-plan';
        $this->postJson(route('portal.crm.update',$p),$payload+['revision'=>$p->revision])->assertOk();
        $brief->refresh();$this->assertSame($newHod->id,$brief->reviewer_id);$this->assertSame($s['creative']->id,$brief->assignee_id);$this->assertSame('2026-11-21 10:00',$brief->internal_eta->format('Y-m-d H:i'));
        $values=app(\App\Services\ConnectedProjectWorkflow::class)->formValues($p->fresh());$this->assertSame('https://example.com/revised',$values['creative']['reference_url']);$this->assertSame('https://example.com/revised-plan',$values['operations']['plan_url']);
        $this->assertDatabaseHas('performance_outbox',['user_id'=>$newHod->id,'title'=>'Creative brief reference updated']);
        $this->assertDatabaseHas('performance_events',['entity_type'=>'creative_request','action'=>'project_manager_changed','entity_id'=>$brief->id]);
        $payload['delivery']['creative']['reference_url']=null;$p->refresh();$this->postJson(route('portal.crm.update',$p),$payload+['revision'=>$p->revision])->assertOk();$this->assertNull(app(\App\Services\ConnectedProjectWorkflow::class)->formValues($p->fresh())['creative']['reference_url']);
    }

    public function test_approval_delay_is_visible_separately_from_original_request_in_both_departments(): void
    {
        $s=$this->setupProject('staff');$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertStatus(202)->json('submission_id');$sent=now()->toIso8601String();$this->travelTo(now()->addHour());$published=now()->toIso8601String();
        $this->actingAs($this->staff('client_relations','manager'))->postJson(route('portal.crm.submissions.review',$id),['revision'=>1,'decision'=>'approve'])->assertOk();
        $brief=CreativeRequest::firstOrFail();$this->actingAs($s['owner'])->getJson(route('portal.creative.show',$brief))->assertOk()->assertJsonPath('timeline_provenance.creative_requested_at',$sent)->assertJsonPath('timeline_provenance.creative_routed_at',$published)->assertJsonPath('timeline_provenance.creative_requested_by',$s['owner']->name);
        $this->getJson(route('portal.operations.handovers.data'))->assertOk()->assertJsonPath('items.0.request_submitted_at',$sent)->assertJsonPath('items.0.routed_at',$published);
    }

    public function test_operations_summary_separates_setup_activation_and_execution_and_does_not_treat_setup_as_completion_deadline(): void
    {
        $s=$this->setupProject();$s['payload']['delivery']['operations']['execution_due_at']='2026-11-28T18:00';
        $id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');$h=OperationsHandover::firstOrFail();
        $this->actingAs($s['operations'])->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'assign','lead_id'=>$s['operations']->id,'agreed_setup_due_at'=>'2026-11-25T16:00','agreed_execution_due_at'=>'2026-11-28T16:00','reason'=>'HOD records both commitments'])->assertOk();
        $this->travelTo(Carbon::parse('2026-11-27T12:00'));$summary=app(\App\Services\ConnectedProjectSummary::class)->build(CrmPipelineProject::find($id),$s['owner']);$work=collect($summary['work'])->firstWhere('team','Operations');
        $this->assertFalse((bool)$work['late']);$this->assertSame('2026-11-25 16:00',$work['agreed']->format('Y-m-d H:i'));$this->assertSame('2026-11-28 16:00',$work['agreed_execution']->format('Y-m-d H:i'));$this->assertSame('2026-11-27 10:00',$work['activation']->format('Y-m-d H:i'));
        $event=collect($summary['timeline'])->firstWhere('label','Operations HOD execution commitment');$this->assertSame($s['operations']->name,$event['owner']);
        $this->actingAs($s['owner'])->getJson(route('portal.project-journey.accountability',$id))->assertOk()->assertSee('Operations HOD setup commitment')->assertSee('Operations HOD execution commitment');
    }

    public function test_revised_execution_request_clears_old_completion_evidence_but_preserves_hod_commitment_and_audit(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');$h=OperationsHandover::firstOrFail();
        $h->update(['stage'=>'awaiting_acceptance','meeting_held_at'=>now(),'cr_signoff_at'=>now(),'ops_signoff_at'=>now(),'handover_approved_at'=>now(),'submitted_at'=>now(),'execution_completed_at'=>now(),'submission_evidence'=>'https://example.com/old-completion','agreed_execution_due_at'=>'2026-11-28T16:00']);
        $p=CrmPipelineProject::find($id);$payload=$s['payload'];$payload['delivery']['operations']['location']='Updated venue';$this->postJson(route('portal.crm.update',$id),$payload+['revision'=>$p->revision])->assertOk();$h->refresh();
        $this->assertNull($h->submitted_at);$this->assertNull($h->execution_completed_at);$this->assertNull($h->submission_evidence);$this->assertNull($h->handover_approved_at);$this->assertSame('2026-11-28 16:00',$h->agreed_execution_due_at->format('Y-m-d H:i'));
        $this->assertTrue(PerformanceEvent::where('entity_type','operations_handover')->where('action','project_request_changed')->where('before->submission_evidence','https://example.com/old-completion')->exists());
    }

    public function test_both_dashboard_returns_show_hod_comments_commitments_and_client_feedback_on_the_same_project(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');$brief=CreativeRequest::firstOrFail();$h=OperationsHandover::firstOrFail();
        $brief=app(CreativeWorkflow::class)->plan($brief,$s['creative'],['revision'=>$brief->revision,'assignee_id'=>$s['creative']->id,'planned_start'=>'2026-11-19T09:00','internal_eta'=>'2026-11-21T10:00','estimated_hours'=>8,'acknowledge_warnings'=>true,'update'=>'Creative HOD: final files will follow approved dimensions.']);
        $this->actingAs($s['operations'])->postJson(route('portal.operations.handovers.action',$h),['revision'=>$h->revision,'action'=>'assign','lead_id'=>$s['operations']->id,'agreed_setup_due_at'=>'2026-11-25T16:00','agreed_execution_due_at'=>'2026-11-28T16:00','reason'=>'Operations HOD: venue access confirmed for installation.'])->assertOk();
        $brief->updates()->create(['author_id'=>$s['owner']->id,'kind'=>'comment','body'=>'Client Relations: client approved the revised dimensions.']);
        $url=route('portal.project-journey.accountability',$id);
        foreach([$s['owner'],$s['creative'],$s['operations']] as $viewer){
            $this->actingAs($viewer)->getJson($url)->assertOk()->assertSee('Creative HOD: final files')->assertSee('Operations HOD: venue access')->assertSee('Client Relations: client approved')->assertSee('21 Nov 2026')->assertSee('28 Nov 2026');
        }
        $this->actingAs($s['owner'])->getJson(route('portal.crm.centre.data',['from'=>'2026-11-16','to'=>'2026-11-22']))->assertOk()->assertJsonPath('clients.0.projects.0.connected_url',$url);
        $outsider=$this->staff('client_relations');$brief->update(['visibility'=>'restricted']);$this->actingAs($outsider)->getJson($url)->assertNotFound();
    }

    public function test_unchanged_client_manager_tag_does_not_undo_a_hod_review_assignment(): void
    {
        $s=$this->setupProject();$id=$this->actingAs($s['owner'])->postJson(route('portal.crm.store'),$s['payload'])->assertCreated()->json('id');$p=CrmPipelineProject::find($id);$brief=CreativeRequest::firstOrFail();$reviewer=$this->staff('creatives','manager');
        $brief=app(CreativeWorkflow::class)->plan($brief,$s['creative'],['revision'=>$brief->revision,'assignee_id'=>$s['creative']->id,'reviewer_id'=>$reviewer->id,'planned_start'=>'2026-11-19T09:00','internal_eta'=>'2026-11-21T10:00','estimated_hours'=>8,'acknowledge_warnings'=>true]);
        $this->postJson(route('portal.crm.update',$id),$s['payload']+['revision'=>$p->revision])->assertOk();$this->assertSame($reviewer->id,$brief->fresh()->reviewer_id);
    }
}
