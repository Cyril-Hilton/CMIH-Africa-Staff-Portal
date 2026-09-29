<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\HrStaffRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class HrStaffRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['name' => 'HR Manager', 'department' => 'hr_admin', 'access_role' => 'manager', 'status' => 'active']);
    }

    private function staff(array $data = []): User
    {
        return User::factory()->create(array_merge(['access_role' => 'staff', 'status' => 'active', 'department' => 'operations_projects'], $data));
    }

    private function upload(string $csv)
    {
        return $this->post(route('portal.hr.staff.import.preview'), ['file' => UploadedFile::fake()->createWithContent('updates.csv', $csv)]);
    }

    public function test_hr_can_browse_complete_profile_and_every_history_section(): void
    {
        $hr = $this->hr();
        $staff = $this->staff(['date_of_birth' => '1991-04-12', 'phone' => '0240000012', 'next_of_kin_name' => 'Family Contact', 'start_date' => '2020-01-01']);
        $this->actingAs($hr)->get(route('portal.hr.staff.index'))->assertOk()->assertSee($staff->name);
        $this->get(route('portal.hr.staff.show', $staff))->assertOk()->assertSee('1991-04-12')->assertSee('Family Contact')->assertSee('0240000012')->assertSee('2020-01-01');
        foreach (array_keys(HrStaffRecord::HISTORIES) as $section) {
            $this->get(route('portal.hr.staff.show', [$staff, 'section' => $section]))->assertOk();
        }
        $this->get(route('portal.hr.staff.import'))->assertOk();
    }

    public function test_regular_staff_and_hr_assistants_cannot_read_export_or_import_sensitive_records(): void
    {
        $target = $this->staff();
        foreach ([$this->staff(), $this->staff(['department' => 'hr_admin', 'position_title' => 'HR Assistant'])] as $viewer) {
            $this->actingAs($viewer)->get(route('portal.hr.staff.index'))->assertForbidden();
            $this->get(route('portal.hr.staff.show', $target))->assertForbidden();
            $this->get(route('portal.hr.staff.document', [$target, 'contract_path']))->assertForbidden();
            $this->post(route('portal.hr.staff.export'), ['scope' => 'all', 'format' => 'zip'])->assertForbidden();
            $this->get(route('portal.hr.staff.import'))->assertForbidden();
            $this->get(route('portal.hr.staff.import.template'))->assertForbidden();
            $this->upload("Email,Name\n{$target->email},Other Name\n")->assertForbidden();
            $this->post(route('portal.hr.staff.import.confirm'), ['token' => 'fake'])->assertForbidden();
        }
    }

    public function test_selected_export_includes_only_chosen_staff_and_fields_and_blocks_formulas(): void
    {
        $staff = $this->staff(['name' => '=HYPERLINK("bad")', 'date_of_birth' => '1988-03-05', 'salary' => 9999, 'phone' => '+233240000000']);
        $other = $this->staff(['name' => 'Do Not Export']);
        $response = $this->actingAs($this->hr())->post(route('portal.hr.staff.export'), ['scope' => 'selected', 'ids' => [$staff->id], 'format' => 'csv', 'fields' => ['date_of_birth', 'phone']]);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('1988-03-05', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+233240000000", $csv);
        $this->assertStringNotContainsString($other->name, $csv);
        $this->assertStringNotContainsString('9999', $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
    }

    public function test_all_and_filtered_exports_include_every_page_but_no_external_accounts(): void
    {
        $hr = $this->hr();
        for ($i = 0; $i < 28; $i++) {
            $this->staff(['name' => "Export Person {$i}"]);
        }
        $external = $this->staff(['name' => 'External Field Account', 'access_role' => 'merchandiser']);
        $this->actingAs($hr);
        $csv = $this->post(route('portal.hr.staff.export'), ['scope' => 'filtered', 'q' => 'Export Person', 'format' => 'csv', 'fields' => ['name']])->assertOk()->streamedContent();
        $this->assertSame(28, substr_count($csv, 'Export Person'));
        $this->assertStringNotContainsString($hr->name, $csv);
        $csv = $this->post(route('portal.hr.staff.export'), ['scope' => 'all', 'q' => 'no matches', 'format' => 'csv', 'fields' => ['name']])->assertOk()->streamedContent();
        $this->assertStringContainsString($hr->name, $csv);
        $this->assertStringNotContainsString($external->name, $csv);
        $this->get(route('portal.hr.staff.show', $external))->assertNotFound();
        $this->post(route('portal.hr.staff.export'), ['scope' => 'selected', 'ids' => [$external->id], 'format' => 'zip'])->assertSessionHasErrors('ids');
        $this->post(route('portal.hr.staff.export'), ['scope' => 'selected', 'format' => 'csv', 'fields' => ['name']])->assertSessionHasErrors('ids');
        $this->post(route('portal.hr.staff.export'), ['scope' => 'all', 'format' => 'csv', 'fields' => ['password']])->assertSessionHasErrors('fields.0');
    }

    public function test_full_archive_contains_profile_history_and_documents_without_credentials(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('contracts/test.pdf', 'test contract');
        $staff = $this->staff(['contract_path' => 'contracts/test.pdf', 'ghana_card_path' => 'missing.jpg']);
        Attendance::create(['user_id' => $staff->id, 'clock_in_at' => now(), 'daily_objective' => 'Exported work history', 'status' => 'on time']);
        $response = $this->actingAs($this->hr())->post(route('portal.hr.staff.export'), ['scope' => 'selected', 'ids' => [$staff->id], 'format' => 'zip'])->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $prefix = 'staff-'.$staff->id.'/';
        $this->assertStringContainsString($staff->name, $zip->getFromName($prefix.'profile.csv'));
        $this->assertStringNotContainsString($staff->getRawOriginal('password'), $zip->getFromName($prefix.'profile.csv'));
        $this->assertStringContainsString('Exported work history', $zip->getFromName($prefix.'attendance.csv'));
        $this->assertSame('test contract', $zip->getFromName($prefix.'documents/contract_path.pdf'));
        $this->assertStringContainsString('File missing', $zip->getFromName($prefix.'documents.csv'));
        $zip->close();
        unlink($path);
        $this->get(route('portal.hr.staff.document', [$staff, 'contract_path']))->assertOk();
        $this->get(route('portal.hr.staff.document', [$staff, 'password']))->assertNotFound();
        $staff->update(['contract_path' => '../outside.txt']);
        $this->get(route('portal.hr.staff.document', [$staff, 'contract_path']))->assertNotFound();
    }

    public function test_import_preview_is_read_only_and_confirmation_updates_only_supplied_values(): void
    {
        $staff = $this->staff(['phone' => '0241234567', 'date_of_birth' => '1990-01-01', 'salary' => 2000]);
        $this->actingAs($this->hr());
        $this->upload("Record ID,Date Of Birth,Phone,Gross salary (GHS)\n{$staff->id},1992-05-14,,3000\n")->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('1990-01-01', $staff->fresh()->date_of_birth->format('Y-m-d'));
        $this->get(route('portal.hr.staff.import'))->assertOk()->assertSee('1992-05-14');
        $token = session('hr_staff_import.token');
        $this->post(route('portal.hr.staff.import.confirm'), ['token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('portal.hr.staff.index'));
        $staff->refresh();
        $this->assertSame('1992-05-14', $staff->date_of_birth->format('Y-m-d'));
        $this->assertSame(5, (int) $staff->birthday_month);
        $this->assertSame(14, (int) $staff->birthday_day);
        $this->assertSame('0241234567', $staff->phone);
        $this->assertSame('3000.00', $staff->salary);
        $this->post(route('portal.hr.staff.import.confirm'), ['token' => $token])->assertSessionHasErrors();
    }

    public function test_import_rejects_unknown_staff_duplicates_and_invalid_dates_without_writes(): void
    {
        $staff = $this->staff();
        $this->actingAs($this->hr());
        foreach (["Record ID,Date Of Birth\n{$staff->id},2026-02-31\n", "Record ID,Name\n999999,New Person\n", "Record ID,Name\n{$staff->id},First\n{$staff->id},Second\n"] as $csv) {
            $count = User::count();
            $this->upload($csv)->assertRedirect();
            $this->assertNotEmpty(session('hr_staff_import.errors'));
            $this->post(route('portal.hr.staff.import.confirm'), ['token' => session('hr_staff_import.token')])->assertSessionHasErrors();
            $this->assertSame($count, User::count());
            $this->assertSame($staff->name, $staff->fresh()->name);
        }
    }

    public function test_stale_import_rolls_back_every_row(): void
    {
        $a = $this->staff();
        $b = $this->staff();
        $this->actingAs($this->hr());
        $this->upload("Record ID,Phone\n{$a->id},111111\n{$b->id},222222\n");
        $b->update(['phone' => 'changed elsewhere']);
        $this->post(route('portal.hr.staff.import.confirm'), ['token' => session('hr_staff_import.token')])->assertSessionHasErrors();
        $this->assertSame($a->phone, $a->fresh()->phone);
        $this->assertSame('changed elsewhere', $b->fresh()->phone);
    }

    public function test_import_rejects_privilege_escalation_and_ignores_read_only_roles(): void
    {
        $staff = $this->staff();
        $this->actingAs($this->hr());
        $this->upload("Record ID,Name\n{$staff->id},Cyril Hilton\n");
        $this->assertNotEmpty(session('hr_staff_import.errors'));
        $this->upload("Record ID,Phone,Access Role\n{$staff->id},0241111111,super_admin\n");
        $this->assertSame(['access_role'], session('hr_staff_import.ignored'));
        $this->post(route('portal.hr.staff.import.confirm'), ['token' => session('hr_staff_import.token')])->assertSessionHasNoErrors();
        $this->assertSame('staff', $staff->fresh()->access_role);
    }
}
