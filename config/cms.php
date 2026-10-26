<?php

return [
    'url' => env('CMS_URL', 'http://127.0.0.1:8055'),
    'admin_url' => env('CMS_ADMIN_URL', 'http://127.0.0.1:8055/admin'),
    'tokens' => json_decode(env('CMS_TOKENS_JSON', '{}'), true) ?: [],
    'timeout' => 4,
];
