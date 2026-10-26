<?php

namespace App\Models;

class ScheduleBlock extends TenantModel
{
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
