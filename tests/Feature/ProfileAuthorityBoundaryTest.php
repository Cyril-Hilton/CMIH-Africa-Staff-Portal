<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class ProfileAuthorityBoundaryTest extends TestCase {
 use RefreshDatabase;
 private function patchFor(User $user,array $changes){return $this->actingAs($user)->patch(route('profile.update'),['name'=>$user->name,'email'=>$user->email,...$changes]);}
 public function test_clearing_then_setting_authority_fields_does_not_bypass_approval():void {
  Mail::fake();$user=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives','position_title'=>'Executive','job_level'=>'executive']);
  $this->patchFor($user,['department'=>null,'position_title'=>null])->assertSessionHasNoErrors();
  $user->refresh();$this->assertSame('creatives',$user->department);$this->assertSame('Executive',$user->position_title);
  $this->patchFor($user,['department'=>'hr_admin','position_title'=>'CVO'])->assertSessionHasNoErrors();
  $user->refresh();$this->assertSame('creatives',$user->department);$this->assertSame('Executive',$user->position_title);$this->assertSame('CVO',$user->requested_position_title);$this->assertFalse($user->isCvoOrSuperAdmin());$this->assertFalse($user->hasFullHrAccess());
 }
 public function test_initially_blank_title_still_needs_approval_and_hr_cannot_self_promote():void {
  Mail::fake();
  foreach([['creatives','staff',null],['hr_admin','manager','Manager']] as [$department,$role,$title]){
   $user=User::factory()->create(['status'=>'active','access_role'=>$role,'department'=>$department,'position_title'=>$title,'job_level'=>'executive']);
   $this->patchFor($user,['position_title'=>'CVO'])->assertSessionHasNoErrors();$user->refresh();
   $this->assertSame($title,$user->position_title);$this->assertSame('CVO',$user->requested_position_title);$this->assertFalse($user->isCvoOrSuperAdmin());
  }
 }
 public function test_editable_job_title_cannot_grant_hr_authority():void {
  $user=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'hr_admin','position_title'=>'Executive','job_level'=>'executive','job_title'=>'HR Assistant']);
  $this->assertFalse($user->hasFullHrAccess());
  $this->patchFor($user,['job_title'=>'HR Director'])->assertSessionHasErrors('job_title');
  $this->assertSame('HR Assistant',$user->fresh()->job_title);$this->assertFalse($user->fresh()->hasFullHrAccess());
 }
}
