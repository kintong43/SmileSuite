<?php

namespace App\Models;

class IdempotencyRequest extends TenantModel
{
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
