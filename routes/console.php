<?php

use App\Billing\BillingReconciler;
use App\Billing\ProcessStripeEvent;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Notifications\OutboxDispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('smilesuite:dispatch-outbox', function (OutboxDispatcher $outbox) {
    $this->info('Queued '.$outbox->dispatchPending().' appointment notification(s).');
})->purpose('Recover and dispatch tenant-scoped appointment notifications');

Artisan::command('smilesuite:recover-billing', function () {
    $count = 0;
    WebhookEvent::whereIn('state', ['pending', 'failed'])->where('attempts', '<', 5)->whereNotNull('tenant_id')->orderBy('id')->chunkById(100, function ($events) use (&$count) {
        foreach ($events as $event) {
            ProcessStripeEvent::dispatch($event->id);
            $count++;
        }
    });
    $this->info('Queued '.$count.' billing event(s).');
})->purpose('Recover durably accepted Stripe events after queue outages');

Artisan::command('smilesuite:reconcile-billing', function (BillingReconciler $billing) {
    if (! config('smilesuite.live_billing')) {
        $this->info('Live billing is disabled.');

        return;
    }
    $failed = 0;
    foreach (Tenant::whereNotNull('stripe_id')->orderBy('id')->cursor() as $tenant) {
        try {
            $billing->reconcile($tenant);
        } catch (Throwable $exception) {
            $failed++;
            $this->error('Reconciliation failed for tenant '.$tenant->id.'.');
        }
    }

    return $failed ? 1 : 0;
})->purpose('Reconcile current provider subscription state for explicitly identified tenants');

Schedule::command('smilesuite:dispatch-outbox')->everyMinute()->withoutOverlapping();
Schedule::command('smilesuite:recover-billing')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('smilesuite:reconcile-billing')->daily()->withoutOverlapping();
