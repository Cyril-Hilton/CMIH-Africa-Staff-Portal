<?php
namespace Tests\Feature;
use App\Models\{User,BoardroomRequest,Notification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BoardroomMeetingInvitationsTest extends TestCase {
    use RefreshDatabase;
    public function test_attendees_get_details_access_and_status_changes_without_approval_permission(): void {
        $this->travelTo(now()->setDate(2026,11,18)->setTime(9,0));
        $organizer=User::factory()->create(['status'=>'active','access_role'=>'staff']);
        $people=User::factory()->count(5)->create(['status'=>'active','access_role'=>'staff','department'=>'creatives','job_level'=>'executive','position_title'=>'Designer']);
        $hr=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>'hr_admin','position_title'=>'HR Manager','job_level'=>'manager']);
        $data=['date'=>'2026-11-19','start'=>'14:00','end'=>'15:30','purpose'=>'Discuss launch concepts and handover','attendees'=>5,'attendee_ids'=>$people->modelKeys()];
        $this->actingAs($organizer)->post(route('portal.boardroom-requests.store'),$data)->assertSessionHasNoErrors();
        $row=BoardroomRequest::sole();$this->assertSame(5,$row->invitedStaff()->count());
        foreach($people as $person){
            $invite=Notification::where('user_id',$person->id)->where('title','Meeting invitation · awaiting room approval')->sole();
            $this->assertStringContainsString('14:00–15:30 GMT',$invite->message);
            $this->assertStringContainsString('19 Nov 2026',$invite->message);
            $this->assertStringContainsString($data['purpose'],$invite->message);
            $this->actingAs($person)->get($invite->url)->assertOk()->assertSee($data['purpose'])->assertDontSee('Save &amp; notify attendees',false);
            $this->post(route('portal.boardroom-requests.action',$row),['action'=>'approve'])->assertForbidden();
        }
        $outsider=User::factory()->create(['status'=>'active','access_role'=>'staff','job_level'=>'executive']);
        $this->actingAs($outsider)->get(route('portal.boardroom-requests',['booking'=>$row->id]))->assertNotFound();
        $this->actingAs($hr)->post(route('portal.boardroom-requests.action',$row),['action'=>'approve'])->assertSessionHasNoErrors();
        foreach($people as $person)$this->assertDatabaseHas('notifications',['user_id'=>$person->id,'title'=>'Meeting confirmed']);
        $updated=array_replace($data,['start'=>'16:00','end'=>'17:00','attendees'=>4,'attendee_ids'=>$people->take(4)->modelKeys()]);
        $this->actingAs($organizer)->post(route('portal.boardroom-requests.update',$row),$updated)->assertSessionHasNoErrors();
        $this->assertSame('pending',$row->fresh()->status);
        $this->assertDatabaseHas('notifications',['user_id'=>$people->last()->id,'title'=>'Meeting invitation withdrawn']);
        $this->actingAs($people->last())->get(route('portal.boardroom-requests',['booking'=>$row->id]))->assertNotFound();
        $this->actingAs($organizer)->post(route('portal.boardroom-requests.action',$row),['action'=>'cancel'])->assertSessionHasNoErrors();
        foreach($people->take(4) as $person)$this->assertDatabaseHas('notifications',['user_id'=>$person->id,'title'=>'Meeting cancelled']);
    }
    public function test_invalid_attendees_and_headcount_are_rejected(): void {
        $user=User::factory()->create(['status'=>'active','access_role'=>'staff']);
        $inactive=User::factory()->create(['status'=>'suspended','access_role'=>'staff']);
        $data=['date'=>today()->addDay()->format('Y-m-d'),'start'=>'14:00','end'=>'15:00','purpose'=>'Team review','attendees'=>1,'attendee_ids'=>[$inactive->id]];
        $this->actingAs($user)->post(route('portal.boardroom-requests.store'),$data)->assertSessionHasErrors('attendee_ids.0');
        $person=User::factory()->create(['status'=>'active','access_role'=>'staff']);
        $this->post(route('portal.boardroom-requests.store'),array_replace($data,['attendee_ids'=>[$user->id,$person->id]]))->assertSessionHasErrors('attendees');
        $this->assertDatabaseCount('boardroom_requests',0);
    }
}
