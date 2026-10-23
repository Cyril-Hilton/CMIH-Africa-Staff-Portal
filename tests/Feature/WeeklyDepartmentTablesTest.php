<?php

namespace Tests\Feature;

use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\WeeklyDepartmentTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDepartmentTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_tables_use_department_columns_and_approved_allowlisted_values(): void
    {
        $owner = User::factory()->create();
        $base = ['department' => 'operations_projects', 'kind' => 'project', 'reference' => 'ART-1', 'title' => 'Artwork', 'owner_id' => $owner->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-23', 'status' => 'completed', 'data' => ['rework_count' => 2, 'quality_score' => 90, 'budget' => 987654, 'private_note' => 'Confidential source'], 'approved_at' => now()];
        PerformanceRegister::create($base);
        PerformanceRegister::create(array_replace($base, ['reference' => 'ART-2', 'title' => 'Unreviewed output', 'approved_at' => null]));
        $tables = app(WeeklyDepartmentTables::class)->build('operations_projects');
        $this->assertCount(1, $tables['operations_projects']['rows']);
        $this->assertEquals(2, $tables['operations_projects']['rows'][0]['rework_count']);
        $this->assertArrayNotHasKey('budget', $tables['operations_projects']['rows'][0]);
        $this->assertStringNotContainsString('Confidential source', json_encode($tables));
        $this->assertCount(0, app(WeeklyDepartmentTables::class)->build('operations_projects', '2026-11-01', '2026-11-30')['operations_projects']['rows']);
        $this->assertSame('Completed', $tables['operations_projects']['rows'][0]['status']);
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('hr_admin'));
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('creatives'));
        $this->assertSame([], app(WeeklyDepartmentTables::class)->build('brands_marketing'));
    }
}
