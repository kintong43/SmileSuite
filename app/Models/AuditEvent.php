<?php

namespace App\Models;

class AuditEvent extends TenantModel
{
    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
