<?php

return [
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'grace_days' => 7,
    'prices' => [
        'starter' => ['monthly' => env('STRIPE_STARTER_MONTHLY_PRICE'), 'annual' => env('STRIPE_STARTER_ANNUAL_PRICE')],
        'pro' => ['monthly' => env('STRIPE_PRO_MONTHLY_PRICE'), 'annual' => env('STRIPE_PRO_ANNUAL_PRICE')],
    ],
];
