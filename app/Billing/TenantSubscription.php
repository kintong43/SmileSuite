<?php

namespace App\Billing;

use App\Tenancy\BelongsToTenant;
use Laravel\Cashier\Subscription;

class TenantSubscription extends Subscription
{
    use BelongsToTenant;

    protected $table = 'subscriptions';

    public function getForeignKey(): string
    {
        return 'subscription_id';
    }
}
