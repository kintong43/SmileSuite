<?php

namespace App\Commerce;

use Illuminate\Validation\ValidationException;

class ShopifyHost
{
    public static function validate(string $host): string
    {
        if (strlen($host) > 253 || ! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.myshopify\.com\z/D', $host)) {
            throw ValidationException::withMessages(['shop_host' => 'Enter the canonical store-name.myshopify.com hostname.']);
        }

        return $host;
    }
}
