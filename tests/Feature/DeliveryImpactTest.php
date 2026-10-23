<?php
namespace Tests\Feature;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\StaffPerformanceScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
class DeliveryImpactTest extends TestCase
{
    use RefreshDatabase;
    public function test_many_small_tasks_do_not_outweigh_a_large_verified_delivery(): void {
        Carbon::setTestNow('2026-10-22 12:00');
        try {
            $manager=User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
            $owner=User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active','line_manager_id'=>$manager->id]);
            $large=Task::create(['title'=>'Major delivery','assigned_to'=>$owner->id,'assigned_by'=>$manager->id,'department'=>'creatives','status'=>'Open','due_on'=>'2026-10-30','created_at'=>'2026-08-01']);
            $this->actingAs($manager)->post(route('portal.tasks.impact',$large),['performance_weight'=>90,'weight_reason'=>'Complex project with substantial business impact'])->assertSessionHasNoErrors();
            for($i=0;$i<10;$i++) Task::create(['title'=>'Small delivery '.$i,'assigned_to'=>$owner->id,'assigned_by'=>$manager->id,'department'=>'creatives','status'=>'Done','completion_review_status'=>'approved','completion_reviewed_by'=>$manager->id,'completion_reviewed_at'=>now(),'due_on'=>'2026-10-23']);
            $score=fn()=>app(StaffPerformanceScore::class)->user($owner,Carbon::parse('2026-10-01'),Carbon::parse('2026-10-31'));
            $this->assertSame(10.0,$score()['task_rate']);
            $this->assertSame(100,$score()['completion_points_total']);
            $manager->update(['status'=>'suspended']);
            $this->assertSame(100,$score()['completion_points_total']);
            $manager->update(['status'=>'active']);
            $large->update(['status'=>'Done','completion_review_status'=>'approved','completion_reviewed_by'=>$manager->id,'completion_reviewed_at'=>now()]);
            $this->assertSame(100.0,$score()['task_rate']);
            $this->assertSame(100,app(StaffPerformanceScore::class)->team(collect([$owner]),Carbon::parse('2026-10-01'),Carbon::parse('2026-10-31'))['completion_points_verified']);
            $this->actingAs($manager)->post(route('portal.tasks.impact',$large),['performance_weight'=>100,'weight_reason'=>'Attempt to change a completed weight'])->assertStatus(409);
            $this->assertDatabaseHas('performance_events',['entity_type'=>'task','entity_id'=>$large->id,'action'=>'set_impact']);
        } finally { Carbon::setTestNow(); }
    }
    public function test_staff_and_other_department_managers_cannot_rate_work_or_themselves(): void {
        $manager=User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
        $owner=User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active','line_manager_id'=>$manager->id]);
        $other=User::factory()->create(['department'=>'finance','access_role'=>'manager','status'=>'active']);
        $task=Task::create(['title'=>'Output','assigned_to'=>$owner->id,'department'=>'creatives','status'=>'Open']);
        foreach([$owner,$other] as $actor)$this->actingAs($actor)->post(route('portal.tasks.impact',$task),['performance_weight'=>100,'weight_reason'=>'Attempting to increase this task weight'])->assertForbidden();
        $mine=Task::create(['title'=>'My own output','assigned_to'=>$manager->id,'department'=>'creatives','status'=>'Open']);
        $this->actingAs($manager)->post(route('portal.tasks.impact',$mine),['performance_weight'=>100,'weight_reason'=>'Attempting to increase my own task weight'])->assertForbidden();
        $this->assertSame(1,$task->fresh()->performance_weight);
    }
    public function test_commitments_use_the_same_weight_rules_and_linked_tasks_cannot_be_double_rated(): void {
        $manager=User::factory()->create(['department'=>'creatives','access_role'=>'manager','status'=>'active']);
        $owner=User::factory()->create(['department'=>'creatives','access_role'=>'staff','status'=>'active','line_manager_id'=>$manager->id]);
        $item=WeeklyConsolidatedItem::create(['department'=>'creatives','lead_staff_id'=>$owner->id,'week_start'=>'2026-10-19','week_end'=>'2026-10-23','campaign_name'=>'Project milestone','deliverables'=>'First approved milestone','workflow_state'=>'executing','created_by'=>$manager->id,'updated_by'=>$manager->id]);
        $this->actingAs($manager)->post(route('portal.performance.impact',$item),['performance_weight'=>25,'weight_reason'=>'Agreed milestone on a large company project'])->assertSessionHasNoErrors();
        $this->assertSame(25,$item->fresh()->performance_weight);
        $linked=Task::create(['title'=>'Linked work','assigned_to'=>$owner->id,'department'=>'creatives','status'=>'Open']);
        $item->update(['source_task_id'=>$linked->id]);
        $this->post(route('portal.performance.impact',$item),['performance_weight'=>30,'weight_reason'=>'Attempt to rate linked work twice'])->assertStatus(409);
        $item->update(['source_task_id'=>null]);
        $item->update(['workflow_state'=>'verified']);
        $this->post(route('portal.performance.impact',$item),['performance_weight'=>10,'weight_reason'=>'Attempting a late score adjustment'])->assertStatus(409);
    }
}
