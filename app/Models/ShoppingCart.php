<?php

namespace App\Models;

class ShoppingCart extends TenantModel
{
    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['shopify_cart_id', 'session_hash'];

    protected function casts(): array
    {
        return ['shopify_cart_id' => 'encrypted', 'connection_generation' => 'integer'];
    }
}
