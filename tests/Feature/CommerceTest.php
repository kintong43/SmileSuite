<?php

namespace Tests\Feature;

use App\Commerce\CartService;
use App\Commerce\CatalogService;
use App\Commerce\CommerceUnavailable;
use App\Commerce\ShopifyConnectionService;
use App\Commerce\ShopifyHost;
use App\Models\AuditEvent;
use App\Models\ShoppingCart;
use App\Models\Tenant;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_canonical_shop_hostnames_are_accepted(): void
    {
        $this->assertSame('clinic-demo.myshopify.com', ShopifyHost::validate('clinic-demo.myshopify.com'));
        foreach (['https://x.myshopify.com', 'x.myshopify.com:443', 'x.y.myshopify.com', '127.0.0.1', 'x.myshopify.com/secret', 'user@x.myshopify.com', '-x.myshopify.com', 'x.myshopify.com.'] as $host) {
            try {
                ShopifyHost::validate($host);
                $this->fail("Accepted invalid host: $host");
            } catch (ValidationException) {
            }
        }
    }

    public function test_connection_encrypts_secret_and_replacement_invalidates_session_carts(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fake(['*' => Http::response(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1', 'name' => 'Synthetic shop', 'primaryDomain' => ['host' => 'clinic-demo.myshopify.com']]]])]);
        $tenant = Tenant::create(['name' => 'Synthetic commerce', 'subdomain' => 'commerce-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            $connection = app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'secret-private-token', '203.0.113.10');
            $this->assertStringNotContainsString('secret-private-token', DB::table('shopify_connections')->value('private_token'));
            ShoppingCart::create(['connection_generation' => 1, 'session_hash' => hash('sha256', 'session'), 'shopify_cart_id' => 'gid://shopify/Cart/test']);
            $replacement = app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'replacement-token', '203.0.113.10');
            $this->assertSame(2, $replacement->generation);
            $this->assertSame(0, ShoppingCart::count());
            $this->assertArrayNotHasKey('private_token', $connection->toArray());
            $this->assertSame(2, AuditEvent::where('action', 'commerce.connected')->count());
        });
    }

    public function test_provider_redirects_are_rejected(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fakeSequence()->push('', 302, ['Location' => 'https://127.0.0.1/private']);
        $tenant = Tenant::create(['name' => 'Synthetic commerce', 'subdomain' => 'commerce-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            $this->expectException(CommerceUnavailable::class);
            app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'private-token', '203.0.113.10');
        });
    }

    public function test_one_store_cannot_be_linked_to_two_tenants(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fake(['*' => Http::response(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1']]])]);
        $first = Tenant::create(['name' => 'First synthetic', 'subdomain' => 'commerce-first', 'timezone' => 'UTC', 'currency' => 'PHP']);
        $second = Tenant::create(['name' => 'Second synthetic', 'subdomain' => 'commerce-second', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($first, fn () => app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'first-private-token', '203.0.113.10'));
        $this->expectException(ValidationException::class);
        app(TenantRunner::class)->run($second, fn () => app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'second-private-token', '203.0.113.10'));
    }

    public function test_unverified_checkout_destination_is_rejected_before_exposing_cart(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fakeSequence()->push(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1']]])->push(['data' => ['cartCreate' => ['cart' => ['id' => 'gid://shopify/Cart/1', 'checkoutUrl' => 'https://attacker.example/checkout'], 'userErrors' => []]]]);
        $tenant = Tenant::create(['name' => 'Synthetic commerce', 'subdomain' => 'commerce-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'private-token', '203.0.113.10');
            $this->expectException(CommerceUnavailable::class);
            app(CartService::class)->add('session-one', 'gid://shopify/ProductVariant/1', 1, '203.0.113.10');
        });
    }

    public function test_demo_catalog_is_local_synthetic_and_restricted_to_two_seed_clinics(): void
    {
        config(['smilesuite.live_commerce' => false, 'smilesuite.sandbox' => true]);
        Http::preventStrayRequests();
        $tenant = Tenant::create(['name' => 'Harbor synthetic', 'subdomain' => 'harbor', 'timezone' => 'Asia/Manila', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            $catalog = app(CatalogService::class)->products('203.0.113.10');
            $this->assertTrue($catalog['demo']);
            $this->assertFalse($catalog['nodes'][0]['variants']['nodes'][0]['availableForSale']);
        });
        $tenant->update(['subdomain' => 'unseeded']);
        $this->expectException(CommerceUnavailable::class);
        app(TenantRunner::class)->run($tenant, fn () => app(CatalogService::class)->products('203.0.113.10'));
        Http::assertNothingSent();
    }

    public function test_cart_uses_variant_only_and_provider_checkout_url_and_rejects_cross_session(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fakeSequence()
            ->push(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1', 'name' => 'Synthetic']]])
            ->push(['data' => ['cartCreate' => ['cart' => ['id' => 'gid://shopify/Cart/1?key=secret', 'checkoutUrl' => 'https://clinic-demo.myshopify.com/checkouts/provider-token', 'lines' => ['nodes' => []]], 'userErrors' => []]]])
            ->push(['data' => ['cart' => ['id' => 'gid://shopify/Cart/1?key=secret', 'checkoutUrl' => 'https://clinic-demo.myshopify.com/checkouts/provider-token', 'lines' => ['nodes' => []]]]]);
        $tenant = Tenant::create(['name' => 'Synthetic commerce', 'subdomain' => 'commerce-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'private-token', '203.0.113.10');
            app(CartService::class)->add('session-one', 'gid://shopify/ProductVariant/1', 2, '203.0.113.10');
            $this->assertNull(app(CartService::class)->get('different-session', '203.0.113.10'));
            $this->assertSame('https://clinic-demo.myshopify.com/checkouts/provider-token', app(CartService::class)->checkoutUrl('session-one', '203.0.113.10'));
        });
        Http::assertSent(fn ($request) => isset($request['variables']['input']['lines'][0]['merchandiseId']) && ! isset($request['variables']['input']['attributes']));
    }

    public function test_graphql_user_errors_do_not_store_a_cart(): void
    {
        config(['smilesuite.live_commerce' => true]);
        Http::fakeSequence()->push(['data' => ['shop' => ['id' => 'gid://shopify/Shop/1']]])->push(['data' => ['cartCreate' => ['cart' => null, 'userErrors' => [['message' => 'Out of stock']]]]]);
        $tenant = Tenant::create(['name' => 'Synthetic commerce', 'subdomain' => 'commerce-test', 'timezone' => 'UTC', 'currency' => 'PHP']);
        app(TenantRunner::class)->run($tenant, function (): void {
            app(ShopifyConnectionService::class)->connect('clinic-demo.myshopify.com', 'private-token', '203.0.113.10');
            try {
                app(CartService::class)->add('session-one', 'gid://shopify/ProductVariant/1', 2, '203.0.113.10');
                $this->fail('Expected unavailable stock error');
            } catch (CommerceUnavailable) {
                $this->assertSame(0, ShoppingCart::count());
            }
        });
    }
}
