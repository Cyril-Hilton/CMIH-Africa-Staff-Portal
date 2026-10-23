<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborativeEventsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $department = 'client_relations'): User
    {
        return User::factory()->create(['department' => $department, 'status' => 'active', 'access_role' => 'staff']);
    }

    private function flyer(string $name = 'campus-beauty-activation.png', bool $edited = false): UploadedFile
    {
        $im = imagecreatetruecolor(600, 800);
        imagefill($im, 0, 0, imagecolorallocate($im, 240, 244, 250));
        for ($i = 0; $i < 7; $i++) {
            imagefilledrectangle($im, 30 + $i * 13, 60 + $i * 90, 550 - $i * 15, 100 + $i * 90, imagecolorallocate($im, 20 + $i * 25, 40 + $i * 10, 90 + $i * 12));
        }imagestring($im, 5, 80, 730, 'UPCOMING CAMPUS EVENT', imagecolorallocate($im, 10, 10, 10));
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return UploadedFile::fake()->createWithContent($name, $bytes.($edited ? 'metadata-change' : ''));
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['title' => 'Dove Campus Activation', 'starts_at' => today()->addDays(7)->toDateString(), 'ends_at' => today()->addDays(8)->toDateString(), 'location' => 'University of Ghana', 'summary' => 'Campus skincare activation', 'status' => 'published', 'image' => $this->flyer()];
    }

    public function test_team_members_publish_a_safe_public_feed_and_drafts_remain_private(): void
    {
        Storage::fake('local');
        $user = $this->staff();
        $this->actingAs($user)->get(route('portal.events.index'))->assertOk();
        $this->post(route('portal.events.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $event = Event::firstOrFail();
        $this->assertSame('local', $event->image_disk);
        $this->assertStringEndsWith('.webp', $event->image_path);
        Storage::disk('local')->assertExists($event->image_path);
        $this->getJson(route('public.events.index'))->assertOk()->assertJsonPath('data.0.title', 'Dove Campus Activation')->assertJsonMissingPath('data.0.created_by')->assertJsonMissingPath('data.0.flyer_sha256')->assertJsonMissingPath('data.0.image_path');
        $this->get(route('public.events.flyer', $event))->assertOk()->assertHeader('content-type', 'image/webp');
        $this->actingAs($this->staff('operations_projects'))->get(route('portal.events.index'))->assertOk()->assertSee('Dove Campus Activation');
        $event->update(['status' => 'draft']);
        $this->getJson(route('public.events.index'))->assertJsonCount(0, 'data');
        $this->get(route('public.events.flyer', $event))->assertNotFound();
    }

    public function test_duplicate_content_name_and_visual_checks_do_not_store_orphan_files(): void
    {
        Storage::fake('local');
        $this->actingAs($this->staff())->post(route('portal.events.store'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->staff('operations_projects'))->post(route('portal.events.store'), $this->payload(['title' => 'Different title', 'image' => $this->flyer('renamed.png')]))->assertSessionHasErrors('image');
        $this->post(route('portal.events.store'), $this->payload(['title' => 'Another event', 'image' => $this->flyer('new-file.png', true)]))->assertSessionHasErrors('image');
        $this->post(route('portal.events.store'), $this->payload(['title' => 'DOVE — CAMPUS ACTIVATION!', 'image' => UploadedFile::fake()->image('different.png', 300, 400)]))->assertSessionHasErrors('image');
        $this->assertSame(1, Event::count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_other_departments_cannot_upload_and_stale_changes_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->staff('finance'))->post(route('portal.events.store'), $this->payload())->assertForbidden();
        $user = $this->staff();
        $this->actingAs($user)->post(route('portal.events.store'), $this->payload())->assertSessionHasNoErrors();
        $event = Event::firstOrFail();
        $payload = $this->payload(['revision' => $event->revision, 'image' => null, 'status' => 'draft']);
        unset($payload['image']);
        $this->patch(route('portal.events.update', $event), $payload)->assertSessionHasNoErrors();
        $this->patch(route('portal.events.update', $event), $payload)->assertStatus(409);
        $this->actingAs($this->staff('operations_projects'))->patch(route('portal.events.update', $event), $payload)->assertForbidden();
    }

    public function test_public_feed_obeys_dates_split_mode_and_conditional_caching(): void
    {
        config(['cmih.app_kind' => 'staff']);
        $user = $this->staff();
        Event::create(['title' => 'Live multi-day event', 'starts_at' => today()->subDay(), 'ends_at' => today()->addDay(), 'created_by' => $user->id, 'status' => 'published']);
        Event::create(['title' => 'Past event', 'starts_at' => today()->subDays(7), 'created_by' => $user->id, 'status' => 'published']);
        $response = $this->getJson(route('public.events.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->withHeader('If-None-Match',$response->headers->get('etag'))->getJson(route('public.events.index'))->assertStatus(304);
        $this->getJson(route('public.events.index',['per_page' => 1000]))->assertUnprocessable();
    }
    public function test_resized_flyers_are_detected_and_external_field_roles_are_denied(): void
    {
        Storage::fake('local');
        $this->actingAs($this->staff())->post(route('portal.events.store'), $this->payload())->assertSessionHasNoErrors();
        $original = $this->flyer();
        $image = imagecreatefromstring(file_get_contents($original->getRealPath()));
        $resized = imagescale($image, 300, 400);
        ob_start(); imagejpeg($resized, null, 90); $bytes = ob_get_clean();
        imagedestroy($image); imagedestroy($resized);
        $this->post(route('portal.events.store'), $this->payload(['title'=>'Renamed resized copy','image'=>UploadedFile::fake()->createWithContent('copy.jpg', $bytes)]))->assertSessionHasErrors('image');
        $this->assertSame(1, Event::count());
        $fieldUser = $this->staff('operations_projects');
        $fieldUser->update(['access_role'=>User::BRAND_PROMOTER_ROLE]);
        $this->actingAs($fieldUser)->get(route('portal.events.index'))->assertForbidden();
    }
    public function test_legacy_flyers_can_be_backfilled_without_changing_real_event_details(): void
    {
        Storage::fake('public');
        $user = $this->staff();
        $flyer = $this->flyer();
        Storage::disk('public')->put('events/legacy.png', file_get_contents($flyer->getRealPath()));
        $event = Event::create(['title'=>'Existing event','starts_at'=>today()->addDays(5),'created_by'=>$user->id,'status'=>'published','image_path'=>'events/legacy.png','image_disk'=>'public']);
        $this->artisan('events:fingerprint-existing')->assertSuccessful();
        $this->assertSame('Existing event', $event->fresh()->title);
        $this->assertNotNull($event->fresh()->flyer_fingerprint);
        $this->actingAs($user)->post(route('portal.events.store'), $this->payload())->assertSessionHasErrors('image');
        $this->assertSame(1, Event::count());
    }
}
