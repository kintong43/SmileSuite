<?php

namespace App\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;

class BillingCheckoutService
{
    private function enabled(): void
    {
        if (! config('smilesuite.live_billing', false) || ! config('cashier.secret')) {
            throw ValidationException::withMessages(['billing' => 'Live subscription billing is disabled.']);
        }
    }

    public function checkout(Tenant $tenant, string $tier, string $interval, string $successUrl, string $cancelUrl): string
    {
        $this->enabled();
        if (! in_array($tier, ['starter', 'pro'], true) || ! in_array($interval, ['monthly', 'annual'], true) || ! ($price = config("billing.prices.$tier.$interval"))) {
            throw ValidationException::withMessages(['tier' => 'An approved subscription price must be configured first.']);
        }
        // Persist a stable operation generation before making any provider mutation.
        $checkout = DB::transaction(function () use ($tenant, $price, $successUrl, $cancelUrl): object {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (DB::table('subscriptions')->where('tenant_id', $tenant->id)->whereIn('stripe_status', ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'])->exists()) {
                throw ValidationException::withMessages(['billing' => 'Manage your existing subscription in the billing portal.']);
            }
            $row = DB::table('billing_checkout_sessions')->where('tenant_id', $tenant->id)->first();
            if ($row && (! $row->expires_at || now()->lt($row->expires_at))) {
                if ($row->price_id !== $price) {
                    throw ValidationException::withMessages(['billing' => 'A checkout is already pending. Complete or let it expire before changing plans.']);
                }

                return $row;
            }
            $values = ['generation' => ($row->generation ?? 0) + 1, 'price_id' => $price, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl, 'provider_session_id' => null, 'checkout_url' => null, 'expires_at' => null, 'updated_at' => now()];
            DB::table('billing_checkout_sessions')->updateOrInsert(['tenant_id' => $tenant->id], $values + ['created_at' => now()]);

            return DB::table('billing_checkout_sessions')->where('tenant_id', $tenant->id)->first();
        }, 3);

        return DB::transaction(function () use ($tenant, $checkout): string {
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $current = DB::table('billing_checkout_sessions')->where('tenant_id', $tenant->id)->first();
            if ($current->generation !== $checkout->generation) {
                throw ValidationException::withMessages(['billing' => 'Checkout state changed. Please refresh.']);
            }
            if ($current->checkout_url) {
                return Crypt::decryptString($current->checkout_url);
            }
            $locked->createOrGetStripeCustomer(['name' => $locked->name, 'metadata' => ['tenant_reference' => (string) $locked->id]], ['idempotency_key' => 'tenant-customer:'.$locked->id]);
            $remote = app(SubscriptionProvider::class)->current($locked);
            if ($remote && ! in_array($remote['status'], ['canceled', 'incomplete_expired'], true)) {
                throw ValidationException::withMessages(['billing' => 'An existing subscription was found. Use the billing portal while reconciliation updates the account.']);
            }
            $session = Cashier::stripe()->checkout->sessions->create(['mode' => 'subscription', 'customer' => $locked->stripe_id, 'line_items' => [['price' => $current->price_id, 'quantity' => 1]], 'success_url' => $current->success_url, 'cancel_url' => $current->cancel_url, 'client_reference_id' => (string) $locked->id], ['idempotency_key' => 'tenant-checkout:'.$locked->id.':'.$current->generation]);
            DB::table('billing_checkout_sessions')->where('tenant_id', $locked->id)->update(['provider_session_id' => $session->id, 'checkout_url' => Crypt::encryptString($session->url), 'expires_at' => date('Y-m-d H:i:sP', $session->expires_at), 'updated_at' => now()]);

            return $session->url;
        });
    }

    public function portal(Tenant $tenant, string $returnUrl): string
    {
        $this->enabled();
        if (! $tenant->stripe_id) {
            throw ValidationException::withMessages(['billing' => 'No subscription customer is connected.']);
        }

        // Downgrades are deliberately not offered through app code until resource checks can
        // be coupled to a period-end provider schedule. Disable portal plan switching in Stripe.
        return $tenant->billingPortalUrl($returnUrl);
    }
}
