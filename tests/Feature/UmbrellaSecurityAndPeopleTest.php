<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Task;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class UmbrellaSecurityAndPeopleTest extends TestCase
{
    use RefreshDatabase;
    public function test_display_names_never_grant_hr_or_manager_permissions(): void {
        $target=User::factory()->create(['status'=>'active']);
        foreach(['Cyril Hilton','Curtis Barnor'] as $name){
            $staff=User::factory()->create(['name'=>$name,'status'=>'active','access_role'=>'staff','department'=>'creatives','position_title'=>'Executive','job_level'=>'executive']);
            $this->assertFalse($staff->hasFullHrAccess());$this->assertFalse($staff->isLineManager());$this->assertFalse(User::canEditUser($staff,$target));
            $this->actingAs($staff)->get(route('portal.people',['section'=>'records']))->assertForbidden();
        }
    }
    public function test_manager_cannot_promote_a_subordinate_into_executive_or_hr_access(): void {
        $manager=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>'creatives']);
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives','line_manager_id'=>$manager->id]);
        $this->actingAs($manager)->post(route('portal.directory.privileges',$staff),['access_role'=>'manager','department'=>'creatives','position_title'=>'CVO'])->assertSessionHasErrors('position_title');
        $this->post(route('portal.directory.privileges',$staff),['access_role'=>'manager','department'=>'hr_admin','position_title'=>'Manager'])->assertSessionHasErrors('department');
        $this->assertSame('staff',$staff->fresh()->access_role);
    }
    public function test_hr_cannot_target_protected_executives_or_assign_system_super_admin_level(): void {
        Mail::fake();
        $hr=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>'hr_admin']);
        $executive=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff']);
        foreach(['suspend','approve','reset','department'] as $action)$this->actingAs($hr)->post(route('admin.users.'.$action,$executive),['department'=>'finance'])->assertForbidden();
        $this->post(route('admin.users.permissions',$staff),['job_level'=>'super_admin','permissions'=>[]])->assertSessionHasErrors('job_level');
        $this->assertSame('active',$executive->fresh()->status);Mail::assertNothingSent();
    }
    public function test_people_hub_shows_only_permitted_tabs_and_preserves_private_headers(): void {
        $staff=User::factory()->create(['status'=>'active','department'=>'creatives','access_role'=>'staff']);
        $this->actingAs($staff)->get(route('portal.people'))->assertOk()->assertSee('Team Directory')->assertDontSee('Accounts &amp; Access',false)->assertDontSee('Staff Records');
        $this->get(route('portal.people',['section'=>'accounts']))->assertForbidden();
        $hr=User::factory()->create(['status'=>'active','department'=>'hr_admin','access_role'=>'manager']);
        foreach(['directory','records','accounts'] as $section)$this->actingAs($hr)->get(route('portal.people',['section'=>$section]))->assertOk()->assertSee('Team Directory')->assertSee('Staff Records')->assertSee('Accounts &amp; Access',false)->assertHeader('Cache-Control','no-store, private');
    }
    public function test_suspension_blocks_login_without_removing_old_work_or_historical_team_filters(): void {
        $manager=User::factory()->create(['status'=>'active','department'=>'creatives','access_role'=>'super_admin']);
        $staff=User::factory()->create(['status'=>'active','department'=>'creatives','access_role'=>'staff','line_manager_id'=>$manager->id]);
        $task=Task::create(['title'=>'Previous employee output','assigned_to'=>$staff->id,'assigned_by'=>$manager->id,'department'=>'creatives','status'=>'Done','priority'=>'Medium']);
        $item=WeeklyConsolidatedItem::create(['department'=>'creatives','week_start'=>'2026-10-05','week_end'=>'2026-10-09','campaign_name'=>'Retained historical output','deliverables'=>'Previous employee output','lead_staff_id'=>$staff->id,'reviewer_id'=>$manager->id,'workflow_state'=>'review','status'=>'In Progress','created_by'=>$manager->id,'updated_by'=>$manager->id]);
        $this->actingAs($manager)->post(route('admin.users.suspend',$staff))->assertRedirect();
        $this->assertSame('suspended',$staff->fresh()->status);$this->assertDatabaseHas('tasks',['id'=>$task->id,'assigned_to'=>$staff->id]);
        $response=$this->get(route('portal.performance.index',['view'=>'commitments','department'=>'creatives','team_lead_id'=>$manager->id,'from'=>'2026-10-01','to'=>'2026-10-31']))->assertOk()->assertSee('Retained historical output');
        $this->assertContains($staff->id,$response->viewData('staff')->pluck('id')->all());
        $this->assertSame($staff->name,$item->fresh()->leadStaff->name);
        auth()->logout();
        $this->post('/login',['email'=>$staff->email,'password'=>'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_cvo_can_archive_and_restore_staff_without_losing_their_work(): void {
        $cvo=User::factory()->create(['status'=>'active','access_role'=>'staff','position_title'=>'CVO']);
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff']);
        $task=Task::create(['title'=>'Retained after archive','assigned_to'=>$staff->id,'assigned_by'=>$cvo->id,'department'=>'creatives','status'=>'Done','priority'=>'Medium']);
        $this->actingAs($cvo)->delete(route('admin.users.destroy',$staff))->assertRedirect();
        $this->assertSame('archived',$staff->fresh()->status);
        $this->assertDatabaseHas('tasks',['id'=>$task->id,'assigned_to'=>$staff->id]);
        $this->post(route('admin.users.restore',$staff))->assertRedirect();
        $this->assertSame('active',$staff->fresh()->status);
    }
}
