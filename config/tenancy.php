<?php

return [
    'domain' => env('TENANCY_DOMAIN', 'smilesuite.localhost'),
    'platform_hosts' => array_filter(explode(',', env('PLATFORM_HOSTS', 'smilesuite.localhost,localhost,127.0.0.1,admin.smilesuite.localhost'))),
    'admin_host' => env('ADMIN_HOST', 'admin.smilesuite.localhost'),
];
