<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_documents_are_optional_but_can_still_be_completed(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $this->assertFalse($staff->requiresIdentityDocument());
        $this->assertTrue($staff->hasRequiredIdentityDocument());
        $this->assertFalse($staff->hasCompleteIdentityDocument());

        $staff->forceFill([
            'nationality_code' => 'NG',
            'identity_document_type' => 'national_id',
            'national_id_type' => User::nationalIdLabelFor('NG'),
            'national_id_number' => '12345678901',
            'national_id_front_path' => 'identity-documents/nin.jpg',
        ])->save();

        $this->assertTrue($staff->fresh()->hasCompleteIdentityDocument());
        $this->assertSame('National Identification Number (NIN) Card or Slip', User::nationalIdLabelFor('NG'));
    }

    public function test_ghana_card_requires_front_and_back_images(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'nationality_code' => 'GH',
            'identity_document_type' => 'national_id',
            'national_id_number' => 'GHA-123456789-0',
            'national_id_front_path' => 'identity-documents/front.jpg',
        ]);

        $this->assertFalse($staff->hasCompleteIdentityDocument());
        $this->assertTrue($staff->hasRequiredIdentityDocument());

        $staff->forceFill(['national_id_back_path' => 'identity-documents/back.jpg'])->save();

        $this->assertTrue($staff->fresh()->hasCompleteIdentityDocument());
    }

    public function test_passport_requires_number_and_actual_passport_document_image(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'nationality_code' => 'SL',
            'identity_document_type' => 'passport',
            'passport_number' => 'P1234567',
        ]);

        $this->assertFalse($staff->hasCompleteIdentityDocument());
        $this->assertTrue($staff->hasRequiredIdentityDocument());

        $staff->forceFill(['passport_photo_path' => 'identity-documents/passport-page.jpg'])->save();

        $this->assertTrue($staff->fresh()->hasCompleteIdentityDocument());
    }

    public function test_only_named_account_types_are_exempt_from_identity_enforcement(): void
    {
        $cyril = User::factory()->create([
            'name' => 'Cyril Hilton',
            'email' => 'cyrilhilton@cmih.africa',
            'access_role' => 'manager',
        ]);
        $superAdmin = User::factory()->create(['access_role' => 'super_admin']);
        $presentation = User::factory()->create(['email' => 'cmihstaffs@cmih.africa']);
        $curtis = User::factory()->create(['name' => 'Curtis Barnor', 'access_role' => 'manager']);
        $impersonatedName = User::factory()->create(['name' => 'Cyril Hilton', 'access_role' => 'staff']);

        $this->assertTrue($cyril->isIdentityDocumentExempt());
        $this->assertTrue($superAdmin->isIdentityDocumentExempt());
        $this->assertTrue($presentation->isIdentityDocumentExempt());
        $this->assertFalse($curtis->isIdentityDocumentExempt());
        $this->assertFalse($impersonatedName->isIdentityDocumentExempt());
    }

    public function test_merchandiser_accounts_are_not_yet_subject_to_identity_verification(): void
    {
        $merchandiser = User::factory()->create([
            'status' => 'active',
            'access_role' => User::MERCHANDISER_ROLE,
        ]);

        $this->assertFalse($merchandiser->requiresIdentityDocument());
        $this->assertTrue($merchandiser->hasRequiredIdentityDocument());
    }

    public function test_staff_can_upload_country_appropriate_id_from_profile(): void
    {
        Storage::fake('local');

        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
            'name' => 'Nigeria Staff',
        ]);

        $response = $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => $staff->name,
            'email' => $staff->email,
            'nationality_code' => 'NG',
            'identity_document_type' => 'national_id',
            'national_id_number' => '12345678901',
            'national_id_front' => UploadedFile::fake()->createWithContent(
                'nin.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('NG', $staff->nationality_code);
        $this->assertSame('national_id', $staff->identity_document_type);
        $this->assertSame('National Identification Number (NIN) Card or Slip', $staff->national_id_type);
        $this->assertTrue($staff->hasCompleteIdentityDocument());
        Storage::disk('local')->assertExists($staff->national_id_front_path);
    }

    public function test_partial_identity_details_remain_optional_after_clock_in(): void
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'access_role' => 'staff',
        ]);

        $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'Updated Before Clock In',
            'email' => $staff->email,
            'identity_document_type' => 'national_id',
        ])->assertSessionHasNoErrors();

        Attendance::create([
            'user_id' => $staff->id,
            'clock_in_at' => now(),
            'daily_objective' => 'Complete assigned work.',
            'status' => 'On Time',
        ]);

        $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'Blocked After Clock In',
            'email' => $staff->email,
            'identity_document_type' => 'national_id',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Blocked After Clock In', $staff->fresh()->name);
    }

    public function test_identity_documents_are_reviewable_only_by_hr_manager_superadmin_or_cvo(): void
    {
        $hrManager = User::factory()->create([
            'department' => 'hr_admin',
            'position_title' => 'HR Manager',
        ]);
        $cvo = User::factory()->create(['position_title' => 'CVO']);
        $superAdmin = User::factory()->create(['access_role' => 'super_admin']);
        $developer = User::factory()->create(['name' => 'Curtis Barnor', 'access_role' => 'manager']);
        $regularStaff = User::factory()->create(['access_role' => 'staff']);

        $this->assertTrue($hrManager->canReviewIdentityDocuments());
        $this->assertTrue($cvo->canReviewIdentityDocuments());
        $this->assertTrue($superAdmin->canReviewIdentityDocuments());
        $this->assertFalse($developer->canReviewIdentityDocuments());
        $this->assertFalse($regularStaff->canReviewIdentityDocuments());
    }

    public function test_private_identity_files_are_downloadable_only_by_the_owner_or_authorized_reviewers(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('identity-documents/target/front.jpg', 'private-id-image');

        $target = User::factory()->create([
            'status' => 'active',
            'nationality_code' => 'NG',
            'identity_document_type' => 'national_id',
            'national_id_number' => '12345678901',
            'national_id_front_path' => 'identity-documents/target/front.jpg',
        ]);
        $hrManager = User::factory()->create([
            'status' => 'active',
            'department' => 'hr_admin',
            'position_title' => 'HR Manager',
        ]);
        $cvo = User::factory()->create(['status' => 'active', 'position_title' => 'CVO']);
        $superAdmin = User::factory()->create(['status' => 'active', 'access_role' => 'super_admin']);
        $regularStaff = User::factory()->create(['status' => 'active', 'access_role' => 'staff']);

        foreach ([$target, $hrManager, $cvo, $superAdmin] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('portal.payroll.document', [$target, 'national-id-front']))
                ->assertOk();
        }

        $this->actingAs($regularStaff)
            ->get(route('portal.payroll.document', [$target, 'national-id-front']))
            ->assertForbidden();
    }

    public function test_hr_manager_and_cvo_modules_show_the_confidential_identity_register(): void
    {
        $target = User::factory()->create([
            'name' => 'Identity Register Staff',
            'status' => 'active',
        ]);
        $merchandiser = User::factory()->create([
            'name' => 'Merchandiser Not In Register',
            'status' => 'active',
            'access_role' => User::MERCHANDISER_ROLE,
        ]);
        $hrManager = User::factory()->create([
            'status' => 'active',
            'department' => 'hr_admin',
            'position_title' => 'HR Manager',
        ]);
        $hrAssistant = User::factory()->create([
            'status' => 'active',
            'department' => 'hr_admin',
            'position_title' => 'Executive',
        ]);
        $cvo = User::factory()->create([
            'status' => 'active',
            'position_title' => 'CVO',
            'job_level' => 'CVO',
        ]);

        $this->actingAs($hrManager)
            ->get(route('portal.hr'))
            ->assertOk()
            ->assertSee('Identity Verification Register')
            ->assertSee($target->name)
            ->assertDontSee($merchandiser->name);

        $this->actingAs($hrAssistant)
            ->get(route('portal.hr'))
            ->assertOk()
            ->assertDontSee('Identity Verification Register');

        $this->actingAs($cvo)
            ->get(route('portal.cvo'))
            ->assertOk()
            ->assertSee('Identity Verification Register')
            ->assertSee($target->name)
            ->assertDontSee($merchandiser->name);
    }

    public function test_identity_sms_command_sends_once_per_unique_active_phone(): void
    {
        config([
            'services.sms.default' => 'mnotify',
            'services.mnotify.api_key' => 'test-key',
            'services.mnotify.sender_id' => 'CMIH',
            'services.mnotify.endpoint' => 'https://api.mnotify.test/api/sms/quick',
        ]);
        Http::fake(['api.mnotify.test/*' => Http::response(['status' => 'success'], 200)]);

        User::factory()->create(['status' => 'active', 'phone' => '0244000001']);
        User::factory()->create(['status' => 'active', 'phone' => '0244000001']);
        User::factory()->create(['status' => 'inactive', 'phone' => '0244000002']);
        User::factory()->create([
            'status' => 'active',
            'access_role' => User::MERCHANDISER_ROLE,
            'phone' => '0244000003',
        ]);

        $this->artisan('staff:send-identity-verification-sms', ['--send' => true])
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['sender'] === 'CMIH'
            && $request['recipient'] === ['0244000001']
            && str_contains($request['message'], 'Identity Verification')
            && str_contains($request['message'], 'optional'));
    }
}
