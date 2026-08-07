<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_department_labels_are_normalized_for_id_cards(): void
    {
        $this->assertSame('HR & Admin', User::departmentLabel('HR & Admin Department'));
        $this->assertSame('Operations & Projects', User::departmentLabel('operations_projects'));
        $this->assertSame('Operations & Projects', User::departmentLabel('Operations and Projects'));
        $this->assertSame('Brands & Marketing', User::departmentLabel('Brands & Marketing'));
        $this->assertSame('Client Relations', User::departmentLabel('client service'));
        $this->assertSame('Creatives', User::departmentLabel('Creative Department'));
        $this->assertSame('Unassigned', User::departmentLabel(null));
    }

    public function test_printable_id_card_displays_normalized_department_name(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'department' => 'Operations and Projects Department',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('portal.id-card'));

        $response->assertOk();
        $response->assertSee('Operations & Projects Department');
        $response->assertDontSee('Unassigned Department');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_phone_number_can_be_updated_by_staff(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'phone' => '+233500000000',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '+233545566524',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('+233545566524', $user->refresh()->phone);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_page_does_not_offer_account_deletion(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Delete Account');
        $this->assertFalse(Route::has('profile.destroy'));
    }

    public function test_staff_cannot_delete_their_account_from_profile_endpoint(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
