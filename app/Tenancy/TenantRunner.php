<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

final class TenantRunner
{
    public function run(int|Tenant $tenant, Closure $callback): mixed
    {
        if (app()->bound(TenantContext::class)) {
            throw new \LogicException('Nested tenant contexts are forbidden.');
        }
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant);
        if (! $tenant->exists) {
            throw new \LogicException('Tenant must be persisted.');
        }
        app()->instance(TenantContext::class, new TenantContext($tenant));
        try {
            return $callback();
        } finally {
            app()->forgetInstance(TenantContext::class);
        }
    }
}
