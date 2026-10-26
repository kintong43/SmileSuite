<?php

namespace App\Billing;

use App\Models\BillingEntitlement;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BillingReconciler
{
    public function __construct(private SubscriptionProvider $provider, private TenantRunner $runner) {}

    public function process(WebhookEvent $event): void
    {
        if (! in_array($event->state, ['pending', 'failed'], true) || ! $event->tenant_id) {
            return;
        }
        $event->increment('attempts');
        try {
            $this->reconcile(Tenant::findOrFail($event->tenant_id));
            $event->update(['state' => 'processed', 'processed_at' => now(), 'last_error_code' => null]);
        } catch (\Throwable $exception) {
            $event->update(['state' => 'failed', 'last_error_code' => 'provider_reconciliation_failed']);
            throw $exception;
        }
    }

    public function reconcile(Tenant $tenant): void
    {
        $this->runner->run($tenant, function () use ($tenant): void {
            DB::transaction(function () use ($tenant): void {
                $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $state = $this->provider->current($locked);
                $status = $state['status'] ?? 'none';
                $items = $state['items'] ?? [];
                $price = count($items) === 1 ? $items[0]['price'] : null;
                $tier = 'none';
                foreach (['starter', 'pro'] as $candidate) {
                    if ($price && in_array($price, array_filter((array) config("billing.prices.$candidate", [])), true)) {
                        $tier = $candidate;
                    }
                }
                if ($state) {
                    $existingOwner = DB::table('subscriptions')->where('stripe_id', $state['id'])->value('tenant_id');
                    if ($existingOwner && (int) $existingOwner !== (int) $tenant->id) {
                        throw new \RuntimeException('subscription_owner_conflict');
                    }
                    DB::table('subscriptions')->updateOrInsert(['stripe_id' => $state['id']], ['tenant_id' => $tenant->id, 'type' => 'default', 'stripe_status' => $status, 'stripe_price' => $price, 'quantity' => $items[0]['quantity'] ?? null, 'trial_ends_at' => isset($state['trial_end']) ? CarbonImmutable::createFromTimestamp($state['trial_end']) : null, 'ends_at' => isset($state['cancel_at']) ? CarbonImmutable::createFromTimestamp($state['cancel_at']) : null, 'created_at' => now(), 'updated_at' => now()]);
                    $subscriptionId = DB::table('subscriptions')->where('stripe_id', $state['id'])->value('id');
                    $liveItemIds = array_column($items, 'id');
                    DB::table('subscription_items')->where('subscription_id', $subscriptionId)->whereNotIn('stripe_id', $liveItemIds)->delete();
                    foreach ($items as $item) {
                        DB::table('subscription_items')->updateOrInsert(['stripe_id' => $item['id']], ['subscription_id' => $subscriptionId, 'stripe_product' => $item['product'], 'stripe_price' => $item['price'], 'quantity' => $item['quantity'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                $entitlement = BillingEntitlement::firstOrNew();
                $pastDueSince = $status === 'past_due' ? ($entitlement->past_due_since ?? now()->toImmutable()) : null;
                $grace = $pastDueSince?->addDays(7);
                $paid = $tier !== 'none' && (in_array($status, ['active', 'trialing'], true) || ($status === 'past_due' && $grace->isFuture()));
                $chairLimit = $tier === 'pro' ? 5 : ($tier === 'starter' ? 1 : 0);
                $practitionerLimit = $tier === 'pro' ? null : ($tier === 'starter' ? 2 : 0);
                $over = DB::table('operatories')->where('tenant_id', $tenant->id)->where('active', true)->count() > $chairLimit || ($practitionerLimit !== null && DB::table('practitioners')->where('tenant_id', $tenant->id)->where('active', true)->count() > $practitionerLimit);
                $entitlement->fill(['tier' => $tier, 'bookings_enabled' => $paid && ! $over, 'chair_limit' => $chairLimit, 'practitioner_limit' => $practitionerLimit, 'past_due_since' => $pastDueSince, 'grace_until' => $grace, 'over_limit' => $over, 'reconciled_at' => now()])->save();
                $locked->update(['billing_status' => $status]);
            }, 3);
        });
    }
}
