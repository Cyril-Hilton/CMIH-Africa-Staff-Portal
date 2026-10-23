<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemovedPowerBiReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_reports_have_no_navigation_or_reachable_endpoints(): void
    {
        $user = User::factory()->create(['access_role' => 'super_admin', 'department' => 'hr_admin', 'status' => 'active']);
        $this->actingAs($user)->get(route('portal.hr'))->assertOk()->assertDontSee('Power BI Reports')->assertDontSee('/portal/power-bi', false);
        $this->get('/portal/power-bi')->assertNotFound();
        $this->post('/portal/power-bi', [])->assertNotFound();
        $this->patch('/portal/power-bi/1', [])->assertNotFound();
        $this->get('/portal/power-bi/1/open')->assertNotFound();
    }
}
