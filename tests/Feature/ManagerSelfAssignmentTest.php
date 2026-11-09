<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,Task};
use Illuminate\Foundation\Testing\RefreshDatabase;
class ManagerSelfAssignmentTest extends TestCase {
 use RefreshDatabase;
 public function test_line_managers_in_every_department_can_assign_daily_and_weekly_work_to_themselves():void {
  $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class);
  foreach(['brands_marketing','client_relations','operations_projects','creatives','finance','hr_admin'] as $department){
   foreach(['access_role','job_level'] as $roleField){
    $manager=User::factory()->create(['department'=>$department,'status'=>'active','access_role'=>'staff','job_level'=>'executive',$roleField=>'manager']);
    $this->actingAs($manager)->get(route('portal.tasks',['view'=>'create']))->assertOk()->assertViewHas('canAssignOthers',true)->assertViewHas('requiresCompletionManager',false)->assertViewHas('teamMembers',fn($team)=>$team->contains('id',$manager->id));
    $this->post(route('portal.tasks.store'),['title'=>'Own daily task '.$department,'details'=>'Manager produces work too','priority'=>'medium','assign_to'=>$manager->id])->assertSessionHasNoErrors();
    $task=Task::where('assigned_by',$manager->id)->sole();$this->assertSame($manager->id,$task->assigned_to);$this->assertSame($department,$task->department);
    $this->post(route('portal.dashboard.weekly-consolidated.store'),['department'=>$department,'week_start'=>now()->startOfWeek()->toDateString(),'lead_staff_id'=>$manager->id,'deliverables'=>'Own weekly plan','status'=>'Planned'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('weekly_consolidated_items',['department'=>$department,'lead_staff_id'=>$manager->id,'created_by'=>$manager->id]);
   }
  }
 }
}
