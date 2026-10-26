<?php

namespace App\Models;

class NotificationDelivery extends TenantModel
{
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
