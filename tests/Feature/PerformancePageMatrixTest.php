<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformancePageMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_department_staff_and_manager_can_render_every_performance_view(): void
    {
        $views = ['summary', 'commitments', 'kpis', 'exceptions', 'trends', 'dependencies', 'meeting', 'dictionary', 'registers'];
        foreach (array_keys(config('performance.departments')) as $department) {
            foreach (['staff', 'manager'] as $role) {
                $user = User::factory()->create(['department' => $department, 'access_role' => $role, 'status' => 'active']);
                $this->actingAs($user);
                foreach ($views as $view) {
                    $this->get(route('portal.performance.index', ['department' => $department, 'view' => $view]))->assertOk();
                }
                $this->get(route('dashboard', ['weekly_department' => 'all']))->assertOk()->assertSee('Weekly Department Reports');
                $this->get(route('portal.performance.cases'))->assertOk();
                $this->get(route('portal.performance.packs'))->assertOk();
            }
        }
    }
}
