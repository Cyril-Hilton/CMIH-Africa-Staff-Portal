<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\MerchandiserResetPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Newpass12!';

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_link_can_be_requested_with_contact_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'peggy@cmih.africa',
            'contact_email' => 'peggy.personal@example.com',
        ]);

        $this->post('/forgot-password', ['email' => 'peggy.personal@example.com'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);

        $notification = new ResetPassword('token');
        $this->assertSame('peggy.personal@example.com', $user->routeNotificationForMail($notification));
    }

    public function test_merchandiser_reset_link_can_be_requested_from_merchandiser_login_with_contact_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'sagya.agent@cmih.africa',
            'contact_email' => 'sagyapomaa12@gmail.com',
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $this->get(route('merchandisers.password.request'))
            ->assertOk()
            ->assertSee('Merchandiser reset links are sent to the contact email on file.');

        $this->post(route('merchandisers.password.email'), [
            'email' => 'sagyapomaa12@gmail.com',
            'portal' => 'merchandisers',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, MerchandiserResetPassword::class, function (MerchandiserResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertStringContainsString('portal=merchandisers', $mail->actionUrl);
            $this->assertStringContainsString(rawurlencode('sagyapomaa12@gmail.com'), $mail->actionUrl);

            return true;
        });
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_can_be_reset_when_contact_email_is_entered(): void
    {
        $user = User::factory()->create([
            'email' => 'peggy@cmih.africa',
            'contact_email' => 'peggy.personal@example.com',
        ]);
        $token = \Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'peggy.personal@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password));
    }

    public function test_merchandiser_password_reset_redirects_back_to_merchandiser_login(): void
    {
        $user = User::factory()->create([
            'email' => 'ernestina.agent@cmih.africa',
            'contact_email' => 'ernestinamawutor6@gmail.com',
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);
        $token = \Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'ernestinamawutor6@gmail.com',
            'portal' => 'merchandisers',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('merchandisers.login'));

        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password));
    }

    public function test_password_reset_requires_more_than_eight_characters_letter_number_and_symbol(): void
    {
        $user = User::factory()->create();
        $token = \Password::broker()->createToken($user);

        $response = $this->from('/reset-password/'.$token)->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/reset-password/'.$token);
    }
}
