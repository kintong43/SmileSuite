<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends TenantModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'price_minor' => 'integer', 'duration_minutes' => 'integer', 'buffer_before_minutes' => 'integer', 'buffer_after_minutes' => 'integer'];
    }

    public function practitioners(): BelongsToMany
    {
        return $this->belongsToMany(Practitioner::class, 'practitioner_services')->wherePivot('tenant_id', TenantContext::current()->id())->withPivot('tenant_id');
    }

    public function operatories(): BelongsToMany
    {
        return $this->belongsToMany(Operatory::class, 'operatory_services')->wherePivot('tenant_id', TenantContext::current()->id())->withPivot('tenant_id');
    }
}
