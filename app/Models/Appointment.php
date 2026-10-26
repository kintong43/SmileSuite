<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends TenantModel
{
    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime', 'occupied_start_at' => 'immutable_datetime', 'occupied_end_at' => 'immutable_datetime', 'checked_in_at' => 'immutable_datetime', 'in_chair_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'version' => 'integer', 'price_minor' => 'integer', 'duration_minutes' => 'integer', 'buffer_before_minutes' => 'integer', 'buffer_after_minutes' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class, 'patient_id');
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'practitioner_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function operatory(): BelongsTo
    {
        return $this->belongsTo(Operatory::class, 'operatory_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class, 'appointment_id');
    }
}
