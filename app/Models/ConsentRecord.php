<?php

namespace App\Models;

class ConsentRecord extends TenantModel
{
    protected function casts(): array
    {
        return ['granted_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime'];
    }
}
