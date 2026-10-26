<?php

namespace App\Models;

class ShopifyConnection extends TenantModel
{
    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['private_token'];

    protected function casts(): array
    {
        return ['private_token' => 'encrypted', 'generation' => 'integer', 'verified_at' => 'immutable_datetime'];
    }
}
