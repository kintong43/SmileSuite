<?php

namespace App\Models;

class AppointmentEvent extends TenantModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
