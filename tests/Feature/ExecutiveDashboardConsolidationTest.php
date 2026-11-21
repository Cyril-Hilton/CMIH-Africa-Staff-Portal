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
            $this->actingAs($executive)->get(route('portal.cvo'))->assertRedirect(route('dashboard'));
        }
        $admin=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('dashboard'));
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
                    ->assertOk()->assertSee($label)->assertSee('Weekly Department Reports');
            }
        }
    }

    public function test_all_departments_renders_each_current_dashboard_for_executive_review(): void
    {
        foreach ([['access_role'=>'super_admin'], ['access_role'=>'staff','job_level'=>'CVO','position_title'=>'CVO'], ['access_role'=>'staff','department'=>'executive','permissions_matrix'=>[User::EXECUTIVE_ACTIVITY_PERMISSION, User::HR_FINANCE_AUDIT_PERMISSION]]] as $role) {
            $viewer=User::factory()->create($role+['status'=>'active']);
            $response=$this->actingAs($viewer)->get(route('dashboard',['weekly_department'=>'all']))->assertOk();
            foreach (array_keys(config('performance.departments')) as $department) {
                $response->assertSee('id="performance-panel-'.$department.'"',false);
            }
            foreach (['data-brands-reports','data-creative-studio','data-operations-reporting','id="finance-workspace"','aria-label="HR and Admin live registers"','data-crm-centre'] as $marker) {
                $response->assertSee($marker,false);
            }
            $response->assertDontSee('Open weekly reports &amp; activities',false);
            $response->assertDontSee('Historical Weekly Consolidated',false);
            $this->get(route('portal.dashboard.live',['section'=>'weekly-consolidated-table','weekly_department'=>'all']))->assertOk()->assertSee('data-creative-studio',false)->assertSee('id="finance-workspace"',false);
        }
    }

    public function test_combined_dashboard_live_refresh_keeps_the_selected_finance_reporting_dates(): void
    {
        $viewer=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
        foreach (['week'=>['2026-10-12','2026-10-18'], 'month'=>['2026-10-01','2026-10-31'], 'year'=>['2026-01-01','2026-12-31']] as $mode=>[$from,$to]) {
            $this->actingAs($viewer)->get(route('portal.dashboard.live',['weekly_department'=>'all','report_period'=>$mode,'report_date'=>'2026-10-14']))
                ->assertOk()->assertSee('name="finance_from" value="'.$from.'"',false)->assertSee('name="finance_to" value="'.$to.'"',false);
        }
    }

    public function test_all_department_view_keeps_hr_and_finance_restricted_for_ordinary_staff(): void
    {
        $staff=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUserIsClockedIn::class)->actingAs($staff)
            ->get(route('dashboard',['weekly_department'=>'all']))->assertOk()
            ->assertSee('data-creative-studio',false)
            ->assertSee('Financial records are restricted to Finance and authorised reviewers.')
            ->assertSee('HR records are restricted.')
            ->assertDontSee('id="finance-workspace"',false)
            ->assertDontSee('aria-label="HR and Admin live registers"',false);
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
