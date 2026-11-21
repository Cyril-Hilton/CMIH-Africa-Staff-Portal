<?php
namespace Tests\Feature;
use App\Models\{Notification,PerformanceOutbox,User};
use App\Support\PortalLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
class ProductionNotificationLinkTest extends TestCase {
 use RefreshDatabase;
 public function test_scheduled_links_use_the_public_staff_origin_even_when_the_console_started_on_localhost(): void {
  $before=app()->environment();$originalRequest=request();
  try {app()->detectEnvironment(fn()=>'production');config(['cmih.app_kind'=>'staff','cmih.urls.staff'=>'https://portal.cmih.africa']);URL::forceRootUrl('http://localhost');PortalLinks::configureConsoleRoot();app()->instance('request',\Illuminate\Http\Request::create('http://localhost/dashboard'));
   $this->assertSame('https://portal.cmih.africa/dashboard?exec_view=accountability',route('dashboard',['exec_view'=>'accountability']));
  } finally {app()->instance('request',$originalRequest);app()->detectEnvironment(fn()=>$before);URL::forceRootUrl(null);URL::forceScheme(null);}
 }
 public function test_existing_loopback_notifications_open_the_real_page_with_query_and_anchor_intact(): void {
  $u=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);$old='http://localhost/dashboard?exec_view=accountability#ranking-attention';
  $n=Notification::create(['user_id'=>$u->id,'title'=>'Ranking attention','message'=>'Review flags','url'=>$old]);
  $o=PerformanceOutbox::create(['user_id'=>$u->id,'dedupe_key'=>'old-loopback','title'=>'Ranking attention','message'=>'Review flags','url'=>$old]);
  $before=app()->environment();
  try {app()->detectEnvironment(fn()=>'production');config(['cmih.urls.staff'=>'https://portal.cmih.africa','cmih.urls.brands'=>'https://brands.cmih.africa']);
   $expected='https://portal.cmih.africa/dashboard?exec_view=accountability#ranking-attention';$this->assertSame($expected,$n->fresh()->url);$this->assertSame($expected,$o->fresh()->url);$this->assertSame($old,$n->getRawOriginal('url'));
   $this->actingAs($u)->get(route('portal.notifications.read',$n))->assertRedirect($expected);
   $this->assertSame('https://brands.cmih.africa/brands/test?tab=team',PortalLinks::notification('http://127.0.0.1:8787/brands/test?tab=team'));
   $this->assertSame('https://example.test/reference',PortalLinks::notification('https://example.test/reference'));
  }finally{app()->detectEnvironment(fn()=>$before);}
 }
 public function test_development_links_stay_local(): void {
  $this->assertSame('http://localhost/dashboard',PortalLinks::notification('http://localhost/dashboard'));
 }
}
