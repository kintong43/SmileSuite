<?php

namespace App\Tenancy;

use App\Models\Tenant;

final readonly class TenantContext
{
    private int $tenantId;

    public function __construct(public Tenant $tenant)
    {
        $this->tenantId = (int) $tenant->getKey();
    }

    public function id(): int
    {
        return $this->tenantId;
    }

    public static function current(): self
    {
        if (! app()->bound(self::class)) {
            throw new MissingTenantContext('A validated tenant context is required.');
        }

        return app(self::class);
    }
}
