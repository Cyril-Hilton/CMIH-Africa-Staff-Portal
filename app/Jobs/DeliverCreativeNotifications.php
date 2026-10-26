<?php
namespace App\Jobs;

use App\Services\PerformanceAutomation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverCreativeNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $requestId) {}

    public function handle(PerformanceAutomation $automation): void
    {
        $automation->deliver(route('portal.creative', ['request' => $this->requestId]));
    }
}
