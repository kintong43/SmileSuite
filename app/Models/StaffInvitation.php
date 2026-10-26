<?php

namespace App\Models;

class StaffInvitation extends TenantModel
{
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
