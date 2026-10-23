<?php
namespace Tests\Feature;
use App\Models\PowerBiReportLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PowerBiReportLinksTest extends TestCase
{
    use RefreshDatabase;
    private function staff(string $department='creatives'): User { return User::factory()->create(['department'=>$department,'access_role'=>'staff','status'=>'active']); }
    private function data(): array { return ['title'=>'Department Results','url'=>'https://app.powerbi.com/groups/me/reports/11111111-1111-1111-1111-111111111111']; }
    public function test_every_staff_department_can_add_and_open_report_links(): void {
        foreach(array_keys(config('performance.departments')) as $i=>$department) {
            $user=$this->staff($department);$data=$this->data();$data['url'].='?page='.$i;
            $this->actingAs($user)->post(route('portal.power-bi.store'),$data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $link=PowerBiReportLink::firstOrFail();
        $this->get(route('portal.power-bi.index'))->assertOk()->assertSee('Department Results')->assertSee('Add a Power BI Report')->assertSee('target="_blank"',false)->assertSee('noopener noreferrer');
        $this->get(route('portal.power-bi.open',$link))->assertRedirect($link->url)->assertHeader('Cache-Control','no-store, private');
        $this->assertDatabaseCount('power_bi_report_links',6);
    }
    public function test_staff_manage_only_own_links_and_archive_can_be_reversed(): void {
        $owner=$this->staff();$this->actingAs($owner)->post(route('portal.power-bi.store'),$this->data());$link=PowerBiReportLink::firstOrFail();
        $payload=$this->data()+['revision'=>1,'active'=>false];
        $this->actingAs($this->staff('finance'))->patch(route('portal.power-bi.update',$link),$payload)->assertForbidden();
        $this->actingAs($owner)->patch(route('portal.power-bi.update',$link),$payload)->assertSessionHasNoErrors();
        $this->get(route('portal.power-bi.open',$link))->assertNotFound();
        $this->patch(route('portal.power-bi.update',$link),$payload)->assertStatus(409);
        $admin=User::factory()->create(['status'=>'active','access_role'=>'super_admin']);
        $this->actingAs($admin)->patch(route('portal.power-bi.update',$link),$this->data()+['revision'=>2,'active'=>true])->assertSessionHasNoErrors();
        $this->get(route('portal.power-bi.open',$link))->assertRedirect($link->url);
        $this->assertDatabaseHas('performance_events',['entity_type'=>'power_bi_link','entity_id'=>$link->id,'action'=>'update']);
    }
    public function test_bad_public_or_lookalike_urls_are_rejected_and_duplicates_are_flagged(): void {
        $this->actingAs($this->staff());
        foreach(['javascript:alert(1)','http://app.powerbi.com/groups/me','https://app.powerbi.com.evil.test/report','https://app.powerbi.com@evil.test/report','https://app.powerbi.com/view?r=public','https://app.powerbi.com:9000/report'] as $url) {
            $this->post(route('portal.power-bi.store'),['title'=>'Test','url'=>$url])->assertSessionHasErrors('url');
        }
        $this->assertDatabaseCount('power_bi_report_links',0);
        $this->post(route('portal.power-bi.store'),$this->data())->assertSessionHasNoErrors();
        $this->post(route('portal.power-bi.store'),$this->data())->assertSessionHasErrors('url');
        $this->assertDatabaseCount('power_bi_report_links',1);
    }
    public function test_guests_and_field_accounts_cannot_access_staff_report_links(): void {
        $this->get(route('portal.power-bi.index'))->assertRedirect(route('login'));
        $user=$this->staff();$user->update(['access_role'=>User::BRAND_PROMOTER_ROLE]);
        $this->actingAs($user)->post(route('portal.power-bi.store'),$this->data())->assertForbidden();
    }
}
