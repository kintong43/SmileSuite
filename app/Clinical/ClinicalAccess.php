<?php

namespace App\Clinical;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\CareAssignment;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\User;

class ClinicalAccess
{
    public function canRead(User $user, PatientProfile $patient): bool
    {
        if ($user->tenant_id !== $patient->tenant_id || $user->status !== 'active') {
            return false;
        }
        if ($patient->user_id === $user->id) {
            return true;
        }

        return $this->hasCareAccess($user, $patient);
    }

    private function hasCareAccess(User $user, PatientProfile $patient): bool
    {
        if ($user->tenant_id !== $patient->tenant_id || $user->status !== 'active' || ! $user->hasRole('practitioner')) {
            return false;
        }
        $practitioner = Practitioner::where('user_id', $user->id)->where('active', true)->first();
        if (! $practitioner) {
            return false;
        }

        return Appointment::where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_chair'])->exists()
            || CareAssignment::where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)
                ->whereNull('revoked_at')->where('valid_from', '<=', now())
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', now()))->exists();
    }

    public function canWriteNote(User $user, PatientProfile $patient): bool
    {
        return $this->hasCareAccess($user, $patient);
    }

    public function permittedAppointmentIds(User $user, PatientProfile $patient): array
    {
        $practitioner = Practitioner::where('user_id', $user->id)->where('active', true)->first();
        if (! $practitioner) {
            return [];
        }
        $assignments = CareAssignment::where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)->whereNull('revoked_at')->where('valid_from', '<=', now())->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))->get();
        if ($assignments->contains(fn ($assignment) => $assignment->appointment_id === null)) {
            return Appointment::where('patient_id', $patient->id)->pluck('id')->all();
        }

        return Appointment::where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_chair'])->pluck('id')->merge($assignments->pluck('appointment_id'))->unique()->all();
    }

    public function audit(User $actor, string $action, PatientProfile $patient): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'action' => $action, 'resource_type' => 'patient_profile', 'resource_id' => $patient->id, 'metadata' => [], 'occurred_at' => now()]);
    }
}
