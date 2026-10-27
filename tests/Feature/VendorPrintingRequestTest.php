<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorPrintingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPrintingRequestTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['department' => 'operations_projects', 'access_role' => 'manager', 'status' => 'active', 'position_title' => 'Operations Manager']);
    }

    private function payload(): array
    {
        return ['information' => ['request_date' => '2026-10-20', 'required_date' => '2026-10-26', 'requested_by' => 'Operations Manager', 'brand_client' => 'Dove', 'project_activation' => 'Campus activation', 'delivery_location' => 'Accra', 'vendor_name' => 'Example Print Vendor', 'urgency' => 'Normal'],
            'items' => [['description' => 'Branded activation backdrop', 'printing_type' => 'Backdrop', 'material' => 'Flex', 'print_method' => 'Digital Print', 'dimension' => '240 × 180', 'unit' => 'cm', 'quantity' => 2, 'finishing' => 'Eyelets', 'artwork_status' => 'Ready for Print', 'remarks' => 'Match the approved artwork.']],
            'vendor' => ['quotation_required' => 'Yes', 'delivery_required' => 'Yes', 'installation_required' => 'No', 'quotation_amount' => 1250.50, 'approval_status' => 'Pending', 'approved_by' => null, 'vendor_confirmation' => 'Pending', 'delivery_date' => '2026-10-25', 'additional_notes' => 'Deliver with protective wrapping.'],
            'checklist' => ['dimensions' => 1, 'quantity' => 1, 'specification' => 1, 'artwork' => 1, 'required_date' => 1, 'quotation' => 0]];
    }

    public function test_operations_manager_can_save_reopen_preview_download_and_revise_request(): void
    {
        $this->actingAs($this->manager());
        $this->get(route('portal.printing-requests.create'))->assertOk()->assertSee('Print specifications')->assertSee('Save & download PDF', false);
        $this->post(route('portal.printing-requests.store'), $this->payload() + ['intent' => 'download'])->assertSessionHasNoErrors()->assertSessionHas('download_printing_request', true);
        $record = VendorPrintingRequest::firstOrFail();
        $this->assertStringStartsWith('PR-', $record->reference);
        $this->get(route('portal.printing-requests.edit', $record))->assertOk()->assertSee($record->reference);
        $this->get(route('portal.printing-requests.preview', $record))->assertOk()->assertSee('Vendor printing request')->assertSee('240 × 180')->assertSee('Pre-submission checklist');
        $response = $this->get(route('portal.printing-requests.download', $record))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if ($directory = getenv('CMIH_PRINT_QA_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }file_put_contents($directory.'/printing-request.pdf', $response->getContent());
            file_put_contents($directory.'/printing-form.html', $this->get(route('portal.printing-requests.edit', $record))->getContent());
        }
        $v = $this->payload();
        $v['revision'] = $record->revision;
        $v['items'][0]['quantity'] = 3;
        $this->put(route('portal.printing-requests.update', $record), $v)->assertSessionHasNoErrors();
        $this->assertSame(2, $record->fresh()->revision);
        $this->assertSame(3, $record->fresh()->items[0]['quantity']);
        $this->put(route('portal.printing-requests.update', $record), $v)->assertStatus(409);
        $this->assertDatabaseHas('performance_events', ['entity_type' => 'printing_request', 'entity_id' => $record->id, 'action' => 'download']);
    }

    public function test_cvo_and_super_admin_have_full_printing_access_and_menu_visibility(): void
    {
        foreach ([['access_role'=>'staff','position_title'=>'CVO','job_level'=>'cvo'], ['access_role'=>'super_admin','position_title'=>'Super Admin','job_level'=>'super_admin']] as $role) {
            $user=User::factory()->create($role+['department'=>null,'status'=>'active']);
            $this->actingAs($user)->withHeader('X-Test-Enforce-ClockIn','1');
            $this->get(route('portal.printing-requests.index'))->assertOk()->assertSee('Vendor Printing Requests');
            $this->get(route('portal.printing-requests.create'))->assertOk()->assertSee('Save & download PDF',false);
            $this->post(route('portal.printing-requests.store'),$this->payload())->assertSessionHasNoErrors();
            $record=VendorPrintingRequest::latest('id')->firstOrFail();
            $this->get(route('portal.printing-requests.edit',$record))->assertOk();
            $this->put(route('portal.printing-requests.update',$record),$this->payload()+['revision'=>$record->revision])->assertSessionHasNoErrors();
            $this->get(route('portal.printing-requests.preview',$record))->assertOk();
            $pdf=$this->get(route('portal.printing-requests.download',$record))->assertOk();
            $this->assertStringStartsWith('%PDF',$pdf->getContent());
        }
    }

    public function test_other_staff_and_other_department_managers_cannot_use_any_printing_endpoint(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('portal.printing-requests.store'), $this->payload())->assertSessionHasNoErrors();
        $record = VendorPrintingRequest::firstOrFail();
        foreach ([['finance', 'manager'], ['hr_admin', 'manager']] as [$department,$role]) {
            $outsider = User::factory()->create(['department' => $department, 'access_role' => $role, 'status' => 'active']);
            $this->actingAs($outsider);
            foreach (['index', 'create'] as $route) {
                $this->get(route('portal.printing-requests.'.$route))->assertForbidden();
            }
            foreach (['edit', 'preview', 'download'] as $route) {
                $this->get(route('portal.printing-requests.'.$route, $record))->assertForbidden();
            }
            $this->post(route('portal.printing-requests.store'), $this->payload())->assertForbidden();
            $this->put(route('portal.printing-requests.update', $record), $this->payload() + ['revision' => 1])->assertForbidden();
        }
    }

    public function test_operations_staff_submit_and_hod_approve_before_download(): void
    {
        $staff=User::factory()->create(['department'=>'operations_projects','access_role'=>'staff','status'=>'active']);$manager=$this->manager();
        $this->actingAs($staff)->get(route('portal.printing-requests.index'))->assertOk()->assertSee('Vendor Printing Requests');
        $this->get(route('portal.printing-requests.create'))->assertOk()->assertSee('Submit for HOD Review')->assertDontSee('Save &amp; download PDF',false);
        $payload=$this->payload();$payload['vendor']['approval_status']='Approved';$payload['vendor']['approved_by']='Invented reviewer';
        $this->post(route('portal.printing-requests.store'),$payload+['review_status'=>'approved','intent'=>'submit'])->assertSessionHasNoErrors();
        $record=VendorPrintingRequest::firstOrFail();$this->assertSame('pending',$record->review_status);$this->assertSame('Pending',$record->vendor['approval_status']);$this->assertSame($staff->name,$record->information['requested_by']);
        foreach(['preview','download'] as $action)$this->get(route('portal.printing-requests.'.$action,$record))->assertForbidden();
        $this->post(route('portal.printing-requests.review',$record),['revision'=>1,'decision'=>'approve','review_note'=>'Self approval attempt'])->assertForbidden();
        $this->actingAs($manager)->get(route('portal.printing-requests.edit',$record))->assertOk()->assertSee('HOD Review');
        $this->post(route('portal.printing-requests.review',$record),['revision'=>1,'decision'=>'approve','review_note'=>'Dimensions and materials checked'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$record->fresh()->review_status);$this->assertSame($manager->id,(int)$record->fresh()->reviewed_by);
        $this->actingAs($staff)->get(route('portal.printing-requests.download',$record))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->put(route('portal.printing-requests.update',$record),$this->payload()+['revision'=>1,'intent'=>'submit'])->assertSessionHasNoErrors();
        $this->assertSame('pending',$record->fresh()->review_status);$this->get(route('portal.printing-requests.download',$record))->assertForbidden();
        $this->actingAs($manager)->post(route('portal.printing-requests.review',$record),['revision'=>1,'decision'=>'approve','review_note'=>'Old screen approval'])->assertStatus(409);
        $this->post(route('portal.printing-requests.review',$record),['revision'=>2,'decision'=>'return','review_note'=>'Please confirm the revised quantity'])->assertSessionHasNoErrors();
        $this->assertSame('needs_changes',$record->fresh()->review_status);
        $this->actingAs($staff)->get(route('portal.printing-requests.edit',$record))->assertOk()->assertSee('Please confirm the revised quantity');
        $this->assertDatabaseHas('performance_events',['entity_type'=>'printing_request','entity_id'=>$record->id,'action'=>'approve']);
    }

    public function test_staff_cannot_open_edit_preview_or_download_another_staff_request():void
    {
        $owner=User::factory()->create(['department'=>'operations_projects','access_role'=>'staff','status'=>'active']);
        $other=User::factory()->create(['department'=>'operations_projects','access_role'=>'staff','status'=>'active']);
        $this->actingAs($owner)->post(route('portal.printing-requests.store'),$this->payload()+['intent'=>'draft'])->assertSessionHasNoErrors();$record=VendorPrintingRequest::firstOrFail();
        $this->assertSame('draft',$record->review_status);
        $this->actingAs($other)->get(route('portal.printing-requests.index'))->assertOk()->assertDontSee($record->reference);
        foreach(['edit','preview','download'] as $action)$this->get(route('portal.printing-requests.'.$action,$record))->assertForbidden();
        $this->put(route('portal.printing-requests.update',$record),$this->payload()+['revision'=>1])->assertForbidden();
    }

    public function test_quantities_dates_statuses_and_approval_names_are_validated_and_html_is_escaped(): void
    {
        $this->actingAs($this->manager());
        $v = $this->payload();
        $v['items'][0]['quantity'] = 0;
        $v['information']['required_date'] = '2026-10-19';
        $v['vendor']['approval_status'] = 'Approved';
        $this->post(route('portal.printing-requests.store'), $v)->assertSessionHasErrors(['items.0.quantity', 'information.required_date', 'vendor.approved_by']);
        $this->assertDatabaseCount('vendor_printing_requests', 0);
        $v = $this->payload();
        $v['items'][0]['description'] = '<script>alert(1)</script>';
        $this->post(route('portal.printing-requests.store'), $v)->assertSessionHasNoErrors();
        $record = VendorPrintingRequest::firstOrFail();
        $this->get(route('portal.printing-requests.preview', $record))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $v = $this->payload();
        $v['items'][0]['material'] = 'Other';
        $v['items'][0]['remarks'] = '';
        $this->post(route('portal.printing-requests.store'), $v)->assertSessionHasErrors('items.0.remarks');
    }

    public function test_large_specification_can_download_as_a_multi_page_pdf(): void
    {
        $this->actingAs($this->manager());
        $v = $this->payload();
        $item = $v['items'][0];
        $v['items'] = [];
        for ($i = 1; $i <= 18; $i++) {
            $v['items'][] = array_replace($item, ['description' => 'Print item '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' — '.str_repeat('Detailed print specification ', 5), 'remarks' => str_repeat('Confirm colour and finishing. ', 5)]);
        }
        $this->post(route('portal.printing-requests.store'), $v)->assertRedirect()->assertSessionHasNoErrors();
        $record = VendorPrintingRequest::firstOrFail();
        $response = $this->get(route('portal.printing-requests.download', $record))->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if ($directory = getenv('CMIH_PRINT_QA_DIR')) {
            file_put_contents($directory.'/printing-multi-page.pdf', $response->getContent());
        }
    }
}
