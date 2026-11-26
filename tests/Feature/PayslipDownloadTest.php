<?php
namespace Tests\Feature;
use App\Models\{Payslip,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
class PayslipDownloadTest extends TestCase {
 use RefreshDatabase;
 private function slip(User $u):Payslip {return Payslip::create(['user_id'=>$u->id,'period'=>'2026-08','gross_salary'=>5000,'ssnit_employee'=>275,'ssnit_employer'=>650,'paye_tax'=>420,'other_deductions'=>0,'bonuses'=>0,'net_salary'=>4305,'issued_at'=>now()]);}
 public function test_employee_receives_an_actual_pdf_attachment_with_embedded_logo():void {
  $u=User::factory()->create(['status'=>'active']);$slip=$this->slip($u);
  $r=$this->actingAs($u)->get(route('portal.payroll.payslip',['payslip'=>$slip,'download'=>1]))->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('Cache-Control','no-store, private')->assertHeader('Content-Disposition','attachment; filename="CMIH-Payslip-2026-08.pdf"');
  $this->assertStringStartsWith('%PDF-',$r->getContent());$this->assertStringContainsString('/Subtype /Image',$r->getContent());
  $this->get(route('portal.payroll'))->assertOk()->assertSee('download=1',false)->assertSee('download data-no-silent data-no-ajax',false);
  $this->actingAs(User::factory()->create(['status'=>'active']))->get(route('portal.payroll.payslip',['payslip'=>$slip,'download'=>1]))->assertForbidden();
 }
 public function test_html_and_print_use_self_contained_logos_and_a_correct_signed_pdf_link():void {
  $u=User::factory()->create(['status'=>'active']);$slip=$this->slip($u);
  $html=$this->actingAs($u)->get(route('portal.payroll.payslip',$slip))->assertOk()->assertSee('data:image/png;base64,',false)->assertSee('@media screen and (prefers-color-scheme: dark)',false)->getContent();
  $doc=new \DOMDocument;@$doc->loadHTML($html);$xp=new \DOMXPath($doc);$url=$xp->query('//a[@class="download-button"]')->item(0)->getAttribute('href');
  $this->assertStringContainsString('download=1',$url);$this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf');
 }
 public function test_signed_pdf_access_expires_and_rejects_tampering():void {
  $slip=$this->slip(User::factory()->create(['status'=>'active']));
  $url=URL::temporarySignedRoute('portal.payroll.payslip.signed',now()->addHour(),['payslip'=>$slip->id,'download'=>1]);$this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->get($url.'&period=2026-10')->assertForbidden();
  $this->get(URL::temporarySignedRoute('portal.payroll.payslip.signed',now()->subHour(),['payslip'=>$slip->id,'download'=>1]))->assertForbidden();
 }
}
