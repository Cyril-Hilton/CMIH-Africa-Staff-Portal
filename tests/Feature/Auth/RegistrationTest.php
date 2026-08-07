<?php

namespace Tests\Feature\Auth;

use App\Mail\PortalCredentialsMail;
use App\Mail\StaffIdCardMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();
        Storage::fake('public');

        $superAdmin = User::factory()->create([
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'contact_email' => 'test@example.com',
            'phone' => '123456789',
            'department' => 'operations_projects',
            'job_title' => 'Software Engineer',
            'position_title' => 'Developer',
            'date_of_birth' => '1990-01-01',
            'start_date' => '2026-01-01',
            'profile_photo' => UploadedFile::fake()->create('profile.jpg', 100),
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'contact_email' => 'test@example.com',
            'status' => 'pending',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('generated_email');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'title' => 'New Staff Account Approval Needed',
        ]);

        Mail::assertSent(PortalCredentialsMail::class);
        Mail::assertSent(StaffIdCardMail::class);
    }
}
