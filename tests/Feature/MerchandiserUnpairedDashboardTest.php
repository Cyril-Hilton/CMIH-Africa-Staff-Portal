<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
class MerchandiserUnpairedDashboardTest extends TestCase {
 use RefreshDatabase;
 public function test_active_unpaired_agent_gets_setup_guidance_instead_of_a_server_error():void {
  $agent=User::factory()->create(['access_role'=>'merchandiser','status'=>'active','kd_id'=>null,'region_id'=>null]);
  $this->actingAs($agent)->get(route('merchandisers.dashboard'))->assertOk()->assertSee('Your Account Is Active')->assertSee('Your Route Setup Is Still Needed')->assertSee(route('merchandisers.logout'),false);
  $this->assertSame('active',$agent->fresh()->status);
 }
 public function test_merchandiser_sign_in_has_no_duplicate_password_button():void {
  $this->get(route('merchandisers.login'))->assertOk()->assertDontSee('onclick="togglePasswordVisibility',false)->assertSee('Show password');
 }
}
