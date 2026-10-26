<?php

namespace App\Commerce;

use App\Models\AuditEvent;
use App\Models\ShopifyConnection;
use App\Models\ShoppingCart;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopifyConnectionService
{
    public function __construct(private ShopifyClient $client, private TenantContext $context) {}

    public function connect(string $host, string $token, string $buyerIp): ShopifyConnection
    {
        $host = ShopifyHost::validate($host);
        if (strlen($token) < 8 || strlen($token) > 4096 || preg_match('/[\r\n]/', $token)) {
            throw ValidationException::withMessages(['token' => 'Enter a valid private Storefront token.']);
        }
        $candidate = new ShopifyConnection(['shop_host' => $host, 'private_token' => $token]);
        $data = $this->client->request($candidate, 'query VerifyShop { shop { id name primaryDomain { host } } }', [], $buyerIp);
        if (! is_string($data['shop']['id'] ?? null) || ! str_starts_with($data['shop']['id'], 'gid://shopify/Shop/')) {
            throw new CommerceUnavailable('The store identity could not be verified.');
        }

        return DB::transaction(function () use ($host, $token, $data): ShopifyConnection {
            Tenant::whereKey($this->context->id())->lockForUpdate()->firstOrFail();
            if (DB::table('shopify_connections')->where('shop_host', $host)->where('tenant_id', '<>', $this->context->id())->exists()) {
                throw ValidationException::withMessages(['shop_host' => 'This store is already associated with another clinic.']);
            }
            $connection = ShopifyConnection::firstOrNew();
            $generation = $connection->exists ? $connection->generation + 1 : 1;
            $connection->fill(['shop_host' => $host, 'shop_id' => $data['shop']['id'], 'private_token' => $token, 'status' => 'active', 'generation' => $generation, 'verified_at' => now(), 'primary_domain' => $data['shop']['primaryDomain']['host'] ?? $host])->save();
            ShoppingCart::query()->delete();
            AuditEvent::create(['actor_id' => auth()->id(), 'action' => 'commerce.connected', 'resource_type' => 'shopify_connection', 'resource_id' => $connection->id, 'metadata' => ['generation' => $generation], 'occurred_at' => now()]);

            return $connection;
        }, 3);
    }

    public function disconnect(): void
    {
        DB::transaction(function (): void {
            Tenant::whereKey($this->context->id())->lockForUpdate()->firstOrFail();
            $connection = ShopifyConnection::first();
            if ($connection) {
                $connection->update(['status' => 'disconnected', 'private_token' => null, 'generation' => $connection->generation + 1]);
            }
            ShoppingCart::query()->delete();
            if ($connection) {
                AuditEvent::create(['actor_id' => auth()->id(), 'action' => 'commerce.disconnected', 'resource_type' => 'shopify_connection', 'resource_id' => $connection->id, 'metadata' => [], 'occurred_at' => now()]);
            }
        });
    }
}
