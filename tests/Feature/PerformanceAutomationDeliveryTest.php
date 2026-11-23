<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PerformanceOutbox;
use App\Models\User;
use App\Services\PerformanceAutomation;
use App\Services\PerformanceWorkflow;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PerformanceAutomationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_in_app_only_even_if_legacy_email_settings_are_enabled(): void
    {
        config(['performance.email_enabled'=>true,'creative.email_enabled'=>true,'mail.default'=>'array']);
        $user=User::factory()->create(['status'=>'active','work_email'=>'preview-only@example.test']);
        PerformanceWorkflow::notify($user->id,'in-app-only-test','Performance review ready','Review the verified output.');
        $this->artisan('performance:process')->assertSuccessful();
        $mailer=Mail::mailer('array');
        $this->assertCount(0,$mailer->getSymfonyTransport()->messages());
        $this->assertNull(PerformanceOutbox::firstOrFail()->emailed_at);
        $this->assertNotNull(PerformanceOutbox::firstOrFail()->dashboard_at);
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
        $this->artisan('performance:process')->assertSuccessful();
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
        $this->assertCount(0,$mailer->getSymfonyTransport()->messages());
    }

    public function test_the_global_mail_guard_blocks_direct_mail_and_leaves_in_app_delivery_available(): void
    {
        $mailer=Mail::mailer('array');
        $mailer->raw('This must never leave the application.',fn($mail)=>$mail->to('isolated@example.test')->subject('Policy test'));
        $this->assertCount(0,$mailer->getSymfonyTransport()->messages());
        $user=User::factory()->create(['status'=>'active']);
        PerformanceWorkflow::notify($user->id,'direct-mail-policy','In-app notice','Local in-app notification.');
        app(PerformanceAutomation::class)->deliver();
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
        $this->assertSame(0,(int)PerformanceOutbox::firstOrFail()->attempts);
    }

    public function test_reports_have_real_scheduled_commands_for_every_required_frequency(): void
    {
        $events=collect(app(Schedule::class)->events());
        foreach(['weekly'=>'0 7 * * 1','monthly'=>'10 7 1 * *','quarterly'=>'20 7 1 1-12/3 *'] as $frequency=>$expression){
            $event=$events->first(fn($event)=>str_contains($event->command??'','performance:report '.$frequency));
            $this->assertNotNull($event,$frequency.' schedule exists');
            $this->assertSame($expression,$event->expression);
            $this->assertSame('Africa/Accra',$event->timezone);
            $this->assertTrue($event->withoutOverlapping);
        }
        $hourly=$events->first(fn($event)=>str_contains($event->command??'','performance:process'));
        $this->assertSame('0 * * * *',$hourly->expression);
    }
}
