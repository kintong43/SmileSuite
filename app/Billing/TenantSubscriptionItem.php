<?php

namespace App\Billing;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\SubscriptionItem;

class TenantSubscriptionItem extends SubscriptionItem
{
    protected $table = 'subscription_items';

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = TenantContext::current()->id();
            $query->whereIn('subscription_items.subscription_id', DB::table('subscriptions')->select('id')->where('tenant_id', $tenantId));
        });
        static::saving(function (self $item): void {
            if (! DB::table('subscriptions')->where('id', $item->subscription_id)->where('tenant_id', TenantContext::current()->id())->exists() || ($item->exists && $item->isDirty('subscription_id'))) {
                throw new \LogicException('Subscription item ownership is immutable and tenant-bound.');
            }
        });
        static::deleting(function (self $item): void {
            if (! DB::table('subscriptions')->where('id', $item->subscription_id)->where('tenant_id', TenantContext::current()->id())->exists()) {
                throw new \LogicException('Cross-tenant subscription item deletion denied.');
            }
        });
    }

    public function getForeignKey(): string
    {
        return 'subscription_item_id';
    }
}
