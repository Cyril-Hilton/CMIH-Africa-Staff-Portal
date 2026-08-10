<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_create_screen_renders_without_missing_partial_error(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $response = $this->actingAs($user)->get(route('portal.surveys.create'));

        $response->assertOk();
        $response->assertSee('Build Survey');
    }
}
