<?php

namespace App\Models;

class OutboxMessage extends TenantModel
{
    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'attempts' => 'integer', 'version' => 'integer'];
    }
}
