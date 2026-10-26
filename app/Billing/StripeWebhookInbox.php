<?php

namespace App\Billing;

use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;

class StripeWebhookInbox
{
    public function receive(string $raw, string $signature): WebhookEvent
    {
        $secret = config('billing.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException('billing_webhook_not_configured');
        }
        $event = Webhook::constructEvent($raw, $signature, $secret, 300);
        if (strlen($raw) > 1048576) {
            throw new \InvalidArgumentException('event_too_large');
        }
        $expected = in_array($event->type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted', 'invoice.paid', 'invoice.payment_failed', 'checkout.session.completed'], true);
        $object = $event->data->object;
        $customer = is_string($object->customer ?? null) ? $object->customer : null;
        $tenantId = $customer ? Tenant::where('stripe_id', $customer)->value('id') : null;
        $subscriptionId = str_starts_with($event->type, 'customer.subscription.') ? $object->id : ($object->subscription ?? null);
        $mappedSubscriptionTenant = is_string($subscriptionId) ? DB::table('subscriptions')->where('stripe_id', $subscriptionId)->value('tenant_id') : null;
        $conflict = $mappedSubscriptionTenant && $customer && (! $tenantId || (int) $mappedSubscriptionTenant !== (int) $tenantId);
        $tenantId ??= $customer ? null : $mappedSubscriptionTenant;
        $state = ! $expected ? 'ignored' : ((! $tenantId || $conflict) ? 'quarantined' : 'pending');

        return WebhookEvent::firstOrCreate(['provider' => 'stripe', 'provider_event_id' => $event->id], ['event_type' => $event->type, 'tenant_id' => $conflict ? null : $tenantId, 'raw_payload' => $raw, 'state' => $state]);
    }
}
