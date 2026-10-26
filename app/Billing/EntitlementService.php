<?php

namespace App\Billing;

use App\Models\BillingEntitlement;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementService
{
    public function __construct(private TenantContext $context) {}

    public function allowsNewBookings(): bool
    {
        $entitlement = BillingEntitlement::first();

        // A provider outage must not keep a persisted enabled flag valid beyond grace.
        // Honor an earlier explicit deadline and cap it at the original seven-day policy.
        return $this->context->tenant->lifecycle_status === 'active' && $entitlement?->bookings_enabled && ! $entitlement->over_limit
            && ($entitlement->grace_until === null || $entitlement->grace_until->isFuture())
            && ($entitlement->past_due_since === null || $entitlement->past_due_since->addDays(7)->isFuture());
    }

    /** Resource insertion must happen inside this callback to preserve the count check lock. */
    public function mutateResource(string $type, \Closure $callback): mixed
    {
        return DB::transaction(function () use ($type, $callback): mixed {
            Tenant::whereKey($this->context->id())->lockForUpdate()->firstOrFail();
            $this->assertCanAddResource($type);

            return $callback();
        }, 3);
    }

    public function assertCanAddResource(string $type): void
    {
        $entitlement = BillingEntitlement::first();
        if (! $this->allowsNewBookings()) {
            throw ValidationException::withMessages(['billing' => 'New resources are unavailable under the current subscription. Existing records remain available.']);
        }
        [$table, $limit] = match ($type) {
            'chair' => ['operatories', $entitlement->chair_limit],
            'practitioner' => ['practitioners', $entitlement->practitioner_limit],
            default => throw new \InvalidArgumentException('Unknown resource type.'),
        };
        if ($limit !== null && DB::table($table)->where('tenant_id', $this->context->id())->where('active', true)->count() >= $limit) {
            throw ValidationException::withMessages(['billing' => 'This subscription has reached its resource limit.']);
        }
    }

    public function assertDowngrade(string $tier): void
    {
        if (! in_array($tier, ['starter', 'pro'], true)) {
            throw new \InvalidArgumentException('Unknown tier.');
        }
        $chairs = $tier === 'pro' ? 5 : 1;
        $practitioners = $tier === 'pro' ? null : 2;
        if (DB::table('operatories')->where('tenant_id', $this->context->id())->where('active', true)->count() > $chairs || ($practitioners !== null && DB::table('practitioners')->where('tenant_id', $this->context->id())->where('active', true)->count() > $practitioners)) {
            throw ValidationException::withMessages(['tier' => 'Reduce active resources before scheduling this downgrade.']);
        }
    }
}
