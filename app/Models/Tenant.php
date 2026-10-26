<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['branding' => 'array', 'trial_ends_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $tenant): void {
            $tenant->subdomain = strtolower(trim($tenant->subdomain));
            if ($tenant->timezone !== null && ! in_array($tenant->timezone, timezone_identifiers_list(), true)) {
                throw new \InvalidArgumentException('A valid IANA timezone is required.');
            }
        });
    }
}
