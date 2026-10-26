<?php

namespace App\Commerce;

use App\Models\ShopifyConnection;
use Illuminate\Support\Facades\Http;

class ShopifyClient
{
    public const VERSION = '2026-10';

    public function request(ShopifyConnection $connection, string $query, array $variables, string $buyerIp): array
    {
        if (! config('smilesuite.live_commerce', false)) {
            throw new CommerceUnavailable('Live commerce is disabled.');
        }
        $host = ShopifyHost::validate($connection->shop_host);
        if (! filter_var($buyerIp, FILTER_VALIDATE_IP)) {
            throw new CommerceUnavailable('Buyer address unavailable.');
        }
        try {
            $response = Http::withoutRedirecting()->connectTimeout(3)->timeout(8)->withHeaders([
                'Shopify-Storefront-Private-Token' => $connection->private_token,
                'Shopify-Storefront-Buyer-IP' => $buyerIp,
                'Content-Type' => 'application/json',
            ])->post("https://$host/api/".self::VERSION.'/graphql.json', ['query' => $query, 'variables' => $variables]);
        } catch (\Throwable) {
            throw new CommerceUnavailable('The clinic store is temporarily unavailable.');
        }
        if (in_array($response->status(), [401, 403], true)) {
            if ($connection->exists) {
                $connection->update(['status' => 'reconnect_required']);
            }
            throw new CommerceUnavailable('The clinic store needs to be reconnected.');
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! empty($body['errors']) || ! isset($body['data'])) {
            throw new CommerceUnavailable('The clinic store is temporarily unavailable.');
        }

        return $body['data'];
    }

    public function connection(): ShopifyConnection
    {
        $connection = ShopifyConnection::where('status', 'active')->first();
        if (! $connection) {
            throw new CommerceUnavailable('The clinic store is not connected.');
        }

        return $connection;
    }
}
