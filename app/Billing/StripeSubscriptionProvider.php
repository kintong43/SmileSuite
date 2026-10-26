<?php

namespace App\Billing;

use App\Models\Tenant;
use Laravel\Cashier\Cashier;

class StripeSubscriptionProvider implements SubscriptionProvider
{
    public function current(Tenant $tenant): ?array
    {
        if (! $tenant->stripe_id) {
            return null;
        }
        $subscriptions = Cashier::stripe()->subscriptions->all(['customer' => $tenant->stripe_id, 'status' => 'all', 'limit' => 100]);
        $usable = array_values(array_filter($subscriptions->data, fn ($subscription) => ! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)));
        if (count($usable) > 1 || $subscriptions->has_more) {
            throw new \RuntimeException('ambiguous_subscriptions');
        }
        $subscription = $usable[0] ?? $subscriptions->data[0] ?? null;
        if (! $subscription) {
            return null;
        }

        return ['id' => $subscription->id, 'status' => $subscription->status, 'trial_end' => $subscription->trial_end, 'cancel_at' => $subscription->cancel_at,
            'items' => array_map(fn ($item) => ['id' => $item->id, 'price' => $item->price->id, 'product' => $item->price->product, 'quantity' => $item->quantity], $subscription->items->data)];
    }
}
