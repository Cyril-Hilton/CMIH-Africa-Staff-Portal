<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\DepartmentSourcePerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BrandsKpiPresentationTest extends TestCase {
 use RefreshDatabase;
 public function test_blue_cards_keep_every_measure_and_gap_but_collapse_the_explanations(): void {
  $owner=User::factory()->create(['status'=>'active','department'=>'brands_marketing']);$source=app(DepartmentSourcePerformance::class)->build('brands_marketing','2026-11-23','2026-11-29',$owner->id);
  $html=view('portal.partials.source-performance',['sourcePerformance'=>$source])->render();$this->assertStringContainsString('brands-kpi-workspace',$html);
  foreach($source['areas'] as $area){$this->assertStringContainsString(e($area['name']),$html);$this->assertStringContainsString(e($area['result_gap']??''),$html);}
  foreach($source['metrics'] as $metric){$this->assertStringContainsString(e($metric['name']),$html);$this->assertStringContainsString(e($metric['note']),$html);}
  $dom=new \DOMDocument();@$dom->loadHTML($html);$xp=new \DOMXPath($dom);$this->assertCount(4,$xp->query('//article[@data-kpi-area]'));$this->assertCount(0,$xp->query('//details[@open]'));$this->assertStringContainsString(e($source['scoring_note']),$html);
 }
 public function test_other_departments_keep_their_existing_kpi_presentation(): void {
  $source=app(DepartmentSourcePerformance::class)->build('finance','2026-11-23','2026-11-29');$html=view('portal.partials.source-performance',['sourcePerformance'=>$source])->render();$this->assertStringNotContainsString('brands-kpi-workspace',$html);$this->assertStringContainsString('KPI Achievement & Reporting Gaps',$html);$this->assertStringContainsString('Recorded attendance results',$html);
 }
}
