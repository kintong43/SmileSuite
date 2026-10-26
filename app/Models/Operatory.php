<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Operatory extends TenantModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'operatory_services')->wherePivot('tenant_id', TenantContext::current()->id())->withPivot('tenant_id');
    }
}
