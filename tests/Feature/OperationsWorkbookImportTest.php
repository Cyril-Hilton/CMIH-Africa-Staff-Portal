<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsClockedIn;
use App\Models\PerformanceRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_undated_details_and_existing_edits_without_exposing_the_workbook(): void
    {
        $this->withoutMiddleware(EnsureUserIsClockedIn::class);
        $manager = User::factory()->create(['access_role' => 'super_admin', 'status' => 'active']);
        $staff = User::factory()->create(['department' => 'operations_projects', 'access_role' => 'staff', 'status' => 'active']);
        $workspace = sys_get_temp_dir().'/operations-workbook-'.Str::uuid();
        File::makeDirectory($workspace, 0700);
        $oldStorage = storage_path();
        $this->app->useStoragePath($workspace);

        try {
            $snapshot = $workspace.'/input.json';
            $row = [
                'kind' => 'activation', 'reference' => 'OPS-XLSX-'.str_repeat('a', 24), 'title' => 'Legacy activation name',
                'client' => null, 'project' => null, 'period_start' => '2026-11-09', 'period_end' => '2026-11-10',
                'lead' => $staff->name, 'supporting' => [], 'status' => 'unrecorded',
                'data' => ['import_source' => 'Activation / Row 3', 'import_report_shared' => true],
            ];
            $posm = array_replace($row, [
                'kind' => 'posm_deployment', 'reference' => 'OPS-XLSX-'.str_repeat('b', 24), 'title' => 'Undated display stock',
                'period_start' => '0001-01-01', 'period_end' => '0001-01-01', 'data' => ['import_undated' => true],
            ]);
            $archive = [[
                'sheet' => 'Worksheet', 'workbook' => 'Tracker', 'url' => 'https://docs.google.com/',
                'hidden' => false, 'rows' => [['number' => 1, 'cells' => [['address' => 'A1', 'value' => '<script>Archive text</script>']]]],
            ]];
            File::put($snapshot, json_encode(['records' => [$row, $posm], 'archive' => $archive, 'inventory' => ['tracker' => []]]));
            $this->artisan('operations:import-workbooks', ['snapshot' => $snapshot])->assertSuccessful();
            $this->assertDatabaseCount('performance_registers', 0);
            $this->artisan('operations:import-workbooks', ['snapshot' => $snapshot, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('performance_registers', 2);
            PerformanceRegister::where('kind', 'activation')->update(['title' => 'Staff corrected title']);
            $this->artisan('operations:import-workbooks', ['snapshot' => $snapshot, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('performance_registers', 2);

            $this->actingAs($manager)->get(route('portal.operations', ['ops_tab' => 'sources']))
                ->assertOk()->assertSee('<script>Archive text</script>');
            $this->get(route('portal.operations', ['ops_tab' => 'sources']))->assertDontSee('<script>Archive text</script>', false);
            $this->actingAs($staff)->get(route('portal.operations', ['ops_tab' => 'sources']))->assertForbidden();
            $this->get(route('dashboard', ['weekly_department' => 'operations_projects', 'ops_tab' => 'projects', 'report_date' => '2026-11-09']))
                ->assertOk()->assertSee('Staff corrected title')->assertDontSee('Legacy activation name');
            $this->get(route('portal.operations', ['ops_tab' => 'assets', 'report_date' => '2026-11-09']))
                ->assertOk()->assertSee('Undated display stock')->assertSee('Date not recorded')->assertDontSee('01 Jan 0001');
        } finally {
            $this->app->useStoragePath($oldStorage);
            File::deleteDirectory($workspace);
        }
    }
}
