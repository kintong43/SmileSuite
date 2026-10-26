<?php

namespace App\Models;

class CareAssignment extends TenantModel
{
    protected function casts(): array
    {
        return ['valid_from' => 'immutable_datetime', 'valid_to' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
