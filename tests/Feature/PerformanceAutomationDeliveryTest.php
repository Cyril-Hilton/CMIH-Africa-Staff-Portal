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

    public function test_real_local_mail_transport_and_dashboard_delivery_are_deduplicated(): void
    {
        config(['performance.email_enabled'=>true,'mail.default'=>'array']);
        $user=User::factory()->create(['status'=>'active','work_email'=>'preview-only@example.test']);
        PerformanceWorkflow::notify($user->id,'local-mail-test','Performance review ready','Review the verified output.');
        $this->artisan('performance:process')->assertSuccessful();
        $mailer=Mail::mailer('array');
        $messages=$mailer->getSymfonyTransport()->messages();
        $this->assertCount(1,$messages);
        $email=$messages->first()->getOriginalMessage();
        $this->assertSame('preview-only@example.test',$email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Review the verified output.',$email->getTextBody());
        $this->assertStringContainsString('/portal/performance',$email->getTextBody());
        $this->assertNotNull(PerformanceOutbox::firstOrFail()->emailed_at);
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
        $this->artisan('performance:process')->assertSuccessful();
        $this->assertCount(1,$mailer->getSymfonyTransport()->messages());
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
    }

    public function test_mail_failure_is_bounded_and_does_not_repeat_dashboard_notifications(): void
    {
        config(['performance.email_enabled'=>true]);
        $user=User::factory()->create(['status'=>'active']);
        PerformanceWorkflow::notify($user->id,'retry-test','Test only','Local simulated failure');
        Mail::shouldReceive('raw')->times(5)->andThrow(new \RuntimeException('Simulated local transport outage'));
        $service=app(PerformanceAutomation::class);
        for($i=0;$i<6;$i++)$service->deliver();
        $row=PerformanceOutbox::firstOrFail();
        $this->assertSame(5,(int)$row->attempts);
        $this->assertNull($row->emailed_at);
        $this->assertNotNull($row->dashboard_at);
        $this->assertSame(1,Notification::where('user_id',$user->id)->count());
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
