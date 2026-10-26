<?php

namespace Tests\Fixtures;

use Stripe\HttpClient\ClientInterface;

/** Offline wire-level fixture: no Stripe network or charges are possible. */
class SyntheticStripeClient implements ClientInterface
{
    public int $checkoutsCreated = 0;

    public bool $existingSubscription = false;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if (str_ends_with($absUrl, '/customers/cus_test')) {
            $body = ['id' => 'cus_test', 'object' => 'customer'];
        } elseif (str_ends_with($absUrl, '/subscriptions')) {
            $body = ['object' => 'list', 'has_more' => false, 'data' => $this->existingSubscription ? [['id' => 'sub_existing', 'object' => 'subscription', 'status' => 'active', 'trial_end' => null, 'cancel_at' => null, 'items' => ['object' => 'list', 'data' => [['id' => 'si_existing', 'object' => 'subscription_item', 'quantity' => 1, 'price' => ['id' => 'price_starter', 'object' => 'price', 'product' => 'prod_test']]]]]] : []];
        } elseif (str_ends_with($absUrl, '/checkout/sessions')) {
            $this->checkoutsCreated++;
            $body = ['id' => 'cs_synthetic', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/synthetic', 'expires_at' => time() + 3600];
        } else {
            throw new \RuntimeException('Unexpected synthetic Stripe endpoint.');
        }

        return [json_encode($body), 200, []];
    }
}
