<?php

namespace Tests\Feature;

use App\Billing\BillingCheckoutService;
use App\Billing\BillingReconciler;
use App\Billing\EntitlementService;
use App\Billing\StripeWebhookInbox;
use App\Billing\SubscriptionProvider;
use App\Billing\TenantSubscription;
use App\Billing\TenantSubscriptionItem;
use App\Models\BillingEntitlement;
use App\Models\Operatory;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Tenancy\MissingTenantContext;
use App\Tenancy\TenantRunner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Exception\SignatureVerificationException;
use Tests\Fixtures\SyntheticStripeClient;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Synthetic clinic', 'subdomain' => 'billing-test', 'timezone' => 'Asia/Manila', 'currency' => 'PHP', 'lifecycle_status' => 'active', 'stripe_id' => 'cus_test']);
    }

    private function receive(string $eventId, string $customer = 'cus_test'): WebhookEvent
    {
        config(['billing.webhook_secret' => 'whsec_test']);
        $raw = json_encode(['id' => $eventId, 'object' => 'event', 'type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_test', 'customer' => $customer]], 'created' => 1]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "$timestamp.$raw", 'whsec_test');

        return app(StripeWebhookInbox::class)->receive($raw, "t=$timestamp,v1=$signature");
    }

    public function test_live_checkout_and_portal_are_disabled_without_explicit_configuration(): void
    {
        $tenant = $this->tenant();
        config(['smilesuite.live_billing' => false]);
        $this->expectException(ValidationException::class);
        app(BillingCheckoutService::class)->checkout($tenant, 'starter', 'monthly', 'https://billing-test.example/success', 'https://billing-test.example/cancel');
    }

    public function test_verified_inbox_is_durable_deduplicated_and_unknown_customers_are_quarantined(): void
    {
        $this->tenant();
        $first = $this->receive('evt_same');
        $this->assertSame($first->id, $this->receive('evt_same')->id);
        $this->assertSame('pending', $first->state);
        $this->assertSame('quarantined', $this->receive('evt_unknown', 'cus_unknown')->state);
        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_invalid_signature_never_enters_inbox(): void
    {
        config(['billing.webhook_secret' => 'whsec_test']);
        try {
            app(StripeWebhookInbox::class)->receive('{}', 't=1,v1=invalid');
            $this->fail('Invalid signature accepted.');
        } catch (SignatureVerificationException) {
            $this->assertDatabaseCount('webhook_events', 0);
        }
    }

    public function test_resource_limit_is_atomic_and_disabled_entitlements_fail_closed(): void
    {
        $tenant = $this->tenant();
        app(TenantRunner::class)->run($tenant, function (): void {
            BillingEntitlement::create(['tier' => 'starter', 'bookings_enabled' => true, 'chair_limit' => 1, 'practitioner_limit' => 2]);
            app(EntitlementService::class)->mutateResource('chair', fn () => Operatory::create(['name' => 'Synthetic chair', 'active' => true]));
            try {
                app(EntitlementService::class)->mutateResource('chair', fn () => Operatory::create(['name' => 'Extra chair', 'active' => true]));
                $this->fail('Resource cap was bypassed');
            } catch (ValidationException) {
            }
            $this->assertSame(1, Operatory::count());
            BillingEntitlement::first()->update(['bookings_enabled' => false]);
            $this->assertFalse(app(EntitlementService::class)->allowsNewBookings());
        });
    }

    public function test_integration_resource_tenant_ownership_is_immutable_in_database(): void
    {
        $tenant = $this->tenant();
        $other = Tenant::create(['name' => 'Other synthetic', 'subdomain' => 'other-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, fn () => BillingEntitlement::create(['tier' => 'starter', 'bookings_enabled' => true, 'chair_limit' => 1, 'practitioner_limit' => 2]));
        $this->expectException(QueryException::class);
        DB::table('billing_entitlements')->where('tenant_id', $tenant->id)->update(['tenant_id' => $other->id]);
    }

    public function test_persisted_grace_expires_locally_without_provider_reconciliation(): void
    {
        $tenant = $this->tenant();
        $this->freezeTime();
        app(TenantRunner::class)->run($tenant, function (): void {
            $entitlement = BillingEntitlement::create(['tier' => 'pro', 'bookings_enabled' => true, 'chair_limit' => 5, 'past_due_since' => now()->subDays(6), 'grace_until' => now()->addDay()]);
            $service = app(EntitlementService::class);
            $this->assertTrue($service->allowsNewBookings());
            $service->mutateResource('chair', fn () => Operatory::create(['name' => 'Grace-period chair', 'active' => true]));
            $this->travel(1)->days();
            $this->assertFalse($service->allowsNewBookings());
            try {
                $service->mutateResource('chair', fn () => Operatory::create(['name' => 'Expired chair', 'active' => true]));
                $this->fail('Expired billing grace allowed resource creation.');
            } catch (ValidationException) {
                $this->assertSame(1, Operatory::count());
            }
            $this->assertTrue($entitlement->fresh()->bookings_enabled);
            $entitlement->update(['past_due_since' => now()->subDays(8), 'grace_until' => now()->addDay()]);
            $this->assertFalse($service->allowsNewBookings());
            $entitlement->update(['grace_until' => null]);
            $this->assertFalse($service->allowsNewBookings());
        });
    }

    public function test_cashier_subscription_queries_require_a_tenant_context(): void
    {
        $this->expectException(MissingTenantContext::class);
        TenantSubscription::query()->get();
    }

    public function test_cashier_subscription_item_queries_require_a_tenant_context(): void
    {
        $this->expectException(MissingTenantContext::class);
        TenantSubscriptionItem::query()->get();
    }

    public function test_checkout_retries_reuse_the_durable_provider_session(): void
    {
        $tenant = $this->tenant();
        config(['smilesuite.live_billing' => true, 'cashier.secret' => 'sk_test_synthetic', 'billing.prices.starter.monthly' => 'price_starter']);
        $http = new SyntheticStripeClient;
        ApiRequestor::setHttpClient($http);
        try {
            $billing = app(BillingCheckoutService::class);
            $first = $billing->checkout($tenant, 'starter', 'monthly', 'https://example.test/success', 'https://example.test/cancel');
            $second = $billing->checkout($tenant, 'starter', 'monthly', 'https://example.test/success', 'https://example.test/cancel');
            $this->assertSame($first, $second);
            $this->assertSame(1, $http->checkoutsCreated);
            $this->assertDatabaseCount('billing_checkout_sessions', 1);
        } finally {
            ApiRequestor::setHttpClient(null);
        }
    }

    public function test_checkout_checks_provider_for_existing_subscription_before_creating_another(): void
    {
        $tenant = $this->tenant();
        config(['smilesuite.live_billing' => true, 'cashier.secret' => 'sk_test_synthetic', 'billing.prices.starter.monthly' => 'price_starter']);
        $http = new SyntheticStripeClient;
        $http->existingSubscription = true;
        ApiRequestor::setHttpClient($http);
        try {
            try {
                app(BillingCheckoutService::class)->checkout($tenant, 'starter', 'monthly', 'https://example.test/success', 'https://example.test/cancel');
                $this->fail('Duplicate subscription checkout created.');
            } catch (ValidationException) {
            }
            $this->assertSame(0, $http->checkoutsCreated);
        } finally {
            ApiRequestor::setHttpClient(null);
        }
    }

    public function test_failed_processing_can_retry_and_reordered_events_reconcile_current_provider_state(): void
    {
        $tenant = $this->tenant();
        config(['billing.prices.pro.monthly' => 'price_pro']);
        $provider = new class implements SubscriptionProvider
        {
            public bool $fail = true;

            public string $status = 'active';

            public function current(Tenant $tenant): ?array
            {
                if ($this->fail) {
                    throw new \RuntimeException('Synthetic provider failure');
                }

                return ['id' => 'sub_test', 'status' => $this->status, 'items' => [['id' => 'si_test', 'price' => 'price_pro', 'product' => 'prod_test', 'quantity' => 1]], 'trial_end' => null, 'cancel_at' => null];
            }
        };
        $this->app->instance(SubscriptionProvider::class, $provider);
        $event = $this->receive('evt_new');
        try {
            app(BillingReconciler::class)->process($event);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $event->fresh()->state);
        $provider->fail = false;
        app(BillingReconciler::class)->process($event->fresh());
        app(BillingReconciler::class)->process($this->receive('evt_old'));
        app(TenantRunner::class)->run($tenant, function (): void {
            $entitlement = BillingEntitlement::firstOrFail();
            $this->assertTrue($entitlement->bookings_enabled);
            $this->assertSame(5, $entitlement->chair_limit);
            $this->assertNull($entitlement->practitioner_limit);
        });
        $provider->status = 'past_due';
        app(BillingReconciler::class)->reconcile($tenant);
        $originalPastDue = DB::table('billing_entitlements')->value('past_due_since');
        $originalGrace = DB::table('billing_entitlements')->value('grace_until');
        $this->travel(8)->days();
        $provider->fail = true;
        try {
            app(BillingReconciler::class)->reconcile($tenant);
            $this->fail('Expected provider outage.');
        } catch (\RuntimeException) {
        }
        $this->assertSame($originalPastDue, DB::table('billing_entitlements')->value('past_due_since'));
        $this->assertSame($originalGrace, DB::table('billing_entitlements')->value('grace_until'));
        app(TenantRunner::class)->run($tenant, function (): void {
            $this->assertTrue(BillingEntitlement::firstOrFail()->bookings_enabled);
            $this->assertFalse(app(EntitlementService::class)->allowsNewBookings());
        });
        $provider->fail = false;
        app(BillingReconciler::class)->reconcile($tenant);
        app(TenantRunner::class)->run($tenant, function (): void {
            $this->assertFalse(BillingEntitlement::firstOrFail()->bookings_enabled);
        });
        $this->assertDatabaseCount('tenants', 1);
        $this->assertSame(1, DB::table('subscriptions')->count());
    }
}
