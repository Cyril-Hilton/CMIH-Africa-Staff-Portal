<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RenderedPerformanceFlowTest extends TestCase
{
    use RefreshDatabase;
    public function test_rendered_download_links_and_workflow_revision_are_usable(): void
    {
        $owner=User::factory()->create(['status'=>'active','access_role'=>'staff','department'=>'creatives']);
        $manager=User::factory()->create(['status'=>'active','access_role'=>'manager','department'=>'creatives']);
        $owner->update(['line_manager_id'=>$manager->id]);
        $item=WeeklyConsolidatedItem::create(['department'=>'creatives','workflow_state'=>'assigned','week_start'=>now()->startOfWeek()->toDateString(),'week_end'=>now()->endOfWeek()->toDateString(),'campaign_name'=>'Rendered workflow test','deliverables'=>'Approved creative output','lead_staff_id'=>$owner->id,'reviewer_id'=>$manager->id,'created_by'=>$manager->id,'updated_by'=>$manager->id]);
        $response=$this->actingAs($owner)->get(route('portal.performance.index',['view'=>'commitments']))->assertOk();
        $dom=new \DOMDocument;
        $previous=libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();libxml_use_internal_errors($previous);
        $xpath=new \DOMXPath($dom);
        $downloads=$xpath->query('//a[contains(@href,"/performance/export/")]');
        $this->assertSame(2,$downloads->length);
        foreach($downloads as $link) $this->get($link->getAttribute('href'))->assertOk()->assertHeader('Content-Disposition');
        $action=route('portal.performance.transition',$item);
        $form=$xpath->query('//form[@action="'.$action.'"]')->item(0);
        $this->assertNotNull($form);
        $revision=$xpath->query('.//input[@name="revision"]',$form)->item(0)->getAttribute('value');
        $this->assertSame((string)$item->revision,$revision);
        $this->post($action,['revision'=>$revision,'action'=>'accept','reason'=>'Accepted from the rendered form'])->assertSessionHasNoErrors();
        $this->assertSame('accepted',$item->fresh()->workflow_state);
    }
}
