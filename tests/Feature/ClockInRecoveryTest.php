<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockInRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['status' => 'active', 'access_role' => 'staff']);
    }

    public function test_reopened_clock_in_link_returns_staff_to_dashboard_without_recording_attendance(): void
    {
        $this->actingAs($this->staff())->withHeaders(['X-Test-Enforce-ClockIn' => 'true'])
            ->get(route('portal.attendance.clock-in'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Please use Clock In on your dashboard to submit your attendance.');

        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_guest_reopened_link_requires_login(): void
    {
        $this->get(route('portal.attendance.clock-in'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_reopened_link_after_clock_in_keeps_existing_record(): void
    {
        $user = $this->staff();
        Attendance::create(['user_id' => $user->id, 'clock_in_at' => now(), 'status' => 'On Time', 'daily_objective' => 'Original']);

        $this->actingAs($user)->get(route('portal.attendance.clock-in'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'You are already clocked in for today.');
        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_missing_task_cannot_redirect_back_to_post_endpoint_and_keeps_notes(): void
    {
        $url = route('portal.attendance.clock-in');
        $this->actingAs($this->staff())->from($url)->post($url, ['daily_objective' => 'Site work', 'remote_notes' => 'Field'])
            ->assertRedirect(route('dashboard'))->assertSessionHasErrors('attendance')
            ->assertSessionHas('_old_input.daily_objective', 'Site work')
            ->assertSessionHas('_old_input.remote_notes', 'Field');
        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_invalid_input_returns_to_dashboard_and_json_still_reports_validation_errors(): void
    {
        $url = route('portal.attendance.clock-in');
        $this->actingAs($this->staff())->from($url)->post($url, ['latitude' => 'invalid'])
            ->assertRedirect(route('dashboard'))->assertSessionHasErrors('latitude');
        $this->postJson($url, ['latitude' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->assertDatabaseCount('attendance', 0);
    }

    private function enforceCsrf(): void
    {
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests() { return false; }
        });
    }

    public function test_expired_token_returns_to_dashboard_with_notes_and_does_not_clock_in(): void
    {
        $this->enforceCsrf();
        $url = route('portal.attendance.clock-in');
        $this->actingAs($this->staff())->withSession(['_token' => 'current'])->from($url)
            ->post($url, ['_token' => 'expired', 'daily_objective' => 'Site work', 'remote_notes' => 'Field'])
            ->assertRedirect(route('dashboard'))->assertSessionHasErrors('attendance')
            ->assertSessionHas('_old_input.daily_objective', 'Site work')
            ->assertSessionHas('_old_input.remote_notes', 'Field');
        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_expired_token_json_remains_an_error(): void
    {
        $this->enforceCsrf();
        $this->actingAs($this->staff())->withSession(['_token' => 'current'])
            ->postJson(route('portal.attendance.clock-in'), ['_token' => 'expired'])
            ->assertStatus(419)->assertJsonPath('message', 'Your clock-in session expired. Please use Clock In on the dashboard to try again.');
        $this->assertDatabaseCount('attendance', 0);
    }
}
