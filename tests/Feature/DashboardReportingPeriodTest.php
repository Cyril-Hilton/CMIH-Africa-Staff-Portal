<?php

namespace Tests\Feature;

use App\Models\{HrDashboardActivity, Task, User, WeeklyConsolidatedItem};
use App\Services\{BrandsDashboard, DepartmentWeeklyPresentation, HrDashboard};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardReportingPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_department_requests_share_the_selected_period_and_retain_history(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-09 12:00:00', 'Africa/Accra'));
        $user = User::factory()->create(['access_role'=>'super_admin', 'status'=>'active']);
        $this->actingAs($user);
        foreach (array_keys(config('performance.departments')) as $department) {
            foreach (['2026-10-26','2026-11-02','2026-11-09'] as $date) {
                WeeklyConsolidatedItem::create(['department'=>$department, 'workflow_state'=>'legacy', 'week_start'=>$date,
                    'week_end'=>\Carbon\Carbon::parse($date)->endOfWeek()->toDateString(), 'campaign_name'=>'Period '.$date,
                    'deliverables'=>'Saved original '.$date, 'status'=>'In Progress', 'created_by'=>$user->id]);
            }
            foreach (['week'=>['2026-11-09','2026-11-15',1], 'month'=>['2026-11-01','2026-11-30',2], 'year'=>['2026-01-01','2026-12-31',3]] as $mode=>[$from,$to,$count]) {
                $response = $this->get(route('dashboard', ['weekly_department'=>$department, 'report_period'=>$mode, 'report_date'=>'2026-11-09']));
                $response->assertOk()->assertSee('Apply reporting period')->assertSee('report_date=');
                $this->assertSame($from, request('weekly_from'));
                $this->assertSame($to, request('weekly_to'));
                $this->assertSame($mode, request('brands_period'));
                $this->assertSame($mode, request('hr_period'));
                $this->assertSame($count, app(DepartmentWeeklyPresentation::class)->build($department,$from,$to)->total());
            }
            $this->get(route('dashboard',['weekly_department'=>$department,'report_period'=>'week','report_date'=>'2026-11-02']))->assertOk();
            $this->assertSame(1, app(DepartmentWeeklyPresentation::class)->build($department,request('weekly_from'),request('weekly_to'))->total());
        }
        $this->assertDatabaseCount('weekly_consolidated_items',18);
    }

    public function test_task_date_does_not_change_department_reporting_period(): void
    {
        $user=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        foreach (['2026-10-20','2026-11-04'] as $date) { $task=Task::create(['title'=>'Daily '.$date,'department'=>'finance','assigned_to'=>$user->id,'assigned_by'=>$user->id,'status'=>'Open','priority'=>'Medium','created_at'=>$date.' 10:00:00']); $task->forceFill(['created_at'=>$date.' 10:00:00'])->save(); }
        $response=$this->actingAs($user)->get(route('dashboard',['weekly_department'=>'finance','report_period'=>'month','report_date'=>'2026-11-09','mega_date'=>'2026-10-20']));
        $response->assertOk()->assertViewHas('departmentTables',fn($tables)=>$tables['finance']->total()===1);
        $this->assertSame('Daily 2026-10-20',$response->viewData('departmentTables')['finance']->first()->title);
        $this->assertSame('2026-11-01',request('weekly_from'));
        $this->get(route('dashboard',['weekly_department'=>'finance']))->assertOk()->assertViewHas('departmentTables',fn($tables)=>$tables['finance']->total()===2);
    }

    public function test_hr_keeps_open_activities_and_excludes_finished_old_and_future_entries(): void
    {
        $user=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        foreach ([['Open follow-up','2026-10-19','In Progress'],['Old completed','2026-10-19','Completed'],['Future entry','2027-01-01','Not Started']] as [$activity,$date,$status]) {
            HrDashboardActivity::create(['tab'=>'operations','category'=>'Office Management','activity'=>$activity,'opened_on'=>$date,'deadline'=>$date,'status'=>$status,'assigned_to'=>$user->id,'created_by'=>$user->id]);
        }
        $this->actingAs($user)->get(route('dashboard',['weekly_department'=>'hr_admin','hr_tab'=>'operations','report_period'=>'week','report_date'=>'2026-11-09']))
            ->assertOk()->assertSee('Open follow-up')->assertDontSee('Old completed')->assertDontSee('Future entry');
        $this->assertSame('week', request('hr_period'));
    }
    public function test_task_date_shortcuts_clear_stale_pages_and_conflicting_creative_date():void
    {
        $user=User::factory()->create(['access_role'=>'super_admin','status'=>'active']);
        $response=$this->actingAs($user)->get(route('dashboard',['weekly_department'=>'brands_marketing','report_period'=>'month','report_date'=>'2026-11-09','mega_brands_marketing_page'=>9,'mega_finance_page'=>4,'creative_day'=>'2026-10-01']));
        $response->assertOk();$dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$xpath=new \DOMXPath($dom);
        foreach(['Today','All task dates'] as $label){
            $link=$xpath->query('//a[normalize-space(text())="'.$label.'"]')->item(0);$this->assertNotNull($link);
            parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY)??'',$query);
            $this->assertArrayNotHasKey('mega_brands_marketing_page',$query);$this->assertArrayNotHasKey('mega_finance_page',$query);$this->assertArrayNotHasKey('creative_day',$query);
            $this->assertSame('month',$query['report_period']);$this->assertSame('brands_marketing',$query['weekly_department']);
            if($label==='Today')$this->assertSame(today()->toDateString(),$query['mega_date']);else $this->assertArrayNotHasKey('mega_date',$query);
        }
    }

}
