<?php

namespace App\Models;

class ClinicHour extends TenantModel
{
    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
