<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffPortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cmih.app_kind' => 'staff']);
    }

    public function test_main_admin_users_directory_excludes_external_merchandisers(): void
    {
        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'Internal Staff Member',
            'email' => 'staff-member@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'External Merchandiser',
            'email' => 'external-merch@cmih.africa',
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk();
        $response->assertSee('Internal Staff Member');
        $response->assertDontSee('External Merchandiser');
    }

    public function test_directory_upcoming_birthdays_only_shows_birthdays_in_the_next_30_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 30, 12, 0, 0));

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin2@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'Chris Ghansah',
            'email' => 'chris@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'birthday_month' => 6,
            'birthday_day' => 1,
        ]);

        User::factory()->create([
            'name' => 'Future July Staff',
            'email' => 'future-july@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'birthday_month' => 7,
            'birthday_day' => 1,
        ]);

        User::factory()->create([
            'name' => 'Far August Staff',
            'email' => 'far-august@cmih.africa',
            'access_role' => 'staff',
            'status' => 'active',
            'birthday_month' => 8,
            'birthday_day' => 15,
        ]);

        $response = $this->actingAs($admin)->get(route('portal.directory'));

        $response->assertOk();
        $response->assertDontSee('1 / June');
        $response->assertSee('Future July Staff');
        $response->assertSee('1 / July');
        $response->assertDontSee('15 / August');

        Carbon::setTestNow();
    }

    public function test_old_cmih_merchandiser_admin_get_pages_redirect_to_new_merchandiser_hub(): void
    {
        $admin = User::factory()->create([
            'name' => 'Brands Admin',
            'email' => 'brands-admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'department' => 'brands_marketing',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('portal.merchandisers-admin.pairings'));

        $response->assertRedirect(route('merchandisers.admin.tab', ['adminTab' => 'merchandisers']));
    }

    public function test_brands_split_keeps_staff_admin_login_on_brands_domain(): void
    {
        config(['cmih.app_kind' => 'brands']);

        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_brands_split_redirects_staff_routes_to_staff_domain_before_auth(): void
    {
        config([
            'cmih.app_kind' => 'brands',
            'cmih.urls.staff' => 'https://portal.cmih.africa',
        ]);

        $response = $this->get('/portal/tasks');

        $response->assertRedirect('https://portal.cmih.africa/portal/tasks');
    }

    public function test_staff_split_redirects_merchandiser_routes_to_brands_domain_before_auth(): void
    {
        config([
            'cmih.app_kind' => 'staff',
            'cmih.urls.brands' => 'https://brands.cmih.africa',
        ]);

        $response = $this->get('/merchandisers/login');

        $response->assertRedirect('https://brands.cmih.africa/merchandisers/login');
    }

    public function test_split_apps_allow_exact_reset_password_post_path(): void
    {
        config(['cmih.app_kind' => 'staff']);

        $staffResponse = $this->from('/reset-password/test-token')->post('/reset-password', []);
        $staffResponse->assertRedirect('/reset-password/test-token');
        $staffResponse->assertSessionHasErrors(['token', 'email', 'password']);

        config(['cmih.app_kind' => 'brands']);

        $brandsResponse = $this->from('/reset-password/test-token')->post('/reset-password', []);
        $brandsResponse->assertRedirect('/reset-password/test-token');
        $brandsResponse->assertSessionHasErrors(['token', 'email', 'password']);
    }

    public function test_root_domain_redirects_dashboard_to_staff_subdomain_with_query_string(): void
    {
        config([
            'cmih.app_kind' => 'all',
            'cmih.urls.website' => 'https://www.cmih.africa',
            'cmih.urls.staff' => 'https://portal.cmih.africa',
        ]);

        $response = $this->get('https://cmih.africa/dashboard?weekly_department=client_relations');

        $response->assertRedirect('https://portal.cmih.africa/dashboard?weekly_department=client_relations');
    }

    public function test_root_domain_redirects_merchandiser_admin_to_brands_subdomain(): void
    {
        config([
            'cmih.app_kind' => 'all',
            'cmih.urls.website' => 'https://www.cmih.africa',
            'cmih.urls.brands' => 'https://brands.cmih.africa',
        ]);

        $response = $this->get('https://cmih.africa/merchandisers/admin');

        $response->assertRedirect('https://brands.cmih.africa/merchandisers/admin');
    }
}
