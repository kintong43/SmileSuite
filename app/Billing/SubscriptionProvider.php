<?php

namespace App\Billing;

use App\Models\Tenant;

interface SubscriptionProvider
{
    /** Current authoritative subscription, never an event snapshot. */
    public function current(Tenant $tenant): ?array;
}
