<?php
namespace Tests\Unit;
use App\Support\ReportRichText;
use PHPUnit\Framework\TestCase;
class ReportRichTextTest extends TestCase
{
 public function test_keeps_report_tables_images_alignment_and_lists_without_active_content():void {
  $html=ReportRichText::html('<h3 style="text-align:center">Summary</h3><ul><li><strong>Delivered</strong></li></ul><table><tr><td colspan="2">92%</td></tr></table><img src="https://example.com/report.jpg" onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">Link</a><p style="position:fixed;background-image:url(https://bad.test);color:#abc">Note</p>');
  $this->assertStringContainsString('text-align:center',$html);
  $this->assertStringContainsString('<ul><li><strong>Delivered</strong></li></ul>',$html);
  $this->assertStringContainsString('<td colspan="2">92%</td>',$html);
  $this->assertStringContainsString('src="https://example.com/report.jpg"',$html);
  foreach(['onerror','script','javascript:','position:','background-image','alert('] as $bad)$this->assertStringNotContainsString($bad,$html);
 }
 public function test_rejects_embedded_uploads_and_untrusted_svg():void {
  $html=ReportRichText::html('<img src="data:image/svg+xml,evil"><svg onload="alert(1)"></svg><iframe src="https://example.com"></iframe>');
  $this->assertSame('',$html);
 }
}
