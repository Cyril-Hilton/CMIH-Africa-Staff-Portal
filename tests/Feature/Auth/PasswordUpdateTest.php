<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Newpass12!';

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_password_requires_more_than_eight_characters_letter_number_and_symbol(): void
    {
        $user = User::factory()->create();

        foreach (['Short1!', 'Password!', 'Password1', '12345678!'] as $invalidPassword) {
            $response = $this
                ->actingAs($user)
                ->from('/profile')
                ->put('/password', [
                    'current_password' => 'password',
                    'password' => $invalidPassword,
                    'password_confirmation' => $invalidPassword,
                ]);

            $response
                ->assertSessionHasErrorsIn('updatePassword', 'password')
                ->assertRedirect('/profile');

            $this->assertFalse(Hash::check($invalidPassword, $user->refresh()->password));
        }
    }
}
