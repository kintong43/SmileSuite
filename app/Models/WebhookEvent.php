<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Global inbox: tenant ownership is resolved solely from stored provider identifiers. */
class WebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['raw_payload'];

    protected function casts(): array
    {
        return ['raw_payload' => 'encrypted', 'processed_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
