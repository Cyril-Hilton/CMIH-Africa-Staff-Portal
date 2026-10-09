<?php
namespace Tests\Feature;
use App\Services\LocationProviderChain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LocationProviderChainTest extends TestCase
{
 private function chain(): LocationProviderChain
 {
  config(['cache.default'=>'array','location_chain.new'=>'test-new','location_chain.legacy'=>'test-legacy','location_chain.geoapify'=>'test-geo']);
  Cache::flush();
  return new class extends LocationProviderChain {protected function reserve(string $provider): bool {return true;}};
 }
 public function test_new_places_is_used_first(): void
 {
  $chain=$this->chain();Http::preventStrayRequests();
  Http::fake(['places.googleapis.com/*'=>Http::response(['places'=>[['displayName'=>['text'=>'Accra'],'formattedAddress'=>'Ghana','location'=>['latitude'=>5.6,'longitude'=>-0.2]]]])]);
  $result=$chain->search('Accra');$this->assertSame('new',$result['provider']);$this->assertCount(1,$result['results']);Http::assertSentCount(1);
 }
 public function test_failures_fall_back_to_legacy_then_geoapify(): void
 {
  $chain=$this->chain();Http::preventStrayRequests();
  Http::fake(['places.googleapis.com/*'=>Http::response([],403),'maps.googleapis.com/*'=>Http::response(['status'=>'REQUEST_DENIED']),'api.geoapify.com/*'=>Http::response(['features'=>[['properties'=>['name'=>'Accra','formatted'=>'Accra, Ghana','lat'=>5.6,'lon'=>-0.2]]]])]);
  $result=$chain->search('Accra');$this->assertSame('geoapify',$result['provider']);$this->assertTrue($result['available']);$this->assertSame(5.6,$result['results'][0]['latitude']);Http::assertSentCount(3);
 }
 public function test_legacy_places_is_used_when_new_places_fails(): void
 {
  $chain=$this->chain();Http::preventStrayRequests();
  Http::fake(['places.googleapis.com/*'=>Http::response([],403),'maps.googleapis.com/*'=>Http::response(['status'=>'OK','results'=>[['name'=>'Accra','formatted_address'=>'Ghana','geometry'=>['location'=>['lat'=>5.6,'lng'=>-0.2]]]]])]);
  $result=$chain->search('Accra');$this->assertSame('legacy',$result['provider']);$this->assertCount(1,$result['results']);Http::assertSentCount(2);
 }
 public function test_all_provider_failures_return_unavailable(): void
 {
  $chain=$this->chain();Http::preventStrayRequests();Http::fake(['*'=>Http::response([],503)]);
  $result=$chain->search('Accra');$this->assertFalse($result['available']);$this->assertSame([],$result['results']);Http::assertSentCount(3);
 }
 public function test_empty_valid_results_do_not_use_more_providers(): void
 {
  $chain=$this->chain();Http::preventStrayRequests();Http::fake(['places.googleapis.com/*'=>Http::response(['places'=>[]])]);
  $result=$chain->search('Unknown place');$this->assertTrue($result['available']);$this->assertSame([],$result['results']);Http::assertSentCount(1);
 }
 public function test_endpoint_is_not_redirected_to_another_app(): void
 {
  config(['cmih.app_kind'=>'staff']);$this->getJson('/api/location-fallback?q=Accra')->assertUnauthorized();
 }
}
