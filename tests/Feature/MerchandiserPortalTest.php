<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Region;
use App\Models\KeyDistributor;
use App\Models\MerchandiserLocation;
use App\Models\Outlet;
use App\Models\SiteContent;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserComplianceQuery;
use App\Models\MerchandiserGoogleFormAssignment;
use App\Models\MerchandiserGoogleFormSubmission;
use App\Models\MerchandiserNativeFormSubmission;
use App\Models\MerchandiserOutletAssignment;
use App\Models\MerchandiserPcmClockin;
use App\Models\MerchandiserPjp;
use App\Models\MerchandiserPjpClockin;
use App\Models\MerchandiserPlanogram;
use App\Models\MerchandiserSupervisorAssignment;
use App\Models\MerchandiserVisit;
use App\Models\MerchandiserVisitSku;
use App\Models\Sku;
use App\Services\MerchandiserRoutePlanner;
use App\Services\PerfectStoreFormTemplate;
use App\Services\PerfectStoreKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MerchandiserPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure user with ID 1 exists and is a super_admin.
        // Seed migrations may have already created users, so we upgrade
        // the existing user at ID 1 (or create one if none exists).
        if (User::where('id', 1)->exists()) {
            User::where('id', 1)->update([
                'name'          => 'System Setup Admin',
                'email'         => 'setup-admin@cmih.africa',
                'contact_email' => 'setup-admin@cmih.africa',
                'access_role'   => 'super_admin',
                'status'        => 'active',
            ]);
        } else {
            // Use forceCreate so the explicit id=1 is honoured (bypasses fillable)
            User::forceCreate([
                'id'            => 1,
                'name'          => 'System Setup Admin',
                'email'         => 'setup-admin@cmih.africa',
                'contact_email' => 'setup-admin@cmih.africa',
                'password'      => Hash::make('Pass123'),
                'access_role'   => 'super_admin',
                'status'        => 'active',
            ]);
        }

        // Add default settings
        SiteContent::updateOrCreate(
            ['key' => 'merchandiser_radius'],
            ['value' => '30', 'type' => 'text', 'updated_by' => 1]
        );
    }

    private function recordOutletClockIn(User $user, Outlet $outlet, ?Carbon $time = null): MerchandiserAttendance
    {
        return MerchandiserAttendance::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'clock_in_time' => $time ?: now(),
            'latitude' => $outlet->latitude ?? 5.6037,
            'longitude' => $outlet->longitude ?? -0.1870,
            'distance_from_outlet' => 0,
            'status' => 'on-time',
        ]);
    }

    #[Test]
    public function candidate_age_must_be_between_18_and_65()
    {
        // 17 years old - should fail
        $response1 = $this->post(route('merchandisers.register'), [
            'name' => 'Too Young',
            'email' => 'young@cmih.africa',
            'contact_email' => 'young@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(17)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response1->assertSessionHasErrors('date_of_birth');

        // 66 years old - should fail
        $response2 = $this->post(route('merchandisers.register'), [
            'name' => 'Too Old',
            'email' => 'old@cmih.africa',
            'contact_email' => 'old@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(66)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response2->assertSessionHasErrors('date_of_birth');

        // 25 years old - should pass
        $response3 = $this->post(route('merchandisers.register'), [
            'name' => 'Valid Agent',
            'email' => 'valid@cmih.africa',
            'contact_email' => 'valid@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response3->assertRedirect(route('merchandisers.login'));
        $this->assertDatabaseHas('users', [
            'email' => 'valid@cmih.africa',
            'access_role' => 'merchandiser',
            'status' => 'pending'
        ]);
    }
    #[Test]
    public function password_must_have_more_than_eight_characters_letter_number_and_symbol()
    {
        $response = $this->post(route('merchandisers.register'), [
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('password');

        $valid = $this->post(route('merchandisers.register'), [
            'name' => 'Secure Agent',
            'email' => 'secure-agent@cmih.africa',
            'contact_email' => 'secure-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $valid->assertRedirect(route('merchandisers.login'));
        $this->assertDatabaseHas('users', [
            'email' => 'secure-agent@cmih.africa',
            'access_role' => 'merchandiser',
        ]);
    }
    #[Test]
    public function duplicate_merchandiser_contact_email_returns_validation_error_instead_of_server_error()
    {
        User::create([
            'name' => 'Existing Agent',
            'email' => 'existing-agent@cmih.africa',
            'contact_email' => 'existing-personal@example.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => Hash::make('Password123!'),
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $response = $this->from(route('merchandisers.register'))->post(route('merchandisers.register'), [
            'name' => 'Duplicate Agent',
            'email' => 'fresh-agent@cmih.africa',
            'contact_email' => 'existing-personal@example.com',
            'phone' => '87654321',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('merchandisers.register'));
        $response->assertSessionHasErrors('contact_email');
        $this->assertDatabaseMissing('users', [
            'email' => 'fresh-agent@cmih.africa',
        ]);
    }
    #[Test]
    public function merchandiser_login_exposes_forgot_password_link()
    {
        $this->get(route('merchandisers.login'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee(route('merchandisers.password.request'), false);
    }
    #[Test]
    public function pending_merchandisers_cannot_log_in()
    {
        $user = User::create([
            'name' => 'Pending Agent',
            'email' => 'pending@cmih.africa',
            'contact_email' => 'pending@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'pending'
        ]);

        $response = $this->post('/merchandisers/login', [
            'email' => 'pending@cmih.africa',
            'password' => 'Pass123'
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(auth()->check());
    }
    #[Test]
    public function active_merchandiser_can_log_in()
    {
        $user = User::create([
            'name' => 'Active Agent',
            'email' => 'active@cmih.africa',
            'contact_email' => 'active@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $response = $this->post('/merchandisers/login', [
            'email' => 'active@cmih.africa',
            'password' => 'Pass123'
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertTrue(auth()->check());
    }
    #[Test]
    public function brands_team_member_can_use_existing_cmih_credentials_for_merchandiser_admin()
    {
        $brandUser = User::create([
            'name' => 'Stephanie Brands',
            'email' => 'stephanie@cmih.africa',
            'contact_email' => 'stephanie.personal@example.com',
            'phone' => '12345678',
            'password' => Hash::make('Pass123'),
            'access_role' => 'staff',
            'job_level' => 'executive',
            'department' => 'brands_marketing',
            'status' => 'active',
        ]);

        $response = $this->post(route('merchandisers.login'), [
            'email' => 'stephanie@cmih.africa',
            'password' => 'Pass123',
        ]);

        $response->assertRedirect(route('merchandisers.admin.dashboard'));
        $this->assertAuthenticatedAs($brandUser);

        $this->get(route('merchandisers.admin.dashboard'))
            ->assertOk();
    }
    #[Test]
    public function active_merchandiser_using_main_login_is_routed_to_merchandiser_dashboard()
    {
        $user = User::create([
            'name' => 'Main Login Agent',
            'email' => 'main-login-agent@cmih.africa',
            'contact_email' => 'main-login-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'main-login-agent@cmih.africa',
            'password' => 'Pass123',
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertAuthenticatedAs($user);
    }
    #[Test]
    public function new_merchandiser_registration_notifies_brands_team_admins()
    {
        $brandUser = User::create([
            'name' => 'Brands Approver',
            'email' => 'brands-approver@cmih.africa',
            'contact_email' => 'brands-approver@personal.com',
            'phone' => '12345678',
            'password' => Hash::make('Pass123'),
            'access_role' => 'staff',
            'job_level' => 'executive',
            'department' => 'brands_marketing',
            'status' => 'active',
        ]);

        $response = $this->post(route('merchandisers.register'), [
            'name' => 'New Field Agent',
            'email' => 'new-field-agent@cmih.africa',
            'contact_email' => 'new-field-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('merchandisers.login'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $brandUser->id,
            'title' => 'New merchandiser registration needs approval',
        ]);
    }
    #[Test]
    public function clock_in_window_boundaries_enforced()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Test KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);
        $outlet = Outlet::create([
            'name' => 'Test Outlet',
            'code' => 'OUT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);

        // The new outlet visit window opens at 8:00 AM and closes at 5:00 PM.
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(7, 0, 0));

        $response = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $response->assertStatus(403); // AccessDenied: Window Closed

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 30, 0));

        $response2 = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $response2->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet'
        ]);

        Carbon::setTestNow(); // Reset clock
    }

    #[Test]
    public function merchandiser_can_clock_in_multiple_assigned_outlets_during_open_visit_window()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Multi Outlet KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Multi Stop Agent',
            'email' => 'multi-stop@cmih.africa',
            'contact_email' => 'multi-stop@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $outlets = collect([
            Outlet::create(['name' => 'PJP Stop One', 'code' => 'PJP-001', 'kd_id' => $kd->id, 'channel_type' => 'GT', 'latitude' => 5.6037, 'longitude' => -0.1870]),
            Outlet::create(['name' => 'PJP Stop Two', 'code' => 'PJP-002', 'kd_id' => $kd->id, 'channel_type' => 'GT', 'latitude' => 5.6038, 'longitude' => -0.1871]),
        ]);

        $outlets->each(function (Outlet $outlet, int $index) use ($user) {
            $outlet->assignedMerchandisers()->attach($user->id, ['assigned_by' => 1, 'assigned_at' => now()]);
            MerchandiserOutletAssignment::create([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'assigned_date' => Carbon::today('Africa/Accra')->toDateString(),
                'sequence' => $index + 1,
                'status' => 'planned',
            ]);
        });

        $this->actingAs($user);
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 15, 0));

        foreach ($outlets as $outlet) {
            $this->post(route('merchandisers.clock-in'), [
                'outlet_id' => $outlet->id,
                'clock_in_type' => 'outlet',
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
            ])->assertRedirect(route('merchandisers.dashboard'));
        }

        $this->assertSame(2, MerchandiserAttendance::where('user_id', $user->id)->where('clock_in_type', 'outlet')->count());
        Carbon::setTestNow();
    }

    #[Test]
    public function merchandiser_clocks_out_after_perfect_store_entry_and_duration_is_recorded()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Duration KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $outlet = Outlet::create([
            'name' => 'Duration Outlet',
            'code' => 'DURATION-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Duration Agent',
            'email' => 'duration-agent@cmih.africa',
            'contact_email' => 'duration-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, ['assigned_by' => 1, 'assigned_at' => now()]);
        $assignment = MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => Carbon::today('Africa/Accra')->toDateString(),
            'sequence' => 1,
            'status' => 'planned',
        ]);

        $this->actingAs($user);
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 30, 0));
        $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ])->assertRedirect(route('merchandisers.dashboard'));

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 50, 0));
        MerchandiserVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'route_assignment_id' => $assignment->id,
            'branded_shelf_available' => true,
            'hangers_available' => true,
        ]);

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 5, 0));
        $this->post(route('merchandisers.clock-out'), [
            'outlet_id' => $outlet->id,
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ])->assertRedirect(route('merchandisers.dashboard'));

        $attendance = MerchandiserAttendance::where('user_id', $user->id)->where('outlet_id', $outlet->id)->first();
        $this->assertNotNull($attendance->clock_out_time);
        $this->assertSame(35, $attendance->visit_duration_minutes);
        $this->assertDatabaseHas('merchandiser_outlet_assignments', [
            'id' => $assignment->id,
            'status' => 'visited',
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function assigned_merchandiser_can_register_outlet_for_their_kd_and_then_clock_in()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Awen Yami',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $user = User::create([
            'name' => 'Diana Dankwiah',
            'email' => 'diana@cmih.africa',
            'contact_email' => 'diana@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 30, 0));

        $dashboard = $this->actingAs($user)->get(route('merchandisers.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Register an Outlet');
        $dashboard->assertSee('No outlets registered for your Key Distributor yet.');

        $storeResponse = $this->post(route('merchandisers.outlets.store'), [
            'name' => 'Awen Yami Osu Shop',
            'code' => 'AY-OSU-001',
            'channel_type' => 'GT',
            'address' => 'Osu, Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);

        $storeResponse->assertRedirect(route('merchandisers.dashboard'));

        $outlet = Outlet::where('code', 'AY-OSU-001')->first();
        $this->assertNotNull($outlet);
        $this->assertSame($kd->id, $outlet->kd_id);
        $this->assertSame($user->id, $outlet->registered_by);

        $response = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet'
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function clocked_in_merchandiser_can_work_without_identity_docs_for_now()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Identity KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);

        $user = User::create([
            'name' => 'Identity Pending Agent',
            'email' => 'identity-agent@cmih.africa',
            'contact_email' => 'identity-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $outlet = Outlet::create([
            'name' => 'Identity Outlet',
            'code' => 'IDENTITY-OUTLET',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);

        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        MerchandiserAttendance::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'clock_in_time' => now(),
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'distance_from_outlet' => 0,
            'status' => 'on-time',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Enforce-Identity-Docs', '1')
            ->get(route('merchandisers.visit', $outlet));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }
    #[Test]
    public function assigned_merchandiser_sees_outlet_visit_window_before_outlets_exist()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'PCM Base KD',
            'region_id' => $region->id,
            'address' => 'North Legon',
            'latitude' => 5.65000000,
            'longitude' => -0.18000000,
        ]);
        $user = User::create([
            'name' => 'Diana Dankwiah',
            'email' => 'diana.pcm@cmih.africa',
            'contact_email' => 'diana.pcm@example.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 20));

        $dashboard = $this->actingAs($user)->get(route('merchandisers.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Outlet Visit Window');
        $dashboard->assertDontSee('Clock in at KD / PCM');
        $dashboard->assertSee('Register an Outlet');
        $dashboard->assertSee('data-clock-in-form', false);
        $dashboard->assertSee('data-clock-submit', false);
        $dashboard->assertDontSee('Clocking in...');

        Carbon::setTestNow();
    }
    #[Test]
    public function admin_can_generate_daily_route_assignments_and_merchandiser_only_sees_todays_route()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ROUTE ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Route KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Route Merchandiser',
            'email' => 'route-merch@cmih.africa',
            'contact_email' => 'route-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        foreach (range(1, 5) as $index) {
            Outlet::create([
                'name' => 'Route Outlet ' . $index,
                'code' => 'ROUTE-' . $index,
                'kd_id' => $kd->id,
                'registered_by' => $user->id,
                'channel_type' => 'GT',
                'latitude' => 5.60000000 + ($index / 10000),
                'longitude' => -0.18000000,
            ]);
        }

        $this->actingAs($admin)->post(route('merchandisers.admin.merchandisers.route-settings', $user), [
            'merchandiser_working_days' => [1],
            'merchandiser_daily_outlet_target' => 2,
            'merchandiser_outlet_frequency' => 'weekly',
        ])->assertRedirect();

        $this->assertDatabaseCount('merchandiser_outlet_assignments', 5);
        $assignment = MerchandiserOutletAssignment::where('user_id', $user->id)
            ->whereDate('assigned_date', '2026-07-20')
            ->firstOrFail();

        $this->assertSame('planned', $assignment->status);

        $response = $this->actingAs($user)->get(route('merchandisers.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Assigned Outlets', false);
        $response->assertSee('Route Outlet 1');
        $response->assertSee('Route Outlet 2');
        $response->assertSee('Route Outlet 3');
        $response->assertSee('Route Outlet 5');

        Carbon::setTestNow();
    }
    #[Test]
    public function admin_assigned_outlets_are_routeable_even_when_registered_by_someone_else()
    {
        $region = Region::create(['name' => 'ASSIGNED ROUTE ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Assigned Route KD', 'region_id' => $region->id]);

        $user = User::create([
            'name' => 'Assigned Outlet Merchandiser',
            'email' => 'assigned-route-merch@cmih.africa',
            'contact_email' => 'assigned-route-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'merchandiser_working_days' => [1],
            'merchandiser_daily_outlet_target' => 1,
            'merchandiser_outlet_frequency' => 'weekly',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        $outlet = Outlet::create([
            'name' => 'Admin Assigned Outlet',
            'code' => 'ADMIN-ASSIGNED-OUTLET',
            'kd_id' => $kd->id,
            'registered_by' => 1,
            'channel_type' => 'GT',
            'latitude' => 5.60400000,
            'longitude' => -0.18700000,
        ]);

        Carbon::setTestNow();

        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        $planner = app(MerchandiserRoutePlanner::class);
        $date = Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra');

        $created = $planner->ensurePeriod($user, $date->copy(), $date->copy()->endOfDay());
        $assignments = $planner->assignmentsForDate($user, $date);

        $this->assertCount(1, $created);
        $this->assertCount(1, $assignments);
        $this->assertSame($outlet->id, $assignments->first()->outlet_id);
    }
    #[Test]
    public function legacy_default_route_target_auto_sizes_todays_closure_list_without_alphabetical_truncation()
    {
        $region = Region::create(['name' => 'AUTO ROUTE ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Auto Route KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Auto Route Merchandiser',
            'email' => 'auto-route-merch@cmih.africa',
            'contact_email' => 'auto-route-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'merchandiser_working_days' => [1],
            'merchandiser_daily_outlet_target' => 8,
            'merchandiser_outlet_frequency' => 'weekly',
        ]);

        $outletNames = [
            'Zebra First Outlet',
            'Alpha Second Outlet',
            'Beta Third Outlet',
            'Delta Fourth Outlet',
            'Echo Fifth Outlet',
            'Foxtrot Sixth Outlet',
            'Gamma Seventh Outlet',
            'Hotel Eighth Outlet',
            'India Ninth Outlet',
            'Juliet Tenth Outlet',
            'Kilo Eleventh Outlet',
            'Lima Twelfth Outlet',
        ];

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        foreach ($outletNames as $index => $name) {
            Outlet::create([
                'name' => $name,
                'code' => 'AUTO-' . ($index + 1),
                'kd_id' => $kd->id,
                'registered_by' => $user->id,
                'channel_type' => 'GT',
            ]);
        }

        app(MerchandiserRoutePlanner::class)->ensureWeek($user, Carbon::now('Africa/Accra'));

        $assignments = MerchandiserOutletAssignment::with('outlet')
            ->where('user_id', $user->id)
            ->whereDate('assigned_date', '2026-07-20')
            ->orderBy('sequence')
            ->get();

        $this->assertCount(12, $assignments);
        $this->assertSame($outletNames, $assignments->pluck('outlet.name')->all());

        $response = $this->actingAs($user)->get(route('merchandisers.dashboard'));

        $response->assertOk();
        $response->assertSeeText('12 not covered of 12');
        $response->assertSeeInOrder([
            'Zebra First Outlet',
            'Alpha Second Outlet',
            'Beta Third Outlet',
            'Lima Twelfth Outlet',
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function route_generation_skips_outlets_created_on_configured_public_holiday_dates()
    {
        Config::set('merchandiser.public_holidays', ['2026-07-20']);

        $region = Region::create(['name' => 'HOLIDAY ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Holiday KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Holiday Merchandiser',
            'email' => 'holiday-merch@cmih.africa',
            'contact_email' => 'holiday-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'merchandiser_working_days' => [1, 2],
            'merchandiser_daily_outlet_target' => 1,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        foreach (range(1, 2) as $index) {
            Outlet::create([
                'name' => 'Holiday Outlet ' . $index,
                'code' => 'HOL-' . $index,
                'kd_id' => $kd->id,
                'registered_by' => $user->id,
                'channel_type' => 'GT',
            ]);
        }

        Carbon::setTestNow();

        app(MerchandiserRoutePlanner::class)->ensureWeek($user, Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        $this->assertSame(0, MerchandiserOutletAssignment::where('user_id', $user->id)->whereDate('assigned_date', '2026-07-20')->count());
        $this->assertSame(0, MerchandiserOutletAssignment::where('user_id', $user->id)->whereDate('assigned_date', '2026-07-21')->count());
    }
    #[Test]
    public function route_generation_uses_outlet_creation_dates_and_sets_schedule_windows()
    {
        $region = Region::create(['name' => 'FREQUENCY ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Frequency KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Frequency Merchandiser',
            'email' => 'frequency-merch@cmih.africa',
            'contact_email' => 'frequency-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'merchandiser_working_days' => [1, 2, 3],
            'merchandiser_daily_outlet_target' => 2,
            'merchandiser_outlet_frequency' => 'weekly',
        ]);

        foreach ([
            1 => '2026-07-20 08:15:00',
            2 => '2026-07-20 09:20:00',
            3 => '2026-07-21 08:30:00',
            4 => '2026-07-21 11:45:00',
            5 => '2026-07-22 12:00:00',
        ] as $index => $createdAt) {
            Carbon::setTestNow(Carbon::parse($createdAt, 'Africa/Accra'));

            Outlet::create([
                'name' => 'Frequency Outlet ' . $index,
                'code' => 'FREQ-' . $index,
                'kd_id' => $kd->id,
                'registered_by' => $user->id,
                'channel_type' => 'GT',
            ]);
        }

        Carbon::setTestNow();

        app(MerchandiserRoutePlanner::class)->ensurePeriod(
            $user,
            Carbon::create(2026, 7, 20, 9, 30, 0, 'Africa/Accra'),
            Carbon::create(2026, 7, 22, 17, 45, 0, 'Africa/Accra')
        );

        $this->assertSame(5, MerchandiserOutletAssignment::where('user_id', $user->id)->count());
        $this->assertSame(2, MerchandiserOutletAssignment::where('user_id', $user->id)->whereDate('assigned_date', '2026-07-20')->count());
        $this->assertSame(2, MerchandiserOutletAssignment::where('user_id', $user->id)->whereDate('assigned_date', '2026-07-21')->count());
        $this->assertSame(1, MerchandiserOutletAssignment::where('user_id', $user->id)->whereDate('assigned_date', '2026-07-22')->count());

        $firstAssignment = MerchandiserOutletAssignment::where('user_id', $user->id)->orderBy('assigned_date')->firstOrFail();
        $this->assertSame('2026-07-20 09:30:00', $firstAssignment->assigned_start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 17:45:59', $firstAssignment->assigned_end_at->format('Y-m-d H:i:s'));
    }
    #[Test]
    public function admin_route_planning_dashboard_filters_by_datetime_range_and_paginates_assignments()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'PAGED ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Paged KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Paged Merchandiser',
            'email' => 'paged-merch@cmih.africa',
            'contact_email' => 'paged-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        foreach (range(1, 30) as $index) {
            $outlet = Outlet::create([
                'name' => 'Paged Outlet ' . $index,
                'code' => 'PAGED-' . $index,
                'kd_id' => $kd->id,
                'channel_type' => 'GT',
            ]);

            MerchandiserOutletAssignment::create([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'assigned_date' => '2026-07-20',
                'assigned_start_at' => Carbon::create(2026, 7, 20, 9, 0, 0, 'Africa/Accra'),
                'assigned_end_at' => Carbon::create(2026, 7, 20, 18, 0, 0, 'Africa/Accra'),
                'sequence' => $index,
                'status' => $index <= 3 ? 'completed' : 'planned',
                'source' => 'auto',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.dashboard', [
            'tab' => 'routes',
            'route_from' => '2026-07-20T08:00',
            'route_to' => '2026-07-20T18:30',
        ]));

        $response->assertOk();
        $response->assertSeeText('Total Assignments');
        $response->assertSeeText('Showing 1-25 of 30 rows');
        $response->assertSee('route_page=2', false);
    }
    #[Test]
    public function completing_google_form_marks_matching_route_assignment_completed()
    {
        $region = Region::create(['name' => 'FORM ROUTE ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Form Route KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Form Route Merchandiser',
            'email' => 'form-route-merch@cmih.africa',
            'contact_email' => 'form-route-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outlet = Outlet::create([
            'name' => 'Form Route Outlet',
            'code' => 'FORM-ROUTE-1',
            'kd_id' => $kd->id,
            'channel_type' => 'SSM',
        ]);
        $form = MerchandiserGoogleFormAssignment::create([
            'title' => 'Route Completion Form',
            'google_form_url' => 'https://docs.google.com/forms/d/e/test/viewform',
            'assigned_user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'kd_id' => $kd->id,
            'google_enabled' => true,
            'native_enabled' => false,
            'starts_on' => '2026-07-20',
            'ends_on' => '2026-07-20',
            'status' => 'active',
        ]);
        $assignment = MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => '2026-07-20',
            'assigned_start_at' => Carbon::create(2026, 7, 20, 9, 0, 0, 'Africa/Accra'),
            'assigned_end_at' => Carbon::create(2026, 7, 20, 18, 0, 0, 'Africa/Accra'),
            'sequence' => 1,
            'status' => 'planned',
            'source' => 'auto',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 12, 0, 0, 'Africa/Accra'));

        $this->actingAs($user)->post(route('merchandisers.google-forms.complete', $form), [
            'outlet_id' => $outlet->id,
            'response_reference' => 'GF-123',
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_google_form_submissions', [
            'form_assignment_id' => $form->id,
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'response_reference' => 'GF-123',
        ]);
        $this->assertSame('completed', $assignment->fresh()->status);

        Carbon::setTestNow();
    }
    #[Test]
    public function queued_clock_in_uses_client_recorded_time_and_sync_token_once()
    {
        $region = Region::create(['name' => 'SYNC ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Sync KD',
            'region_id' => $region->id,
            'latitude' => 5.61745000,
            'longitude' => -0.16812000,
        ]);
        $outlet = Outlet::create([
            'name' => 'Sync Outlet',
            'code' => 'SYNC-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.61745000,
            'longitude' => -0.16812000,
        ]);
        $user = User::create([
            'name' => 'Sync Merchandiser',
            'email' => 'sync-merch@cmih.africa',
            'contact_email' => 'sync-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);
        MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => '2026-07-20',
            'sequence' => 1,
            'status' => 'planned',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 9, 30, 0, 'Africa/Accra'));

        $payload = [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.61745000,
            'longitude' => -0.16812000,
            'client_recorded_at' => '2026-07-20T08:55:00+00:00',
            'sync_token' => 'offline-sync-token-1',
            'sync_source' => 'offline_retry',
        ];

        $this->actingAs($user)->post(route('merchandisers.clock-in'), $payload)
            ->assertRedirect(route('merchandisers.dashboard'));

        $this->assertDatabaseHas('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'sync_token' => 'offline-sync-token-1',
            'sync_source' => 'offline_retry',
            'status' => 'on-time',
        ]);

        $this->actingAs($user)->post(route('merchandisers.clock-in'), $payload)
            ->assertRedirect(route('merchandisers.dashboard'));

        $this->assertSame(1, MerchandiserAttendance::where('sync_token', 'offline-sync-token-1')->count());

        Carbon::setTestNow();
    }
    #[Test]
    public function merchandiser_can_complete_google_form_and_store_planogram_assessment_on_visit()
    {
        Storage::fake('public');
        $region = Region::create(['name' => 'PLAN ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Plan KD', 'region_id' => $region->id]);
        $outlet = Outlet::create([
            'name' => 'Planogram Outlet',
            'code' => 'PLAN-001',
            'kd_id' => $kd->id,
            'channel_type' => 'SSM',
        ]);
        $user = User::create([
            'name' => 'Plan Merchandiser',
            'email' => 'plan-merch@cmih.africa',
            'contact_email' => 'plan-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create(['name' => 'Perfect Store SKU']);
        $planogram = MerchandiserPlanogram::create([
            'title' => 'SSM Perfect Store',
            'category' => 'Skin Care',
            'channel_type' => 'SSM',
            'checklist' => ['Shelf talker visible', 'Category divider in place'],
            'status' => 'active',
            'created_by' => 1,
        ]);
        $form = MerchandiserGoogleFormAssignment::create([
            'title' => 'Perfect Store Audit',
            'google_form_url' => 'https://docs.google.com/forms/d/e/test/viewform',
            'assigned_user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'status' => 'active',
            'created_by' => 1,
        ]);
        MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => now('Africa/Accra')->toDateString(),
            'sequence' => 1,
            'status' => 'planned',
        ]);

        $visitPage = $this->actingAs($user)->get(route('merchandisers.visit', $outlet));
        $visitPage->assertOk();
        $visitPage->assertSeeText('Google Forms & Surveys', false);
        $visitPage->assertSee('Planogram Assessment');

        $this->post(route('merchandisers.google-forms.complete', $form), [
            'outlet_id' => $outlet->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_google_form_submissions', [
            'form_assignment_id' => $form->id,
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->recordOutletClockIn($user, $outlet);

        $this->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 1,
            'planogram_id' => $planogram->id,
            'planogram_score' => 88,
            'planogram_notes' => 'Shelf talker is visible. Category divider needs replacement.',
            'sku_entry_mode' => 'manual',
            'skus' => [
                $sku->id => [
                    'osa_quantity' => 12,
                    'npd_present' => 1,
                    'facing' => 4,
                    'share_of_shelf' => 35,
                    'planogram_compliant' => 1,
                ],
            ],
        ])->assertRedirect(route('merchandisers.dashboard'));

        $this->assertDatabaseHas('merchandiser_visits', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'planogram_id' => $planogram->id,
            'planogram_score' => 88,
        ]);
        $this->assertDatabaseHas('merchandiser_outlet_assignments', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'status' => 'completed',
        ]);
    }
    #[Test]
    public function merchandiser_can_submit_full_native_perfect_store_audit()
    {
        $region = Region::create(['name' => 'NATIVE ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Native KD', 'region_id' => $region->id]);
        $outlet = Outlet::create([
            'name' => 'Native Perfect Store Outlet',
            'code' => 'NATIVE-001',
            'kd_id' => $kd->id,
            'channel_type' => 'SSM',
        ]);
        $user = User::create([
            'name' => 'Native Merchandiser',
            'email' => 'native-merch@cmih.africa',
            'contact_email' => 'native-merch@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => now('Africa/Accra')->toDateString(),
            'sequence' => 1,
            'status' => 'planned',
        ]);

        $form = MerchandiserGoogleFormAssignment::where('native_template_key', PerfectStoreFormTemplate::KEY)->firstOrFail();
        $template = app(PerfectStoreFormTemplate::class);

        $this->actingAs($user)
            ->get(route('merchandisers.native-forms.show', ['form' => $form, 'outlet_id' => $outlet->id]))
            ->assertOk()
            ->assertSeeText('Perfect Store Audit')
            ->assertSeeText('SKU Merchandising: OSA Quantity on Shelf')
            ->assertSeeText('Planogram: Oral Care');

        $answers = [];
        foreach ($template->questions() as $question) {
            if (($question['type'] ?? null) === 'number') {
                $answers[$question['key']] = 2;
            } elseif (($question['type'] ?? null) === 'planogram_status') {
                $answers[$question['key']] = '1';
            } elseif (! empty($question['options'])) {
                $answers[$question['key']] = $question['options'][0];
            } else {
                $answers[$question['key']] = 'Native answer for ' . $question['label'];
            }
        }
        $regionKey = $template->questions()->firstWhere('label', 'REGION')['key'];
        $outletNameKey = $template->questions()->firstWhere('label', 'OUTLET NAME')['key'];
        $answers[$regionKey] = 'Tampered Region';
        $answers[$outletNameKey] = 'Tampered Outlet';

        $this->post(route('merchandisers.native-forms.submit', $form), [
            'outlet_id' => $outlet->id,
            'answers' => $answers,
        ])->assertRedirect(route('merchandisers.visit', $outlet));

        $submission = MerchandiserNativeFormSubmission::where('form_assignment_id', $form->id)
            ->where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->firstOrFail();

        $this->assertSame(120, count($submission->answers));
        $this->assertSame('NATIVE ACCRA', $submission->answers[$regionKey]);
        $this->assertSame('Native Perfect Store Outlet', $submission->answers[$outletNameKey]);
        $this->assertSame(66.0, (float) $submission->normalized_metrics['quantity_totals']['combined']);
        $this->assertSame(62.0, (float) $submission->normalized_metrics['facings_total']);
        $this->assertSame(34, $submission->normalized_metrics['planogram']['compliant']);
        $this->assertSame(100.0, (float) $submission->normalized_metrics['planogram']['compliance_rate']);
    }
    #[Test]
    public function admin_can_assign_google_form_by_brand_category_and_campaign()
    {
        $admin = User::findOrFail(1);
        $brand = Brand::create([
            'name' => 'Perfect Store Brand',
            'logo_path' => 'brands/perfect-store.png',
        ]);
        $campaign = Campaign::create([
            'name' => 'Perfect Store July Audit',
            'client_name' => 'Unilever',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.google-forms.store'), [
            'title' => 'Perfect Store Audit',
            'google_form_url' => 'https://docs.google.com/forms/d/e/test/viewform',
            'brand_id' => $brand->id,
            'campaign_id' => $campaign->id,
            'category' => 'Perfect Store',
            'channel_type' => 'SSM',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_google_form_assignments', [
            'title' => 'Perfect Store Audit',
            'brand_id' => $brand->id,
            'campaign_id' => $campaign->id,
            'category' => 'Perfect Store',
            'channel_type' => 'SSM',
            'status' => 'active',
        ]);
    }
    #[Test]
    public function brands_admin_can_assign_supervisor_forward_pjp_and_log_compliance_query()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Supervisor KD',
            'region_id' => $region->id,
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
        ]);
        $supervisor = User::factory()->create([
            'name' => 'Field Supervisor',
            'status' => 'active',
            'access_role' => 'merchandiser_supervisor',
        ]);
        $merchandiser = User::factory()->create([
            'name' => 'Assigned Merch',
            'status' => 'active',
            'access_role' => 'merchandiser',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.supervisors.assign'), [
            'supervisor_id' => $supervisor->id,
            'merchandiser_ids' => [$merchandiser->id],
            'kd_ids' => [$kd->id],
        ])->assertRedirect(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $this->assertDatabaseHas('users', [
            'id' => $merchandiser->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('merchandiser_supervisor_assignments', [
            'supervisor_id' => $supervisor->id,
            'merchandiser_id' => $merchandiser->id,
        ]);
        $this->assertDatabaseHas('merchandiser_supervisor_assignments', [
            'supervisor_id' => $supervisor->id,
            'kd_id' => $kd->id,
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.pjps.store'), [
            'title' => 'Admin Attempted PJP',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'kd_ids' => [$kd->id],
            'merchandiser_ids' => [$merchandiser->id],
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
            'radius_meters' => 150,
        ])->assertForbidden();

        $this->actingAs($supervisor)->post(route('merchandisers.admin.pjps.store'), [
            'title' => 'Week 27 North Legon PJP',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'kd_ids' => [$kd->id],
            'merchandiser_ids' => [$merchandiser->id],
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
            'radius_meters' => 150,
        ])->assertRedirect(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $pjp = MerchandiserPjp::firstOrFail();
        $this->assertSame($supervisor->id, (int) $pjp->supervisor_id);
        $this->actingAs($admin)->post(route('merchandisers.admin.pjps.forward', $pjp))
            ->assertRedirect();

        $this->actingAs($supervisor)->post(route('merchandisers.admin.supervisors.pjp-clock-in'), [
            'pjp_id' => $pjp->id,
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_pjp_clockins', [
            'pjp_id' => $pjp->id,
            'user_id' => $supervisor->id,
            'status' => 'verified',
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.compliance-queries.store'), [
            'user_id' => $merchandiser->id,
            'channel' => 'in_app',
            'subject' => 'Visit compliance query',
            'message' => 'Please explain the missed outlet report.',
            'issues' => ['missed_outlet'],
        ])->assertRedirect(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $this->assertDatabaseHas('merchandiser_compliance_queries', [
            'user_id' => $merchandiser->id,
            'sent_by' => $admin->id,
            'channel' => 'in_app',
            'subject' => 'Visit compliance query',
            'status' => 'sent',
        ]);
    }
    #[Test]
    public function brands_admin_can_assign_one_supervisor_to_multiple_kds_and_merchandisers()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'NORTH', 'timezone' => 'Africa/Accra']);
        $firstKd = KeyDistributor::create([
            'name' => 'North KD One',
            'region_id' => $region->id,
            'latitude' => 10.78293440,
            'longitude' => -0.85104960,
        ]);
        $secondKd = KeyDistributor::create([
            'name' => 'North KD Two',
            'region_id' => $region->id,
            'latitude' => 10.79293440,
            'longitude' => -0.86104960,
        ]);
        $supervisor = User::factory()->create([
            'name' => 'Northern Supervisor',
            'status' => 'active',
            'access_role' => User::MERCHANDISER_SUPERVISOR_ROLE,
            'job_level' => 'supervisor',
        ]);
        $firstMerchandiser = User::factory()->create([
            'name' => 'First Covered Merch',
            'status' => 'active',
            'access_role' => User::MERCHANDISER_ROLE,
            'kd_id' => $firstKd->id,
            'region_id' => $region->id,
        ]);
        $secondMerchandiser = User::factory()->create([
            'name' => 'Second Covered Merch',
            'status' => 'active',
            'access_role' => User::MERCHANDISER_ROLE,
            'kd_id' => $secondKd->id,
            'region_id' => $region->id,
        ]);
        $internalStaff = User::factory()->create([
            'name' => 'Internal Staff Not In Coverage',
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.supervisors.assign'), [
            'supervisor_id' => $supervisor->id,
            'merchandiser_ids' => [
                $firstMerchandiser->id,
                $secondMerchandiser->id,
                $supervisor->id,
                $internalStaff->id,
            ],
            'kd_ids' => [$firstKd->id, $secondKd->id],
        ])->assertRedirect(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $this->assertDatabaseHas('users', [
            'id' => $firstMerchandiser->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondMerchandiser->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $supervisor->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $internalStaff->id,
            'supervisor_id' => $supervisor->id,
        ]);

        foreach ([$firstMerchandiser, $secondMerchandiser] as $merchandiser) {
            $this->assertDatabaseHas('merchandiser_supervisor_assignments', [
                'supervisor_id' => $supervisor->id,
                'merchandiser_id' => $merchandiser->id,
            ]);
        }

        foreach ([$firstKd, $secondKd] as $kd) {
            $this->assertDatabaseHas('merchandiser_supervisor_assignments', [
                'supervisor_id' => $supervisor->id,
                'kd_id' => $kd->id,
            ]);
        }

        $this->assertDatabaseMissing('merchandiser_supervisor_assignments', [
            'supervisor_id' => $supervisor->id,
            'merchandiser_id' => $supervisor->id,
        ]);
        $this->assertDatabaseMissing('merchandiser_supervisor_assignments', [
            'supervisor_id' => $supervisor->id,
            'merchandiser_id' => $internalStaff->id,
        ]);
        $this->assertSame(4, MerchandiserSupervisorAssignment::where('supervisor_id', $supervisor->id)->count());
    }
    #[Test]
    public function supervisor_admin_screen_uses_promoted_merchandiser_supervisors_and_lists_all_active_merchandisers()
    {
        $brandsAdmin = User::factory()->create([
            'name' => 'Brands Portal Admin',
            'status' => 'active',
            'access_role' => 'staff',
            'department' => 'brands_marketing',
        ]);
        User::factory()->create([
            'name' => 'Nicolette Chachu',
            'status' => 'active',
            'access_role' => 'manager',
            'department' => 'client_relations',
            'job_level' => 'manager',
        ]);
        $supervisor = User::factory()->create([
            'name' => 'Promoted Field Supervisor',
            'status' => 'active',
            'access_role' => 'merchandiser_supervisor',
            'job_level' => 'supervisor',
        ]);

        foreach (range(1, 10) as $index) {
            User::factory()->create([
                'name' => sprintf('Promo Merch %02d', $index),
                'status' => 'active',
                'access_role' => 'merchandiser',
            ]);
        }

        $response = $this->actingAs($brandsAdmin)->get(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $response->assertOk();
        $response->assertSeeText('Promoted Field Supervisor');
        $response->assertDontSeeText('Nicolette Chachu');
        $response->assertSee('name="supervisor_role_search"', false);
        $response->assertSeeText('Page 1 of 2');
        $response->assertSeeText('Showing 1–8');
        $response->assertSeeText('Promo Merch 07');
        $response->assertDontSee('PJP title / market route', false);

        $searchResponse = $this->actingAs($brandsAdmin)->get(route('merchandisers.admin.dashboard', [
            'tab' => 'supervisors',
            'supervisor_role_search' => 'Promo Merch 10',
        ]));

        $searchResponse->assertOk();
        $searchResponse->assertSeeText('Promo Merch 10');
        $searchResponse->assertSeeText('Showing 1–1');

        $supervisorResponse = $this->actingAs($supervisor)->get(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $supervisorResponse->assertOk();
        $supervisorResponse->assertSee('PJP title / market route', false);
    }
    #[Test]
    public function brands_admin_can_demote_a_supervisor_back_to_regular_merchandiser()
    {
        $admin = User::findOrFail(1);
        $supervisor = User::factory()->create([
            'name' => 'Temporary Supervisor',
            'status' => 'active',
            'access_role' => 'merchandiser_supervisor',
            'job_level' => 'supervisor',
            'position_title' => 'Merchandiser Supervisor',
        ]);
        $assignedMerchandiser = User::factory()->create([
            'status' => 'active',
            'access_role' => 'merchandiser',
            'supervisor_id' => $supervisor->id,
        ]);
        MerchandiserSupervisorAssignment::create([
            'supervisor_id' => $supervisor->id,
            'merchandiser_id' => $assignedMerchandiser->id,
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.supervisors.demote', $supervisor))
            ->assertRedirect(route('merchandisers.admin.dashboard', ['tab' => 'supervisors']));

        $this->assertDatabaseHas('users', [
            'id' => $supervisor->id,
            'access_role' => 'merchandiser',
            'job_level' => 'merchandiser',
            'position_title' => 'Merchandiser',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedMerchandiser->id,
            'supervisor_id' => null,
        ]);
        $this->assertDatabaseMissing('merchandiser_supervisor_assignments', [
            'supervisor_id' => $supervisor->id,
        ]);
    }
    #[Test]
    public function admin_can_change_merchandiser_visit_window_without_hard_coding()
    {
        $admin = User::findOrFail(1);

        $response = $this->actingAs($admin)->post(route('merchandisers.admin.clock-settings.update'), [
            'visit_start' => '08:00',
            'visit_end' => '08:10',
            'late_start' => '08:05',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('site_contents', [
            'key' => 'merchandiser_visit_window_start',
            'value' => '08:00',
        ]);

        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Dynamic KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'Dynamic Outlet',
            'code' => 'DYN-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Dynamic Agent',
            'email' => 'dynamic-agent@cmih.africa',
            'contact_email' => 'dynamic-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 30, 0));
        $this->actingAs($user)->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ])->assertStatus(403);

        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(8, 5, 0));
        $this->actingAs($user)->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ])->assertRedirect(route('merchandisers.dashboard'));

        $this->assertDatabaseHas('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function admin_dashboard_clock_in_filter_limits_clock_in_counts_to_selected_dates()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'GREATER ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Filtered KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $merchandiser = User::create([
            'name' => 'Filtered Merchandiser',
            'email' => 'filtered-merch@cmih.africa',
            'contact_email' => 'filtered-merch@cmih.africa',
            'password' => Hash::make('Password123!'),
            'access_role' => User::MERCHANDISER_ROLE,
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        foreach (['2026-08-05 08:05:00', '2026-08-06 08:05:00', '2026-08-01 08:05:00'] as $clockedAt) {
            MerchandiserPcmClockin::create([
                'user_id' => $merchandiser->id,
                'kd_id' => $kd->id,
                'clocked_in_at' => Carbon::parse($clockedAt),
                'latitude' => 5.60370000,
                'longitude' => -0.18700000,
                'distance_from_kd' => 5,
                'status' => 'verified',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.dashboard', [
            'tab' => 'overview',
            'clock_from' => '2026-08-05',
            'clock_to' => '2026-08-06',
        ]));

        $response->assertOk();
        $response->assertSee('05 Aug 2026 - 06 Aug 2026');
        $response->assertSee('2 PCM', false);
        $response->assertDontSee('3 PCM', false);
    }
    #[Test]
    public function admin_live_tracking_clock_in_filter_uses_selected_dates()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'TRACKING ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Tracking KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $inRange = User::create([
            'name' => 'In Range Merchandiser',
            'email' => 'in-range-merch@cmih.africa',
            'contact_email' => 'in-range-merch@cmih.africa',
            'password' => Hash::make('Password123!'),
            'access_role' => User::MERCHANDISER_ROLE,
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outsideRange = User::create([
            'name' => 'Outside Range Merchandiser',
            'email' => 'outside-range-merch@cmih.africa',
            'contact_email' => 'outside-range-merch@cmih.africa',
            'password' => Hash::make('Password123!'),
            'access_role' => User::MERCHANDISER_ROLE,
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        MerchandiserPcmClockin::create([
            'user_id' => $inRange->id,
            'kd_id' => $kd->id,
            'clocked_in_at' => Carbon::parse('2026-08-05 08:05:00'),
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
            'distance_from_kd' => 5,
            'status' => 'verified',
        ]);
        MerchandiserPcmClockin::create([
            'user_id' => $outsideRange->id,
            'kd_id' => $kd->id,
            'clocked_in_at' => Carbon::parse('2026-08-01 08:05:00'),
            'latitude' => 5.60370000,
            'longitude' => -0.18700000,
            'distance_from_kd' => 5,
            'status' => 'verified',
        ]);

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.dashboard', [
            'tab' => 'tracking',
            'clock_from' => '2026-08-05',
            'clock_to' => '2026-08-05',
        ]));

        $response->assertOk();
        $response->assertSee('Live tracking clock-in filter');
        $response->assertSee('05 Aug 2026');
        $response->assertSee('1 of 2 agents clocked in for this range');
        $response->assertSee('Clock-In Range');
        $response->assertSee('05 Aug 2026, 08:05 AM');
        $response->assertDontSee('01 Aug 2026, 08:05 AM');
    }
    #[Test]
    public function brands_admin_can_see_merchandiser_registered_outlet_details()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Visible KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $merchandiser = User::create([
            'name' => 'Outlet Registrar',
            'email' => 'outlet-registrar@cmih.africa',
            'contact_email' => 'outlet-registrar@personal.com',
            'phone' => '0540000000',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        Outlet::create([
            'name' => 'Registrar Outlet',
            'code' => 'REG-001',
            'kd_id' => $kd->id,
            'registered_by' => $merchandiser->id,
            'channel_type' => 'SSM',
            'address' => 'North Legon',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.tab', ['adminTab' => 'kds']));

        $response->assertOk();
        $response->assertSee('Registrar Outlet');
        $response->assertSee('REG-001');
        $response->assertSee('Outlet Registrar');
        $response->assertSee('North Legon');
    }
    #[Test]
    public function merchandiser_outlet_registration_always_uses_their_assigned_kd()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Assigned KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $otherKd = KeyDistributor::create([
            'name' => 'Other KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);

        $user = User::create([
            'name' => 'Diana Dankwiah',
            'email' => 'diana2@cmih.africa',
            'contact_email' => 'diana2@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);

        $response = $this->actingAs($user)->post(route('merchandisers.outlets.store'), [
            'name' => 'Submitted Outlet',
            'code' => 'SUBMITTED-001',
            'channel_type' => 'SSM',
            'address' => 'Accra',
            'latitude' => 5.6101,
            'longitude' => -0.2002,
            'kd_id' => $otherKd->id,
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('outlets', [
            'code' => 'SUBMITTED-001',
            'kd_id' => $kd->id,
        ]);
        $this->assertDatabaseMissing('outlets', [
            'code' => 'SUBMITTED-001',
            'kd_id' => $otherKd->id,
        ]);
    }
    #[Test]
    public function merchandiser_outlet_registration_assigns_and_locks_gps_coordinates()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'GPS KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $user = User::create([
            'name' => 'GPS Registrar',
            'email' => 'gps-registrar@cmih.africa',
            'contact_email' => 'gps-registrar@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $response = $this->actingAs($user)->post(route('merchandisers.outlets.store'), [
            'name' => 'Captured Outlet',
            'code' => 'CAP-001',
            'channel_type' => 'GT',
            'address' => 'At the outlet',
            'latitude' => 5.6101,
            'longitude' => -0.2002,
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));

        $outlet = Outlet::where('code', 'CAP-001')->firstOrFail();
        $this->assertSame($user->id, $outlet->registered_by);
        $this->assertSame('staff_gps', $outlet->coordinates_source);
        $this->assertNotNull($outlet->coordinates_locked_at);
        $this->assertDatabaseHas('merchandiser_outlet_user', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]);
    }
    #[Test]
    public function route_generation_uses_registered_or_explicitly_assigned_outlets_only()
    {
        $region = Region::create(['name' => 'ROUTE OWNERSHIP', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Ownership KD', 'region_id' => $region->id]);
        $user = User::create([
            'name' => 'Route Owner',
            'email' => 'route-owner@cmih.africa',
            'contact_email' => 'route-owner@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'merchandiser_working_days' => [1],
            'merchandiser_outlet_frequency' => 'weekly',
        ]);
        $otherUser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        $registeredOutlet = Outlet::create([
            'name' => 'Registered Route Outlet',
            'code' => 'OWN-001',
            'kd_id' => $kd->id,
            'registered_by' => $user->id,
            'channel_type' => 'GT',
        ]);
        $assignedOutlet = Outlet::create([
            'name' => 'Admin Assigned Outlet',
            'code' => 'OWN-002',
            'kd_id' => $kd->id,
            'registered_by' => $otherUser->id,
            'channel_type' => 'GT',
        ]);

        Carbon::setTestNow();
        $unassignedOutlet = Outlet::create([
            'name' => 'Unassigned Same KD Outlet',
            'code' => 'OWN-003',
            'kd_id' => $kd->id,
            'registered_by' => $otherUser->id,
            'channel_type' => 'GT',
        ]);

        $assignedOutlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        app(MerchandiserRoutePlanner::class)->ensureWeek($user, Carbon::create(2026, 7, 20, 8, 0, 0, 'Africa/Accra'));

        $assignedOutletIds = MerchandiserOutletAssignment::where('user_id', $user->id)
            ->pluck('outlet_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($registeredOutlet->id, $assignedOutletIds);
        $this->assertContains($assignedOutlet->id, $assignedOutletIds);
        $this->assertNotContains($unassignedOutlet->id, $assignedOutletIds);
    }
    #[Test]
    public function admin_can_assign_registered_outlets_by_creation_date_and_remove_assignment()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ADMIN ROUTE', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Admin Route KD', 'region_id' => $region->id]);
        $merchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $secondMerchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 10, 0, 0, 'Africa/Accra'));
        $mondayOutlet = Outlet::create([
            'name' => 'Monday Outlet',
            'code' => 'DAY-001',
            'kd_id' => $kd->id,
            'registered_by' => $merchandiser->id,
            'channel_type' => 'GT',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 7, 21, 10, 0, 0, 'Africa/Accra'));
        $tuesdayOutlet = Outlet::create([
            'name' => 'Tuesday Outlet',
            'code' => 'DAY-002',
            'kd_id' => $kd->id,
            'registered_by' => $secondMerchandiser->id,
            'channel_type' => 'GT',
        ]);
        Carbon::setTestNow();

        $this->actingAs($admin)->post(route('merchandisers.admin.outlet-assignments.registered'), [
            'outlet_created_from' => '2026-07-20',
            'outlet_created_to' => '2026-07-20',
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_outlet_user', [
            'user_id' => $merchandiser->id,
            'outlet_id' => $mondayOutlet->id,
        ]);
        $this->assertDatabaseMissing('merchandiser_outlet_user', [
            'user_id' => $secondMerchandiser->id,
            'outlet_id' => $tuesdayOutlet->id,
        ]);

        MerchandiserOutletAssignment::create([
            'user_id' => $merchandiser->id,
            'outlet_id' => $mondayOutlet->id,
            'assigned_date' => Carbon::today()->addDay()->toDateString(),
            'sequence' => 1,
            'status' => 'planned',
            'source' => 'auto',
        ]);

        $this->actingAs($admin)->delete(route('merchandisers.admin.outlet-assignments.destroy', [
            'outlet' => $mondayOutlet,
            'user' => $merchandiser,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('merchandiser_outlet_user', [
            'user_id' => $merchandiser->id,
            'outlet_id' => $mondayOutlet->id,
        ]);
        $this->assertDatabaseMissing('merchandiser_outlet_assignments', [
            'user_id' => $merchandiser->id,
            'outlet_id' => $mondayOutlet->id,
            'status' => 'planned',
        ]);
    }
    #[Test]
    public function admin_can_assign_single_or_multiple_outlets_from_route_planning()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ROUTE MANUAL ASSIGN', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Manual Assign KD', 'region_id' => $region->id]);
        $merchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outletOne = Outlet::create([
            'name' => 'Manual One',
            'code' => 'MAN-001',
            'kd_id' => $kd->id,
            'registered_by' => $merchandiser->id,
            'channel_type' => 'GT',
        ]);
        $outletTwo = Outlet::create([
            'name' => 'Manual Two',
            'code' => 'MAN-002',
            'kd_id' => $kd->id,
            'registered_by' => $merchandiser->id,
            'channel_type' => 'GT',
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.outlet-assignments.store'), [
            'user_id' => $merchandiser->id,
            'outlet_id' => $outletOne->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('merchandisers.admin.outlet-assignments.store'), [
            'user_id' => $merchandiser->id,
            'outlet_ids' => [$outletTwo->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('merchandiser_outlet_user', [
            'user_id' => $merchandiser->id,
            'outlet_id' => $outletOne->id,
        ]);
        $this->assertDatabaseHas('merchandiser_outlet_user', [
            'user_id' => $merchandiser->id,
            'outlet_id' => $outletTwo->id,
        ]);
    }
    #[Test]
    public function route_planning_assignment_requires_at_least_one_outlet()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ROUTE EMPTY ASSIGN', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Empty Assign KD', 'region_id' => $region->id]);
        $merchandiser = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $this->actingAs($admin)->post(route('merchandisers.admin.outlet-assignments.store'), [
            'user_id' => $merchandiser->id,
        ])->assertSessionHasErrors('outlet_ids');
    }
    #[Test]
    public function merchandiser_can_capture_unlocked_existing_outlet_coordinates_once()
    {
        $region = Region::create(['name' => 'GPS RECAPTURE', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Recapture KD', 'region_id' => $region->id]);
        $user = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $outlet = Outlet::create([
            'name' => 'Legacy Outlet',
            'code' => 'LEG-001',
            'kd_id' => $kd->id,
            'registered_by' => $user->id,
            'channel_type' => 'GT',
        ]);

        $this->actingAs($user)->patch(route('merchandisers.outlets.coordinates.update', $outlet), [
            'latitude' => 5.6222,
            'longitude' => -0.1888,
        ])->assertRedirect(route('merchandisers.dashboard'));

        $outlet->refresh();
        $this->assertSame('staff_gps', $outlet->coordinates_source);
        $this->assertNotNull($outlet->coordinates_locked_at);
        $this->assertEquals(5.6222, (float) $outlet->latitude);
        $this->assertEquals(-0.1888, (float) $outlet->longitude);

        $this->actingAs($user)->from(route('merchandisers.dashboard'))->patch(route('merchandisers.outlets.coordinates.update', $outlet), [
            'latitude' => 5.7000,
            'longitude' => -0.1000,
        ])->assertRedirect(route('merchandisers.dashboard'))
            ->assertSessionHasErrors('outlet_id');

        $this->assertEquals(5.6222, (float) $outlet->fresh()->latitude);
    }
    #[Test]
    public function merchandiser_visit_page_exposes_manual_and_ai_sku_entry_modes()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'AI Pilot KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'AI Pilot Outlet',
            'code' => 'AI-PILOT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'AI Pilot Agent',
            'email' => 'ai-pilot-agent@cmih.africa',
            'contact_email' => 'ai-pilot-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        Sku::create(['name' => 'Guinness Smooth 330ml']);

        $response = $this->actingAs($user)->get(route('merchandisers.visit', $outlet));

        $response->assertOk();
        $response->assertSee('Manual Entry');
        $response->assertSee('AI Shelf Detection (Pilot)');
        $response->assertSee('name="sku_entry_mode"', false);
        $response->assertSee('capture="environment"', false);
        $response->assertSee('Pilot mode keeps manual fallback active');
    }
    #[Test]
    public function manual_sku_entry_stores_visit_without_ai_photo()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Manual KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'Manual Outlet',
            'code' => 'MANUAL-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Manual Agent',
            'email' => 'manual-agent@cmih.africa',
            'contact_email' => 'manual-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create(['name' => 'Guinness Smooth 330ml']);

        $this->recordOutletClockIn($user, $outlet);

        $response = $this->actingAs($user)->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 1,
            'sku_entry_mode' => 'manual',
            'skus' => [
                $sku->id => [
                    'osa_quantity' => 12,
                    'npd_present' => 1,
                    'facing' => 4,
                    'share_of_shelf' => 35,
                    'planogram_compliant' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));

        $visit = MerchandiserVisit::firstOrFail();
        $this->assertSame('manual', $visit->sku_entry_mode);
        $this->assertNull($visit->ai_detection_status);
        $this->assertNull($visit->ai_shelf_photo_path);
        $this->assertDatabaseHas('merchandiser_visit_skus', [
            'visit_id' => $visit->id,
            'sku_id' => $sku->id,
            'osa_quantity' => 12,
            'facing' => 4,
        ]);
    }

    #[Test]
    public function visit_submission_rejects_sos_facings_that_exceed_total_category_facings()
    {
        $region = Region::create(['name' => 'ACCRA SOS', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'SOS Guard KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'SOS Guard Outlet',
            'code' => 'SOS-GUARD-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'SOS Guard Agent',
            'email' => 'sos-guard-agent@cmih.africa',
            'contact_email' => 'sos-guard-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create([
            'name' => 'Rexona Guard SKU',
            'category' => 'Deodorants',
            'facing_target' => 4,
            'sos_target' => 60,
        ]);

        $this->recordOutletClockIn($user, $outlet);

        $response = $this->actingAs($user)
            ->from(route('merchandisers.visit', $outlet))
            ->post(route('merchandisers.visit.store', $outlet), [
                'branded_shelf_available' => 1,
                'hangers_available' => 1,
                'sku_entry_mode' => 'manual',
                'skus' => [
                    $sku->id => [
                        'osa_quantity' => 12,
                        'npd_present' => 1,
                        'facing' => 4,
                        'share_of_shelf' => 35,
                        'category_unilever_facings' => 61,
                        'category_total_facings' => 60,
                        'planogram_compliant' => 1,
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('merchandisers.visit', $outlet))
            ->assertSessionHasErrors("skus.{$sku->id}.category_unilever_facings");

        $this->assertDatabaseCount('merchandiser_visits', 0);
        $this->assertDatabaseCount('merchandiser_visit_skus', 0);
    }

    #[Test]
    public function visit_submission_stores_share_of_shelf_once_per_category()
    {
        $region = Region::create(['name' => 'ACCRA CATEGORY SOS', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Category SOS KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'Category SOS Outlet',
            'code' => 'CATEGORY-SOS-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Category SOS Agent',
            'email' => 'category-sos-agent@cmih.africa',
            'contact_email' => 'category-sos-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $firstSku = Sku::create([
            'name' => 'Rexona Roll On',
            'category' => 'Deodorants',
            'facing_target' => 4,
        ]);
        $secondSku = Sku::create([
            'name' => 'Rexona Spray',
            'category' => 'Deodorants',
            'facing_target' => 3,
        ]);

        $this->recordOutletClockIn($user, $outlet);

        $response = $this->actingAs($user)->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 1,
            'sku_entry_mode' => 'manual',
            'category_sos' => [
                'deodorants' => [
                    'category' => 'Deodorants',
                    'category_unilever_facings' => 30,
                    'category_total_facings' => 50,
                ],
            ],
            'skus' => [
                $firstSku->id => [
                    'osa_quantity' => 12,
                    'npd_present' => 1,
                    'facing' => 4,
                    'share_of_shelf' => 0,
                    'planogram_compliant' => 1,
                ],
                $secondSku->id => [
                    'osa_quantity' => 8,
                    'npd_present' => 1,
                    'facing' => 3,
                    'share_of_shelf' => 0,
                    'planogram_compliant' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));

        $visit = MerchandiserVisit::firstOrFail();
        $rows = MerchandiserVisitSku::where('visit_id', $visit->id)->get();
        $categoryRows = $rows->filter(fn (MerchandiserVisitSku $row) => $row->category_total_facings !== null);

        $this->assertCount(1, $categoryRows);
        $this->assertSame(30, (int) $categoryRows->first()->category_unilever_facings);
        $this->assertSame(50, (int) $categoryRows->first()->category_total_facings);
        $this->assertSame(60.0, (float) $rows->first()->share_of_shelf);
        $this->assertSame(60.0, app(PerfectStoreKpiService::class)
            ->categoryKpis(now()->startOfDay(), now()->endOfDay())
            ->firstWhere('category', 'Deodorants')
            ->sos_pct);
    }

    #[Test]
    public function ai_sku_entry_mode_captures_shelf_photo_and_keeps_manual_metrics()
    {
        Storage::fake('public');

        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'AI Shelf KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'AI Shelf Outlet',
            'code' => 'AI-SHELF-001',
            'kd_id' => $kd->id,
            'channel_type' => 'SSM',
            'address' => 'North Legon',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'AI Shelf Agent',
            'email' => 'ai-shelf-agent@cmih.africa',
            'contact_email' => 'ai-shelf-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create(['name' => 'Guinness Foreign Extra Stout']);

        $this->recordOutletClockIn($user, $outlet);

        $response = $this->actingAs($user)->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 0,
            'sku_entry_mode' => 'ai',
            'ai_shelf_photo' => UploadedFile::fake()->create('shelf.jpg', 256, 'image/jpeg'),
            'ai_detection_notes' => 'Photo taken from left shelf angle; merchandiser confirmed the counts manually.',
            'skus' => [
                $sku->id => [
                    'osa_quantity' => 8,
                    'npd_present' => 0,
                    'facing' => 3,
                    'share_of_shelf' => 22.5,
                    'planogram_compliant' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));

        $visit = MerchandiserVisit::firstOrFail();
        $this->assertSame('ai', $visit->sku_entry_mode);
        $this->assertSame('pilot_photo_captured', $visit->ai_detection_status);
        $this->assertNotNull($visit->ai_shelf_photo_path);
        Storage::disk('public')->assertExists($visit->ai_shelf_photo_path);
        $this->assertSame('pilot_photo_upload', $visit->ai_detection_payload['source']);
        $this->assertFalse($visit->ai_detection_payload['auto_detection_completed']);
        $this->assertTrue($visit->ai_detection_payload['manual_fallback_available']);
        $this->assertDatabaseHas('merchandiser_visit_skus', [
            'visit_id' => $visit->id,
            'sku_id' => $sku->id,
            'osa_quantity' => 8,
            'facing' => 3,
        ]);
    }
    #[Test]
    public function ai_sku_entry_requires_a_shelf_photo()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'AI Required KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'AI Required Outlet',
            'code' => 'AI-REQUIRED-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'AI Required Agent',
            'email' => 'ai-required-agent@cmih.africa',
            'contact_email' => 'ai-required-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create(['name' => 'Malta Guinness']);

        $response = $this->actingAs($user)->from(route('merchandisers.visit', $outlet))->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 1,
            'sku_entry_mode' => 'ai',
            'skus' => [
                $sku->id => [
                    'osa_quantity' => 5,
                    'npd_present' => 0,
                    'facing' => 2,
                    'share_of_shelf' => 15,
                    'planogram_compliant' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('merchandisers.visit', $outlet));
        $response->assertSessionHasErrors('ai_shelf_photo');
        $this->assertDatabaseCount('merchandiser_visits', 0);
    }
    #[Test]
    public function ai_detection_endpoint_falls_back_to_manual_when_no_provider_is_configured()
    {
        config([
            'services.openai.api_key' => null,
            'services.gemini.api_key' => null,
            'services.ai.provider' => 'auto',
        ]);

        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'AI Missing Key KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'AI Missing Key Outlet',
            'code' => 'AI-MISSING-KEY-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'AI Missing Key Agent',
            'email' => 'ai-missing-key-agent@cmih.africa',
            'contact_email' => 'ai-missing-key-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        Sku::create(['name' => 'Guinness Smooth 330ml']);

        $response = $this->actingAs($user)->postJson(route('merchandisers.visit.ai-detect', $outlet), [
            'ai_shelf_photo' => UploadedFile::fake()->create('shelf.jpg', 256, 'image/jpeg'),
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'manual_fallback',
            'provider' => 'manual',
            'review_required' => true,
        ]);
    }
    #[Test]
    public function ai_detection_prefills_and_visit_submission_stores_predictions_with_corrections()
    {
        Storage::fake('public');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output_text' => json_encode([
                    'detections' => [
                        [
                            'sku_id' => 1,
                            'sku_name' => 'Guinness Smooth 330ml',
                            'quantity' => 11,
                            'facing' => 5,
                            'share_of_shelf' => 42.5,
                            'planogram_compliant' => true,
                            'confidence' => 0.88,
                            'review_required' => false,
                            'boxes' => [
                                ['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.4, 'label' => 'front row'],
                            ],
                            'notes' => 'Clear visible Guinness Smooth row.',
                        ],
                    ],
                    'scene_notes' => 'Good lighting.',
                ]),
            ], 200),
        ]);
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.vision_model' => 'gpt-5.5',
            'services.ai.review_threshold' => 0.75,
            'services.ai.provider' => 'auto',
            'performance.background_jobs.connection' => 'sync',
        ]);

        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'AI Real KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'AI Real Outlet',
            'code' => 'AI-REAL-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'AI Real Agent',
            'email' => 'ai-real-agent@cmih.africa',
            'contact_email' => 'ai-real-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create([
            'id' => 1,
            'name' => 'Guinness Smooth 330ml',
            'reference_image_path' => 'sku-reference-images/guinness-smooth.jpg',
            'aliases' => ['Smooth', 'Guinness Smooth'],
        ]);

        $this->recordOutletClockIn($user, $outlet);

        $detectResponse = $this->actingAs($user)->postJson(route('merchandisers.visit.ai-detect', $outlet), [
            'ai_shelf_photo' => UploadedFile::fake()->create('shelf.jpg', 256, 'image/jpeg'),
        ]);

        $detectResponse->assertAccepted();
        $detectResponse->assertJsonPath('job_status', 'queued');

        $pollResponse = $this->actingAs($user)->getJson($detectResponse->json('poll_url'));
        $pollResponse->assertOk();
        $pollResponse->assertJsonPath('status', 'completed');
        $pollResponse->assertJsonPath('provider', 'openai');
        $pollResponse->assertJsonPath('detections.0.quantity', 11);

        $predictions = $pollResponse->json();

        $submitResponse = $this->actingAs($user)->post(route('merchandisers.visit.store', $outlet), [
            'branded_shelf_available' => 1,
            'hangers_available' => 1,
            'sku_entry_mode' => 'ai',
            'ai_shelf_photo' => UploadedFile::fake()->create('confirmed-shelf.jpg', 256, 'image/jpeg'),
            'ai_predictions_json' => json_encode($predictions),
            'ai_detection_notes' => 'Corrected quantity from 11 to 12 after checking the rear row.',
            'skus' => [
                $sku->id => [
                    'osa_quantity' => 12,
                    'npd_present' => 1,
                    'facing' => 5,
                    'share_of_shelf' => 42.5,
                    'planogram_compliant' => 1,
                ],
            ],
        ]);

        $submitResponse->assertRedirect(route('merchandisers.dashboard'));

        $visit = MerchandiserVisit::firstOrFail();
        $this->assertSame('completed', $visit->ai_detection_status);
        $this->assertTrue($visit->ai_detection_payload['auto_detection_completed']);
        $this->assertSame('openai_shelf_analysis', $visit->ai_detection_payload['source']);
        $this->assertSame('openai', $visit->ai_detection_payload['provider']);
        $this->assertFalse($visit->ai_detection_review_required);
        $this->assertNotNull($visit->ai_detection_completed_at);

        $this->assertDatabaseHas('merchandiser_visit_skus', [
            'visit_id' => $visit->id,
            'sku_id' => $sku->id,
            'osa_quantity' => 12,
            'ai_predicted_quantity' => 11,
            'ai_predicted_facing' => 5,
        ]);
    }
    #[Test]
    public function ai_detection_uses_gemini_when_openai_fails()
    {
        Storage::fake('public');
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'OpenAI unavailable']], 500),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'detections' => [
                                            [
                                                'sku_id' => 1,
                                                'sku_name' => 'Malta Guinness Bottle 330ml',
                                                'quantity' => 6,
                                                'facing' => 3,
                                                'share_of_shelf' => 25,
                                                'planogram_compliant' => true,
                                                'confidence' => 0.81,
                                                'review_required' => false,
                                                'boxes' => [],
                                                'notes' => 'Gemini fallback identified visible bottles.',
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.vision_model' => 'gpt-4o-mini',
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com',
            'services.gemini.model' => 'gemini-flash-latest',
            'services.ai.provider' => 'auto',
            'services.ai.review_threshold' => 0.45,
            'performance.background_jobs.connection' => 'sync',
        ]);

        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Gemini Fallback KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $outlet = Outlet::create([
            'name' => 'Gemini Fallback Outlet',
            'code' => 'GEMINI-FALLBACK-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
        ]);
        $user = User::create([
            'name' => 'Gemini Fallback Agent',
            'email' => 'gemini-fallback-agent@cmih.africa',
            'contact_email' => 'gemini-fallback-agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        Sku::create([
            'id' => 1,
            'name' => 'Malta Guinness Bottle 330ml',
        ]);

        $response = $this->actingAs($user)->postJson(route('merchandisers.visit.ai-detect', $outlet), [
            'ai_shelf_photo' => UploadedFile::fake()->create('shelf.jpg', 256, 'image/jpeg'),
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('job_status', 'queued');

        $pollResponse = $this->actingAs($user)->getJson($response->json('poll_url'));
        $pollResponse->assertOk();
        $pollResponse->assertJsonPath('status', 'completed');
        $pollResponse->assertJsonPath('provider', 'gemini');
        $pollResponse->assertJsonPath('detections.0.quantity', 6);
        $pollResponse->assertJsonPath('attempts.0.provider', 'openai');
        $pollResponse->assertJsonPath('attempts.0.status', 'provider_error');
        $pollResponse->assertJsonPath('attempts.1.provider', 'gemini');
        $pollResponse->assertJsonPath('attempts.1.status', 'completed');
    }
    #[Test]
    public function brands_admin_can_manage_sku_reference_images_for_ai_catalog()
    {
        Storage::fake('public');
        $admin = User::findOrFail(1);
        $brand = Brand::create(['name' => 'Guinness', 'logo_path' => 'brands/guinness.png']);
        $updatedBrand = Brand::create(['name' => 'Unilever', 'logo_path' => 'brands/unilever.png']);

        $response = $this->actingAs($admin)->post(route('merchandisers.admin.skus.store'), [
            'name' => 'AI Catalog SKU',
            'brand_id' => $brand->id,
            'category' => 'Beverage',
            'track_osa' => '1',
            'osa_drop_size' => '6',
            'track_npd' => '1',
            'npd_drop_size' => '2',
            'track_mhs' => '1',
            'mhs_drop_size' => '4',
            'facing_target' => '5',
            'track_planogram' => '1',
            'sos_target' => '62.5',
            'reference_image' => UploadedFile::fake()->create('product.jpg', 128, 'image/jpeg'),
            'aliases' => 'Catalog Alias, shelf short name',
            'ai_reference_notes' => 'Red label, bottle format.',
        ]);

        $response->assertRedirect();
        $sku = Sku::where('name', 'AI Catalog SKU')->firstOrFail();
        $this->assertNotNull($sku->reference_image_path);
        Storage::disk('public')->assertExists($sku->reference_image_path);
        $this->assertSame(['Catalog Alias', 'shelf short name'], $sku->aliases);
        $this->assertTrue($sku->brand->is($brand));
        $this->assertSame('Beverage', $sku->category);
        $this->assertTrue($sku->track_osa);
        $this->assertSame(6, $sku->osa_drop_size);
        $this->assertTrue($sku->track_npd);
        $this->assertSame(2, $sku->npd_drop_size);
        $this->assertTrue($sku->track_mhs);
        $this->assertSame(4, $sku->mhs_drop_size);
        $this->assertSame(5, $sku->facing_target);
        $this->assertTrue($sku->track_planogram);
        $this->assertSame('62.50', $sku->sos_target);

        $updateResponse = $this->actingAs($admin)->put(route('merchandisers.admin.skus.update', $sku), [
            'name' => 'AI Catalog SKU Updated',
            'brand_id' => $updatedBrand->id,
            'category' => 'Home Care',
            'track_osa' => '1',
            'osa_drop_size' => '7',
            'mhs_drop_size' => '5',
            'facing_target' => '9',
            'sos_target' => '55',
            'aliases' => 'Updated Alias',
            'ai_reference_notes' => 'Updated notes.',
        ]);

        $updateResponse->assertRedirect();
        $sku->refresh();
        $this->assertSame('AI Catalog SKU Updated', $sku->name);
        $this->assertSame(['Updated Alias'], $sku->aliases);
        $this->assertSame('Updated notes.', $sku->ai_reference_notes);
        $this->assertTrue($sku->brand->is($updatedBrand));
        $this->assertSame('Home Care', $sku->category);
        $this->assertTrue($sku->track_osa);
        $this->assertSame(7, $sku->osa_drop_size);
        $this->assertFalse($sku->track_npd);
        $this->assertFalse($sku->track_mhs);
        $this->assertSame(5, $sku->mhs_drop_size);
        $this->assertSame(9, $sku->facing_target);
        $this->assertFalse($sku->track_planogram);
        $this->assertSame('55.00', $sku->sos_target);

        $targetResponse = $this->actingAs($admin)->post(route('merchandisers.admin.category-targets.store'), [
            'category' => 'Home Care',
            'sos_target' => '68.5',
        ]);

        $targetResponse->assertRedirect();
        $target = \App\Models\PerfectStoreCategoryTarget::where('category', 'Home Care')->firstOrFail();
        $this->assertSame('68.50', $target->sos_target);
        $this->assertSame($admin->id, $target->created_by);
        $this->assertSame($admin->id, $target->updated_by);
    }

    #[Test]
    public function category_kpi_tab_caps_facing_percentage_scores()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'FACING REGION', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Facing KD',
            'region_id' => $region->id,
        ]);
        $outlet = Outlet::create([
            'name' => 'Facing Outlet',
            'code' => 'FACING-OUTLET-001',
            'kd_id' => $kd->id,
        ]);
        $merchandiser = User::create([
            'name' => 'Facing Agent',
            'email' => 'facing-agent@cmih.africa',
            'contact_email' => 'facing-agent@cmih.africa',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => User::MERCHANDISER_ROLE,
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $sku = Sku::create([
            'name' => 'Runaway Facing SKU',
            'category' => 'Facing Audit',
            'track_osa' => false,
            'facing_target' => 2,
        ]);
        $visit = MerchandiserVisit::create([
            'user_id' => $merchandiser->id,
            'outlet_id' => $outlet->id,
        ]);
        MerchandiserVisitSku::create([
            'visit_id' => $visit->id,
            'sku_id' => $sku->id,
            'osa_quantity' => 0,
            'npd_present' => false,
            'facing' => 13,
            'facing_target_snapshot' => 2,
            'share_of_shelf' => 50,
            'planogram_compliant' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.dashboard', [
            'tab' => 'category-kpi',
        ]));

        $response->assertOk();
        $response->assertSeeText('Facing Audit');
        $response->assertSeeText('100%');
        $response->assertDontSeeText('650%');
    }

    #[Test]
    public function brands_admin_can_add_new_brand_and_category_from_sku_catalog()
    {
        $admin = User::findOrFail(1);

        $response = $this->actingAs($admin)->post(route('merchandisers.admin.skus.store'), [
            'name' => 'New Partner SKU',
            'new_brand_name' => 'New Partner Brand',
            'new_category' => 'Energy Drink',
            'aliases' => 'Partner Short Name',
        ]);

        $response->assertRedirect();
        $brand = Brand::where('name', 'New Partner Brand')->firstOrFail();
        $sku = Sku::where('name', 'New Partner SKU')->firstOrFail();

        $this->assertTrue($sku->brand->is($brand));
        $this->assertSame('Energy Drink', $sku->category);
        $this->assertSame(['Partner Short Name'], $sku->aliases);

        $updateResponse = $this->actingAs($admin)->put(route('merchandisers.admin.skus.update', $sku), [
            'name' => 'New Partner SKU Updated',
            'new_brand_name' => 'Second Partner Brand',
            'new_category' => 'Ready To Drink',
        ]);

        $updateResponse->assertRedirect();
        $sku->refresh();

        $this->assertSame('Second Partner Brand', $sku->brand->name);
        $this->assertSame('Ready To Drink', $sku->category);
    }
    #[Test]
    public function live_tracking_uses_latest_gps_ping_and_admin_rows_can_zoom_to_agent()
    {
        $admin = User::findOrFail(1);
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Tracking KD',
            'region_id' => $region->id,
            'address' => 'Accra',
        ]);
        $user = User::create([
            'name' => 'Field Tracker',
            'email' => 'field-tracker@cmih.africa',
            'contact_email' => 'field-tracker@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);

        $this->actingAs($user)->postJson(route('merchandisers.location-ping'), [
            'latitude' => 5.6817,
            'longitude' => -0.1932,
        ])->assertOk()->assertJson(['status' => 'success']);

        $location = MerchandiserLocation::where('user_id', $user->id)->first();
        $this->assertNotNull($location);
        $this->assertEqualsWithDelta(5.6817, (float) $location->latitude, 0.000001);
        $this->assertEqualsWithDelta(-0.1932, (float) $location->longitude, 0.000001);

        $response = $this->actingAs($admin)->get(route('merchandisers.admin.tab', ['adminTab' => 'tracking']));

        $response->assertOk();
        $response->assertSee('Field Tracker');
        $response->assertSee('Zoom to Field Tracker on the map');
        $response->assertSee('focusMerchandiserOnMap');
        $response->assertSee('setZoom(Math.max(googleMap.getZoom() || 0, 19))', false);
    }
    #[Test]
    public function geofenced_radius_distance_enforced()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Test KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);
        // Outlet coordinates at [5.6037, -0.1870]
        $outlet = Outlet::create([
            'name' => 'Test Outlet',
            'code' => 'OUT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 30, 0));

        // Attempt clock-in from coordinates far away (e.g. 5.7000, -0.1000 is ~14 km away)
        $response = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.7000,
            'longitude' => -0.1000
        ]);

        $response->assertSessionHasErrors('outlet_id');
        $this->assertDatabaseMissing('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function key_distributor_deletion_triggers_wizard_when_dependents_exist()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd1 = KeyDistributor::create([
            'name' => 'Ama Jessica Dist',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6,
            'longitude' => -0.18
        ]);
        $kd2 = KeyDistributor::create([
            'name' => 'Bisvel Ltd',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6,
            'longitude' => -0.18
        ]);

        // Outlet linked to KD1
        $outlet = Outlet::create([
            'name' => 'Osu GT Store',
            'code' => 'OUT-100',
            'kd_id' => $kd1->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6,
            'longitude' => -0.18
        ]);

        // Merchandiser linked to KD1
        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd1->id,
            'region_id' => $region->id
        ]);

        // Authenticated admin acting
        $admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@cmih.africa',
            'contact_email' => 'admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'super_admin',
            'status' => 'active'
        ]);
        $this->actingAs($admin);

        // Delete without reassign target -> fails with session show_reassign_wizard_for flag
        $response = $this->delete(route('merchandisers.admin.kds.destroy', $kd1));

        $response->assertSessionHas('show_reassign_wizard_for', $kd1->id);
        $this->assertDatabaseHas('key_distributors', ['id' => $kd1->id]);

        // Delete with target reassign to KD2 -> reassigns outlets and users and deletes KD1
        $response2 = $this->delete(route('merchandisers.admin.kds.destroy', $kd1), [
            'reassign_kd_id' => $kd2->id
        ]);

        $response2->assertRedirect(route('merchandisers.admin.tab', ['adminTab' => 'kds']));
        $this->assertDatabaseMissing('key_distributors', ['id' => $kd1->id]);
        
        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'kd_id' => $kd2->id
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kd_id' => $kd2->id
        ]);
    }
    #[Test]
    public function admin_can_edit_kd_details_and_assigned_merchandisers_together()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $newRegion = Region::create(['name' => 'KUMASI', 'timezone' => 'Africa/Accra']);

        $kd = KeyDistributor::create([
            'name' => 'Old KD',
            'region_id' => $region->id,
            'address' => 'Old Address',
            'latitude' => 5.6,
            'longitude' => -0.18
        ]);
        $otherKd = KeyDistributor::create([
            'name' => 'Other KD',
            'region_id' => $region->id,
            'address' => 'Other Address',
        ]);

        $currentMerchandiser = User::create([
            'name' => 'Current Merch',
            'email' => 'current-merch@cmih.africa',
            'contact_email' => 'current-merch@personal.com',
            'phone' => '11111111',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
        ]);
        $newMerchandiser = User::create([
            'name' => 'New Merch',
            'email' => 'new-merch@cmih.africa',
            'contact_email' => 'new-merch@personal.com',
            'phone' => '22222222',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'pending',
            'kd_id' => null,
            'region_id' => null,
        ]);
        $movedMerchandiser = User::create([
            'name' => 'Moved Merch',
            'email' => 'moved-merch@cmih.africa',
            'contact_email' => 'moved-merch@personal.com',
            'phone' => '33333333',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $otherKd->id,
            'region_id' => $region->id,
        ]);

        $admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin-kd-edit@cmih.africa',
            'contact_email' => 'admin-kd-edit@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'super_admin',
            'status' => 'active'
        ]);

        $response = $this->actingAs($admin)->put(route('merchandisers.admin.kds.update', $kd), [
            'name' => 'Updated KD',
            'region_id' => $newRegion->id,
            'address' => 'Updated Address',
            'latitude' => 10.7829344,
            'longitude' => -0.8510496,
            'sync_assigned_merchandisers' => 1,
            'assigned_merchandiser_ids' => [$newMerchandiser->id, $movedMerchandiser->id],
        ]);

        $response->assertSessionHas('success', 'Key Distributor updated.');
        $this->assertDatabaseHas('key_distributors', [
            'id' => $kd->id,
            'name' => 'Updated KD',
            'region_id' => $newRegion->id,
            'address' => 'Updated Address',
            'latitude' => 10.7829344,
            'longitude' => -0.8510496,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $currentMerchandiser->id,
            'kd_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $newMerchandiser->id,
            'kd_id' => $kd->id,
            'region_id' => $newRegion->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $movedMerchandiser->id,
            'kd_id' => $kd->id,
            'region_id' => $newRegion->id,
            'status' => 'active',
        ]);
    }
    #[Test]
    public function admin_cannot_save_a_key_distributor_without_pcm_coordinates()
    {
        $region = Region::create(['name' => 'NORTH', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Bisvel LTD',
            'region_id' => $region->id,
            'address' => 'UB-0000-5276',
        ]);

        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'brands-admin-kd-gps@cmih.africa',
            'contact_email' => 'brands-admin-kd-gps@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('merchandisers.admin.dashboard'))
            ->put(route('merchandisers.admin.kds.update', $kd), [
                'name' => 'Bisvel LTD',
                'region_id' => $region->id,
                'address' => 'UB-0000-5276',
                'latitude' => '',
                'longitude' => '',
                'sync_assigned_merchandisers' => 1,
            ]);

        $response->assertRedirect(route('merchandisers.admin.dashboard'));
        $response->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertDatabaseHas('key_distributors', [
            'id' => $kd->id,
            'latitude' => null,
            'longitude' => null,
        ]);
    }
    #[Test]
    public function kd_coordinate_backfill_only_writes_explicit_kd_row_coordinates()
    {
        $region = Region::create(['name' => 'BACKFILL', 'timezone' => 'Africa/Accra']);
        $decimalKd = KeyDistributor::create([
            'name' => 'Lucky Bazaar',
            'region_id' => $region->id,
            'address' => '4.904211, -1.758493',
        ]);
        $dmsKd = KeyDistributor::create([
            'name' => 'Sonturk',
            'region_id' => $region->id,
            'address' => '5 07 00.5 N 1 16 19.2 W',
        ]);
        $suggestedOnlyKd = KeyDistributor::create([
            'name' => 'Outlet Cluster KD',
            'region_id' => $region->id,
            'address' => 'No depot coordinates here',
        ]);

        Outlet::create([
            'name' => 'Cluster Outlet One',
            'code' => 'CLUSTER-001',
            'kd_id' => $suggestedOnlyKd->id,
            'channel_type' => 'GT',
            'latitude' => 5.10000000,
            'longitude' => -0.10000000,
        ]);
        Outlet::create([
            'name' => 'Cluster Outlet Two',
            'code' => 'CLUSTER-002',
            'kd_id' => $suggestedOnlyKd->id,
            'channel_type' => 'GT',
            'latitude' => 5.20000000,
            'longitude' => -0.20000000,
        ]);

        $this->artisan('merchandisers:backfill-kd-coordinates', ['--suggest-outlets' => true])
            ->assertExitCode(0);

        $this->assertNull($decimalKd->fresh()->latitude);
        $this->assertNull($dmsKd->fresh()->latitude);
        $this->assertNull($suggestedOnlyKd->fresh()->latitude);

        $this->artisan('merchandisers:backfill-kd-coordinates', [
            '--write' => true,
            '--suggest-outlets' => true,
        ])->assertExitCode(0);

        $this->assertEqualsWithDelta(4.904211, (float) $decimalKd->fresh()->latitude, 0.00000001);
        $this->assertEqualsWithDelta(-1.758493, (float) $decimalKd->fresh()->longitude, 0.00000001);
        $this->assertEqualsWithDelta(5.11680556, (float) $dmsKd->fresh()->latitude, 0.00000001);
        $this->assertEqualsWithDelta(-1.272, (float) $dmsKd->fresh()->longitude, 0.00000001);
        $this->assertNull($suggestedOnlyKd->fresh()->latitude);
        $this->assertNull($suggestedOnlyKd->fresh()->longitude);
    }
    #[Test]
    public function clock_in_succeeds_well_inside_geofence_boundary()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Test KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.61745,
            'longitude' => -0.16812
        ]);
        $outlet = Outlet::create([
            'name' => 'Test Outlet',
            'code' => 'OUT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.61745,
            'longitude' => -0.16812
        ]);

        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 30, 0));

        // 20 meters away (lat difference of 0.00018 degrees)
        $response = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.61763,
            'longitude' => -0.16812
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet'
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function clock_in_fails_just_outside_geofence_boundary()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Test KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.61745,
            'longitude' => -0.16812
        ]);
        $outlet = Outlet::create([
            'name' => 'Test Outlet',
            'code' => 'OUT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.61745,
            'longitude' => -0.16812
        ]);

        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id
        ]);
        $outlet->assignedMerchandisers()->attach($user->id, [
            'assigned_by' => 1,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user);
        Carbon::setTestNow(Carbon::today('Africa/Accra')->setTime(9, 30, 0));

        // 40 meters away (lat difference of 0.00036 degrees, which is above the 30m geofence radius)
        $response = $this->post(route('merchandisers.clock-in'), [
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'latitude' => 5.61781,
            'longitude' => -0.16812
        ]);

        $response->assertSessionHasErrors('outlet_id');
        $this->assertDatabaseMissing('merchandiser_attendances', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet'
        ]);

        Carbon::setTestNow();
    }
    #[Test]
    public function user_can_update_profile_and_banking()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $this->actingAs($user);

        $response = $this->patch(route('merchandisers.profile.update'), [
            'name' => 'New Agent Name',
            'email' => 'newagent@cmih.africa',
            'phone' => '87654321',
            'residential_address' => 'East Legon, Accra',
            'bank_name' => 'GCB Bank',
            'bank_branch' => 'Main',
            'bank_account_name' => 'Agent Holder',
            'bank_account_number' => '1020304050',
            'momo_number' => '0244112233',
            'momo_name' => 'Agent Momo',
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Agent Name',
            'email' => 'newagent@cmih.africa',
            'phone' => '87654321',
            'residential_address' => 'East Legon, Accra',
            'bank_name' => 'GCB Bank',
            'bank_account_number' => '1020304050'
        ]);
    }
    #[Test]
    public function user_can_submit_leave_application()
    {
        $supervisor = User::create([
            'name' => 'Supervisor User',
            'email' => 'super@cmih.africa',
            'contact_email' => 'super@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'manager',
            'status' => 'active'
        ]);

        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'supervisor_id' => $supervisor->id
        ]);

        $cover = User::create([
            'name' => 'Cover Agent',
            'email' => 'cover-agent@cmih.africa',
            'contact_email' => 'cover-agent@cmih.africa',
            'phone' => '87654321',
            'date_of_birth' => '1994-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('merchandisers.leaves.store'), [
            'start_date' => Carbon::tomorrow()->toDateString(),
            'end_date' => Carbon::tomorrow()->addDays(5)->toDateString(),
            'leave_type' => 'annual',
            'covering_staff_id' => $cover->id,
            'comments' => 'Need rest',
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('leave_applications', [
            'user_id' => $user->id,
            'leave_type' => 'annual',
            'line_manager_id' => $supervisor->id,
            'covering_staff_id' => $cover->id,
            'status' => 'pending'
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'title' => 'Merchandiser Leave Approval Needed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $cover->id,
            'title' => 'Merchandiser Leave Cover Assigned',
        ]);
    }
    #[Test]
    public function user_can_submit_petty_cash_claim()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $this->actingAs($user);

        // Use create() instead of image() to avoid dependency on the GD extension
        $file = \Illuminate\Http\UploadedFile::fake()->create('receipt.jpg', 100);

        $response = $this->post(route('merchandisers.claims.store'), [
            'amount' => 150.00,
            'currency' => 'GHS',
            'description' => 'Bus fare to client venue',
            'receipt' => $file
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('petty_cash_claims', [
            'user_id' => $user->id,
            'amount' => 150.00,
            'currency' => 'GHS',
            'status' => 'pending'
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => 1,
            'title' => 'Merchandiser Claim Approval Needed',
        ]);
    }
    #[Test]
    public function user_can_submit_salary_advance_loan()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'salary' => 5000.00
        ]);

        $this->actingAs($user);

        // 1. Submit too much (limit is 5000 * 2 = 10,000)
        $response1 = $this->post(route('merchandisers.loans.store'), [
            'amount' => 12000.00,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 1000.00,
            'reason' => 'Emergency needs'
        ]);

        $response1->assertSessionHasErrors('amount');

        // 2. Submit valid amount
        $response2 = $this->post(route('merchandisers.loans.store'), [
            'amount' => 3000.00,
            'repayment_style' => 'monthly_deduction',
            'monthly_deduction_amount' => 500.00,
            'reason' => 'Emergency needs'
        ]);

        $response2->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('salary_advances', [
            'user_id' => $user->id,
            'amount' => 3000.00,
            'status' => 'pending'
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => 1,
            'title' => 'Merchandiser Salary Advance Approval Needed',
        ]);
    }
    #[Test]
    public function user_can_submit_quarterly_appraisal()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $this->actingAs($user);

        $response = $this->post(route('merchandisers.appraisals.store'), [
            'feedback' => 'Worked hard this quarter',
            'scores' => [
                'attendance' => 9,
                'execution' => 8,
                'orders' => 10,
                'communication' => 9
            ]
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        
        $appraisal = \App\Models\Appraisal::where('user_id', $user->id)->first();
        $this->assertNotNull($appraisal);
        $this->assertEquals(9, $appraisal->self_assessment['scores']['attendance']);
        $this->assertEquals('Worked hard this quarter', $appraisal->self_assessment['feedback']);
    }
    #[Test]
    public function user_can_checkout_posm_materials()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $this->actingAs($user);

        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('proof.jpg', 100);

        $response = $this->post(route('merchandisers.inventory.store'), [
            'item_name' => 'Guinness T-Shirts',
            'quantity_out' => 20,
            'location' => 'Osu GT Store',
            'notes' => 'Handing over to promoters',
            'image' => $file
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        
        $ledger = \App\Models\PosmLedger::where('created_by', $user->id)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('Guinness T-Shirts', $ledger->item_name);
        $this->assertEquals(20, $ledger->quantity_out);
        $this->assertEquals('Osu GT Store', $ledger->location);
        $this->assertNotNull($ledger->image_path);
        
        $this->assertDatabaseHas('posm_ledgers', [
            'created_by' => $user->id,
            'item_name' => 'Guinness T-Shirts',
            'quantity_out' => 20,
            'location' => 'Osu GT Store',
            'image_path' => $ledger->image_path
        ]);
    }
    #[Test]
    public function lateness_deductions_applied_correctly_on_payroll()
    {
        $region = Region::create(['name' => 'ACCRA', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create([
            'name' => 'Test KD',
            'region_id' => $region->id,
            'address' => 'Accra',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);
        $outlet = Outlet::create([
            'name' => 'Test Outlet',
            'code' => 'OUT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'address' => 'Osu',
            'latitude' => 5.6037,
            'longitude' => -0.1870
        ]);

        $user = User::create([
            'name' => 'Field Agent',
            'email' => 'field@cmih.africa',
            'contact_email' => 'field@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active',
            'kd_id' => $kd->id,
            'region_id' => $region->id,
            'salary' => 1000.00
        ]);

        MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => '2026-06-01',
            'sequence' => 1,
            'status' => 'planned',
        ]);
        MerchandiserOutletAssignment::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'assigned_date' => '2026-06-02',
            'sequence' => 1,
            'status' => 'planned',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 6, 1, 9, 2, 0, 'Africa/Accra'));

        MerchandiserAttendance::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'clock_in_time' => Carbon::now(),
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'distance_from_outlet' => 5.2,
            'status' => 'late-start',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0, 'Africa/Accra'));
        $payroll = \App\Http\Controllers\Merchandiser\MerchandiserController::calculatePayrollDetails($user, 2026, 6);

        $this->assertEquals(15.00, $payroll['deductions']);
        $this->assertEquals(985.00, $payroll['net_pay']);

        Carbon::setTestNow();
    }
    #[Test]
    public function merchandiser_can_create_administrative_survey()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $this->actingAs($user);

        $response = $this->post(route('merchandisers.surveys.store'), [
            'title' => 'Weekly Merchandiser Feedback Survey',
            'description' => 'Collect weekly feedback from agents',
            'status' => 'published',
            'is_anonymous' => 0,
            'questions' => [
                [
                    'question_text' => 'Rate stock availability',
                    'question_type' => 'dropdown',
                    'options' => ['Low', 'Medium', 'High'],
                    'is_required' => 1
                ],
                [
                    'question_text' => 'Any remarks?',
                    'question_type' => 'paragraph',
                    'is_required' => 0
                ]
            ]
        ]);

        $response->assertRedirect(route('merchandisers.dashboard'));
        $this->assertDatabaseHas('surveys', [
            'title' => 'Weekly Merchandiser Feedback Survey',
            'created_by' => $user->id,
            'status' => 'published'
        ]);

        $survey = \App\Models\Survey::where('title', 'Weekly Merchandiser Feedback Survey')->first();
        $this->assertCount(2, $survey->questions);
        $this->assertEquals('Rate stock availability', $survey->questions[0]->question_text);
        $this->assertEquals(['Low', 'Medium', 'High'], $survey->questions[0]->options);
    }
    #[Test]
    public function merchandiser_can_mark_notification_as_read()
    {
        $user = User::create([
            'name' => 'Agent Name',
            'email' => 'agent@cmih.africa',
            'contact_email' => 'agent@personal.com',
            'phone' => '12345678',
            'date_of_birth' => '1995-05-05',
            'password' => Hash::make('Pass123'),
            'access_role' => 'merchandiser',
            'status' => 'active'
        ]);

        $notif = \App\Models\Notification::create([
            'user_id' => $user->id,
            'title' => 'Urgent: Stock Replenish',
            'message' => 'Please check the main shelves at Osu GT.',
            'url' => null,
            'read_at' => null
        ]);

        $this->actingAs($user);

        $response = $this->post(route('merchandisers.notifications.read', $notif));

        $response->assertRedirect();
        $this->assertNotNull($notif->fresh()->read_at);
    }
    #[Test]
    public function admin_can_generate_client_share_link()
    {
        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'admin@cmih.africa',
            'contact_email' => 'admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active'
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('merchandisers.admin.share.generate'), [
            'label' => 'Unilever Q2 Review',
            'show_overview' => '1',
            'show_tracking' => '1'
        ]);

        $response->assertSessionHas('share_url');
        $this->assertDatabaseHas('merchandiser_reports', [
            'label' => 'Unilever Q2 Review',
            'created_by' => $admin->id
        ]);
    }
    #[Test]
    public function merchandiser_admin_dashboard_renders_active_share_links()
    {
        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'share-admin@cmih.africa',
            'contact_email' => 'share-admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active'
        ]);

        \App\Models\MerchandiserReport::create([
            'token' => 'active-dashboard-report',
            'created_by' => $admin->id,
            'label' => 'Active Dashboard Report',
            'sections_config' => ['show_overview' => true],
            'expires_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin)
            ->withSession(['share_url' => 'https://cmih.africa/public/reports/demo-copy-link'])
            ->get(route('merchandisers.admin.dashboard'))
            ->assertOk()
            ->assertSee('Active Dashboard Report')
            ->assertSee('Revoke')
            ->assertSee('copyShareLink', false)
            ->assertDontSee("alert('Link copied!')", false);
    }
    #[Test]
    public function client_can_view_shared_report()
    {
        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'admin@cmih.africa',
            'contact_email' => 'admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active'
        ]);

        $report = \App\Models\MerchandiserReport::create([
            'token' => 'test-report-token-xyz',
            'created_by' => $admin->id,
            'label' => 'Unilever Client Dashboard',
            'sections_config' => ['show_overview' => true],
            'expires_at' => now()->addHours(2)
        ]);

        // Access publicly without logging in
        $response = $this->get(route('merchandisers.report.view', 'test-report-token-xyz'));

        $response->assertStatus(200);
        $response->assertSee('Unilever Client Dashboard');
    }
    #[Test]
    public function merchandiser_dashboard_shows_real_outlet_and_clockin_metrics()
    {
        $region = Region::create(['name' => 'Greater Accra', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Awen Yami', 'region_id' => $region->id]);
        $user = User::factory()->create([
            'access_role' => 'merchandiser',
            'status' => 'active',
            'region_id' => $region->id,
            'kd_id' => $kd->id,
        ]);
        $outletOne = Outlet::create([
            'name' => 'Madina Shop',
            'code' => 'MAD-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.1,
            'longitude' => -0.1,
        ]);
        Outlet::create([
            'name' => 'Osu Shop',
            'code' => 'OSU-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.2,
            'longitude' => -0.2,
        ]);
        MerchandiserVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outletOne->id,
            'branded_shelf_available' => true,
            'hangers_available' => true,
        ]);
        MerchandiserAttendance::create([
            'user_id' => $user->id,
            'outlet_id' => $outletOne->id,
            'clock_in_type' => 'outlet',
            'clock_in_time' => now(),
            'latitude' => 5.1,
            'longitude' => -0.1,
            'distance_from_outlet' => 5,
            'status' => 'on-time',
        ]);

        $response = $this->actingAs($user)->get(route('merchandisers.dashboard'));

        $response->assertOk();
        $response->assertSee('Outlets Clocked In');
        $response->assertSee('Not Covered');
        $response->assertSee('Scored Today');
        $response->assertSee('Monthly outlets covered');
    }
    #[Test]
    public function shared_report_shows_agent_clockin_details_for_clients()
    {
        $admin = User::create([
            'name' => 'Brands Report Admin',
            'email' => 'report-admin@cmih.africa',
            'contact_email' => 'report-admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Accra Client Region', 'timezone' => 'Africa/Accra']);
        $kd = KeyDistributor::create(['name' => 'Client KD', 'region_id' => $region->id]);
        $agent = User::factory()->create([
            'name' => 'Client Visible Agent',
            'access_role' => 'merchandiser',
            'status' => 'active',
            'region_id' => $region->id,
            'kd_id' => $kd->id,
        ]);
        $outlet = Outlet::create([
            'name' => 'Client Outlet',
            'code' => 'CLIENT-001',
            'kd_id' => $kd->id,
            'channel_type' => 'GT',
            'latitude' => 5.3,
            'longitude' => -0.3,
        ]);
        MerchandiserAttendance::create([
            'user_id' => $agent->id,
            'outlet_id' => $outlet->id,
            'clock_in_type' => 'outlet',
            'clock_in_time' => now(),
            'latitude' => 5.3,
            'longitude' => -0.3,
            'distance_from_outlet' => 9,
            'status' => 'on-time',
        ]);

        \App\Models\MerchandiserReport::create([
            'token' => 'client-detail-token',
            'created_by' => $admin->id,
            'label' => 'Detailed Client Dashboard',
            'sections_config' => ['show_overview' => true],
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->get(route('merchandisers.report.view', 'client-detail-token'));

        $response->assertOk();
        $response->assertSee('Field Agent Clock-In Detail');
        $response->assertSee('Client Visible Agent');
        $response->assertSee('Client KD');
        $response->assertSee('OUTLET');
        $response->assertSee('Client Outlet');
    }
    #[Test]
    public function client_cannot_view_expired_or_revoked_report()
    {
        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'admin@cmih.africa',
            'contact_email' => 'admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active'
        ]);

        // 1. Expired
        $report1 = \App\Models\MerchandiserReport::create([
            'token' => 'expired-token',
            'created_by' => $admin->id,
            'label' => 'Unilever Old Report',
            'sections_config' => ['show_overview' => true],
            'expires_at' => now()->subHour()
        ]);

        $response = $this->get(route('merchandisers.report.view', 'expired-token'));
        $response->assertSee('Link Expired');

        // 2. Revoked
        $report2 = \App\Models\MerchandiserReport::create([
            'token' => 'revoked-token',
            'created_by' => $admin->id,
            'label' => 'Unilever Revoked Report',
            'sections_config' => ['show_overview' => true],
            'expires_at' => now()->addHours(2),
            'is_revoked' => true
        ]);

        $response = $this->get(route('merchandisers.report.view', 'revoked-token'));
        $response->assertSee('Link Expired');
    }
    #[Test]
    public function admin_can_export_operation_data()
    {
        $admin = User::create([
            'name' => 'Brands Admin',
            'email' => 'admin@cmih.africa',
            'contact_email' => 'admin@cmih.africa',
            'password' => Hash::make('Pass123'),
            'access_role' => 'admin',
            'status' => 'active'
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('merchandisers.admin.export', 'merchandisers') . '?format=csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="merchandiser_merchandisers_' . now()->format('Y-m-d') . '.csv"');
    }
}
