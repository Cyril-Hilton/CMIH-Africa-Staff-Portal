<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\SiteContent;
class PortalAssetOriginTest extends TestCase
{
 use RefreshDatabase;
 public function test_logo_uses_current_portal_origin_instead_of_storage_configuration(): void
 {
  \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8093');
  config(['filesystems.disks.public.url'=>'http://127.0.0.1:8000/storage']);
  SiteContent::create(['key'=>'logo_dark','value'=>'content/logo-dark.png']);
  $html=\Illuminate\Support\Facades\Blade::render('<x-application-logo />');
  $this->assertStringContainsString(asset('storage/content/logo-dark.png'),$html);
  $this->assertStringNotContainsString('127.0.0.1:8000',$html);
 }
 public function test_editor_cdn_is_in_script_allowlist(): void
 {
  $middleware=new \App\Http\Middleware\AddSecurityHeaders;
  $response=$middleware->handle(\Illuminate\Http\Request::create('/login'),fn()=>response('OK'));
  $policy=$response->headers->get('Content-Security-Policy-Report-Only');
  $this->assertMatchesRegularExpression("/script-src[^;]*https:\\/\\/cdn\\.ckeditor\\.com;/",$policy);
 }
}
