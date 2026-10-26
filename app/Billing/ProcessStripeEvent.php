<?php

namespace App\Billing;

use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStripeEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(BillingReconciler $reconciler): void
    {
        $event = WebhookEvent::findOrFail($this->eventId);
        $reconciler->process($event);
    }
}
