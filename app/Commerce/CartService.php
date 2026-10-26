<?php

namespace App\Commerce;

use App\Models\ShopifyConnection;
use App\Models\ShoppingCart;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    private const FIELDS = 'id checkoutUrl totalQuantity cost { totalAmount { amount currencyCode } } lines(first:100) { nodes { id quantity merchandise { ... on ProductVariant { id title product { title } price { amount currencyCode } } } } }';

    public function __construct(private ShopifyClient $client, private TenantContext $context) {}

    public function add(string $sessionId, string $variantId, int $quantity, string $buyerIp): array
    {
        if (! preg_match('~\Agid://shopify/ProductVariant/[0-9]+\z~', $variantId)) {
            throw ValidationException::withMessages(['variant_id' => 'Select a store product variant.']);
        }
        $this->quantity($quantity);

        return $this->mutate($sessionId, $buyerIp, function ($cart) use ($variantId, $quantity): array {
            $lines = [['merchandiseId' => $variantId, 'quantity' => $quantity]];

            return $cart ? ['cartLinesAdd', 'mutation Add($cartId: ID!, $lines: [CartLineInput!]!) { cartLinesAdd(cartId:$cartId,lines:$lines) { cart { '.self::FIELDS.' } userErrors { code message } } }', ['cartId' => $cart->shopify_cart_id, 'lines' => $lines]] : ['cartCreate', 'mutation Create($input: CartInput!) { cartCreate(input:$input) { cart { '.self::FIELDS.' } userErrors { code message } } }', ['input' => ['lines' => $lines]]];
        });
    }

    public function update(string $sessionId, string $lineId, int $quantity, string $buyerIp): array
    {
        $this->quantity($quantity);
        $this->lineId($lineId);

        return $this->mutate($sessionId, $buyerIp, fn ($cart) => ['cartLinesUpdate', 'mutation Update($cartId: ID!, $lines: [CartLineUpdateInput!]!) { cartLinesUpdate(cartId:$cartId,lines:$lines) { cart { '.self::FIELDS.' } userErrors { code message } } }', ['cartId' => $cart?->shopify_cart_id ?? throw new CommerceUnavailable('Your cart has expired.'), 'lines' => [['id' => $lineId, 'quantity' => $quantity]]]]);
    }

    public function remove(string $sessionId, string $lineId, string $buyerIp): array
    {
        $this->lineId($lineId);

        return $this->mutate($sessionId, $buyerIp, fn ($cart) => ['cartLinesRemove', 'mutation Remove($cartId: ID!, $lineIds: [ID!]!) { cartLinesRemove(cartId:$cartId,lineIds:$lineIds) { cart { '.self::FIELDS.' } userErrors { code message } } }', ['cartId' => $cart?->shopify_cart_id ?? throw new CommerceUnavailable('Your cart has expired.'), 'lineIds' => [$lineId]]]);
    }

    public function get(string $sessionId, string $buyerIp): ?array
    {
        $connection = $this->client->connection();
        $cart = ShoppingCart::where('session_hash', $this->sessionHash($sessionId))->where('connection_generation', $connection->generation)->first();
        if (! $cart) {
            return null;
        }
        $data = $this->client->request($connection, 'query Cart($id:ID!) { cart(id:$id) { '.self::FIELDS.' } }', ['id' => $cart->shopify_cart_id], $buyerIp);
        if (! is_array($data['cart'] ?? null)) {
            $cart->delete();
            throw new CommerceUnavailable('Your store cart has expired. Please start a new cart.');
        }
        $this->validateCheckoutUrl($data['cart']['checkoutUrl'] ?? '', $connection);
        unset($data['cart']['id']);

        return $data['cart'];
    }

    public function checkoutUrl(string $sessionId, string $buyerIp): string
    {
        $cart = $this->get($sessionId, $buyerIp);
        $url = $cart['checkoutUrl'] ?? '';

        return $this->validateCheckoutUrl($url, $this->client->connection());
    }

    private function validateCheckoutUrl(string $url, ShopifyConnection $connection): string
    {
        $parts = parse_url($url);
        $allowed = [$connection->shop_host, $connection->primary_domain, 'checkout.shopify.com'];
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($parts['host'] ?? '', $allowed, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new CommerceUnavailable('The store returned an invalid checkout destination.');
        }

        return $url;
    }

    private function mutate(string $sessionId, string $buyerIp, \Closure $operation): array
    {
        $sessionHash = $this->sessionHash($sessionId);

        // Never retry a mutation after a timeout: the provider may already have applied it.
        return DB::transaction(function () use ($sessionHash, $buyerIp, $operation): array {
            Tenant::whereKey($this->context->id())->lockForUpdate()->firstOrFail();
            $connection = $this->client->connection();
            $cart = ShoppingCart::where('session_hash', $sessionHash)->where('connection_generation', $connection->generation)->first();
            [$field, $query, $variables] = $operation($cart);
            $data = $this->client->request($connection, $query, $variables, $buyerIp);
            $result = $data[$field] ?? [];
            if (! empty($result['userErrors']) || ! is_array($result['cart'] ?? null) || ! is_string($result['cart']['id'] ?? null)) {
                throw new CommerceUnavailable('The store could not update your cart. Check availability and try again.');
            }
            $this->validateCheckoutUrl($result['cart']['checkoutUrl'] ?? '', $connection);
            ShoppingCart::updateOrCreate(['session_hash' => $sessionHash], ['connection_generation' => $connection->generation, 'shopify_cart_id' => $result['cart']['id']]);
            unset($result['cart']['id']);

            return $result['cart'];
        });
    }

    private function sessionHash(string $sessionId): string
    {
        if ($sessionId === '' || strlen($sessionId) > 512) {
            throw new CommerceUnavailable('A valid browser session is required.');
        }

        return hash_hmac('sha256', $sessionId, config('app.key'));
    }

    private function quantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 100) {
            throw ValidationException::withMessages(['quantity' => 'Choose a quantity from 1 to 100.']);
        }
    }

    private function lineId(string $lineId): void
    {
        if (! preg_match('~\Agid://shopify/CartLine/[a-zA-Z0-9?=&_-]+\z~', $lineId)) {
            throw ValidationException::withMessages(['line_id' => 'Choose a valid cart line.']);
        }
    }
}
