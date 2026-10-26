<?php

namespace App\Commerce;

use App\Models\ShoppingCart;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

class CatalogService
{
    public function __construct(private ShopifyClient $client, private TenantContext $context) {}

    public function products(string $buyerIp, string $locale = 'en', ?string $cursor = null): array
    {
        if (config('smilesuite.sandbox') && app()->environment(['local', 'testing']) && in_array($this->context->tenant->subdomain, ['harbor', 'cedar'], true) && ! config('smilesuite.live_commerce')) {
            return DemoCatalog::products($this->context->tenant->subdomain);
        }
        $connection = $this->client->connection();
        $locale = in_array($locale, ['en', 'fil'], true) ? $locale : 'en';
        $key = implode(':', ['catalog', $this->context->id(), $connection->generation, ShopifyClient::VERSION, $this->context->tenant->currency, $locale, hash('sha256', $cursor ?? '')]);

        return Cache::remember($key, 1800, function () use ($connection, $buyerIp, $cursor): array {
            $data = $this->client->request($connection, 'query Catalog($cursor: String) { products(first: 20, after: $cursor) { nodes { id title description featuredImage { url altText } variants(first: 20) { nodes { id title availableForSale price { amount currencyCode } } } } pageInfo { hasNextPage endCursor } } }', ['cursor' => $cursor], $buyerIp);
            if (! is_array($data['products'] ?? null)) {
                throw new CommerceUnavailable('The store catalog is unavailable.');
            }

            return $data['products'];
        });
    }

    public function refresh(): void
    {
        $connection = $this->client->connection();
        $connection->increment('generation');
        ShoppingCart::query()->delete();
    }
}
