<?php

namespace App\Models;

class BillingEntitlement extends TenantModel
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['bookings_enabled' => 'boolean', 'over_limit' => 'boolean', 'chair_limit' => 'integer', 'practitioner_limit' => 'integer', 'past_due_since' => 'immutable_datetime', 'grace_until' => 'immutable_datetime', 'reconciled_at' => 'immutable_datetime'];
    }
}
