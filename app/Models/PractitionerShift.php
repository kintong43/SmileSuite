<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PractitionerShift extends TenantModel
{
    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'practitioner_id');
    }
}
