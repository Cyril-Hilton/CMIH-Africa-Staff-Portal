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
 public function test_all_departments_receive_collapsible_cards_with_their_own_labels_and_values(): void {
  foreach(array_keys(config('performance.departments')) as $dept){$source=app(DepartmentSourcePerformance::class)->build($dept,'2026-11-23','2026-11-29');$html=view('portal.partials.source-performance',['sourcePerformance'=>$source])->render();$this->assertStringContainsString('brands-kpi-workspace',$html);$this->assertStringContainsString('KPI Achievement & Reporting Gaps',$html);$this->assertStringContainsString('Recorded attendance results',$html);$this->assertStringContainsString('data-source-department="'.$dept.'"',$html);}
 }
}
