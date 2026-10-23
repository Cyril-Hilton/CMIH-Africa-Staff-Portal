<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\ExecutiveDashboardSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_executive_landings_redirect_to_one_dashboard(): void
    {
        foreach ([['access_role'=>'super_admin'],['access_role'=>'staff','position_title'=>'CVO','job_level'=>'CVO']] as $role) {
            $executive=User::factory()->create($role+['status'=>'active']);
            $this->actingAs($executive)->get(route('portal.cvo'))->assertRedirect(route('dashboard',['weekly_department'=>'all']));
        }
        $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('dashboard',['weekly_department'=>'all']));
        $this->assertCount(4,app(ExecutiveDashboardSummary::class)->build($admin));
    }

    public function test_ordinary_staff_cannot_load_private_executive_summary_or_legacy_cvo(): void
    {
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'brands_marketing']);
        $this->assertSame([],app(ExecutiveDashboardSummary::class)->build($staff));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($staff)->get(route('portal.cvo'))->assertForbidden();
    }
    public function test_both_executive_roles_can_present_every_department_on_main_dashboard(): void
    {
        foreach ([['access_role'=>'super_admin'],['access_role'=>'staff','job_level'=>'CVO','position_title'=>'CVO']] as $role) {
            $executive=User::factory()->create($role+['status'=>'active']);
            foreach (config('performance.departments') as $department=>$label) {
                $this->actingAs($executive)->get(route('dashboard',['weekly_department'=>$department]))
                    ->assertOk()->assertSee($label)->assertSee('Weekly Departmental Performance');
            }
        }
    }

    public function test_location_projection_is_bounded_private_and_uses_only_actual_gps_records(): void
    {
        $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'finance','last_login_ip'=>'8.8.8.8']);
        $outsider=User::factory()->create(['status'=>'active','access_role'=>'merchandiser','department'=>'merchandisers']);
        foreach (range(1,35) as $day) {
            \App\Models\Attendance::create(['user_id'=>$staff->id,'clock_in_at'=>now()->subMinutes($day),'latitude'=>5.55,'longitude'=>-0.11,'status'=>'On Time','daily_objective'=>'PRIVATE OBJECTIVE','remote_notes'=>'PRIVATE REMOTE NOTE']);
        }
        \App\Models\Attendance::create(['user_id'=>$admin->id,'clock_in_at'=>now(),'latitude'=>null,'longitude'=>null,'status'=>'On Time','daily_objective'=>'No GPS']);
        \App\Models\Attendance::create(['user_id'=>$outsider->id,'clock_in_at'=>now(),'latitude'=>9.99,'longitude'=>8.88,'status'=>'On Time','daily_objective'=>'Field attendance']);
        $rows=app(ExecutiveDashboardSummary::class)->locations($admin);
        $this->assertCount(30,$rows);
        $this->assertSame(5.55,$rows[0]['latitude']);
        $this->assertStringNotContainsString('9.99',json_encode($rows));
        $this->assertStringNotContainsString('PRIVATE',json_encode($rows));
        $this->assertStringNotContainsString('8.8.8.8',json_encode($rows));
        $this->assertSame([],app(ExecutiveDashboardSummary::class)->locations($staff));
        $this->actingAs($staff);
        $this->assertStringNotContainsString('openstreetmap',view('portal.partials.executive-dashboard-summary')->render());
        $this->actingAs($admin);
        $html=view('portal.partials.executive-dashboard-summary')->render();
        $this->assertStringContainsString('Open Recorded Location',$html);
        $this->assertStringNotContainsString('<iframe',$html);
        $this->assertStringNotContainsString('unpkg.com',$html);
    }

}
