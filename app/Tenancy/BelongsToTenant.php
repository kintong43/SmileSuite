<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), TenantContext::current()->id());
        });
        static::saving(function (Model $model): void {
            $tenantId = TenantContext::current()->id();
            if ($model->getAttribute('tenant_id') !== null && (int) $model->getAttribute('tenant_id') !== $tenantId) {
                throw new \LogicException('Tenant ownership does not match the validated context.');
            }
            if ($model->exists && (int) $model->getRawOriginal('tenant_id') !== $tenantId) {
                throw new \LogicException('Tenant ownership is immutable.');
            }
            $model->setAttribute('tenant_id', $tenantId);
        });
        static::deleting(function (Model $model): void {
            if ((int) $model->getAttribute('tenant_id') !== TenantContext::current()->id()) {
                throw new \LogicException('Cross-tenant deletion denied.');
            }
        });
    }
}
