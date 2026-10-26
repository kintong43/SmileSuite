<?php

namespace App\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AuditEvent;
use App\Models\IdempotencyRequest;
use App\Models\Operatory;
use App\Models\OutboxMessage;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(private CalendarRules $rules, private ScheduleGuard $guard) {}

    public function book(User $actor, array $data, string $idempotencyKey): Appointment
    {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 200) {
            $this->rules->reject('Provide an idempotency key of at most 200 characters.', 'idempotency_key');
        }
        $canonical = [];
        foreach (['patient_id', 'service_id', 'practitioner_id', 'operatory_id'] as $field) {
            if (! isset($data[$field]) || filter_var($data[$field], FILTER_VALIDATE_INT) === false || (int) $data[$field] < 1) {
                $this->rules->reject('Select a valid resource.', $field);
            }
            $canonical[$field] = (int) $data[$field];
        }
        $canonical['start_at'] = trim((string) ($data['start_at'] ?? ''));
        $canonical['override_reason'] = trim((string) ($data['override_reason'] ?? ''));
        if ($canonical['override_reason'] !== '') {
            $this->reason($canonical['override_reason']);
        }
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return $this->guard->serialized(function () use ($actor, $canonical, $idempotencyKey, $hash) {
            $actor = $this->actor($actor);
            $record = IdempotencyRequest::where('actor_id', $actor->id)->where('operation', 'book')->where('key_hash', hash('sha256', $idempotencyKey))->first();
            if ($record && $record->expires_at > CarbonImmutable::now()) {
                if (! hash_equals($record->request_hash, $hash)) {
                    $this->rules->reject('This idempotency key was already used for different booking details.', 'idempotency_key');
                }
                $appointment = Appointment::findOrFail($record->result_resource_id);
                $this->authorize($actor, $appointment->patient_id, $appointment->practitioner_id);

                return $appointment;
            }
            if ($record) {
                $record->delete();
            }
            $values = $this->reservation($actor, $canonical, null, $canonical['override_reason']);
            $appointment = Appointment::create(array_merge($values, ['public_code' => (string) Str::ulid(), 'status' => 'scheduled', 'version' => 1, 'payment_method' => 'pay_at_clinic']));
            $this->changed($actor, $appointment, null, $canonical['override_reason'] ?: null, 'appointment.booked');
            IdempotencyRequest::create(['actor_id' => $actor->id, 'operation' => 'book', 'key_hash' => hash('sha256', $idempotencyKey), 'request_hash' => $hash, 'result_resource_id' => $appointment->id, 'expires_at' => CarbonImmutable::now()->addHours(24)]);

            return $appointment;
        });
    }

    public function reschedule(User $actor, Appointment $appointment, array $data, int $version, string $reason): Appointment
    {
        return $this->guard->serialized(function () use ($actor, $appointment, $data, $version, $reason) {
            $actor = $this->actor($actor);
            $appointment = $this->appointment($appointment, $version);
            $this->authorize($actor, $appointment->patient_id, $appointment->practitioner_id);
            if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
                $this->rules->reject('This appointment cannot be rescheduled.', 'status');
            }
            $this->reason($reason);
            $this->cancellationWindow($actor, $appointment, $reason);
            $selection = array_replace($appointment->only(['patient_id', 'service_id', 'practitioner_id', 'operatory_id']), $data);
            // Existing service terms are immutable snapshots; selecting another service takes current terms.
            $values = $this->reservation($actor, $selection, $appointment, $reason);
            $prior = $appointment->status;
            $appointment->fill($values);
            $appointment->version++;
            $appointment->save();
            $this->changed($actor, $appointment, $prior, $reason, 'appointment.rescheduled');

            return $appointment;
        });
    }

    public function transition(User $actor, Appointment $appointment, string $state, int $version, ?string $reason = null): Appointment
    {
        return $this->guard->serialized(function () use ($actor, $appointment, $state, $version, $reason) {
            $actor = $this->actor($actor);
            $appointment = $this->appointment($appointment, $version);
            if ($reason !== null && $reason !== '') {
                $this->reason($reason);
            }
            $staff = $this->authorize($actor, $appointment->patient_id, $appointment->practitioner_id);
            $prior = $appointment->status;
            $allowed = match ($prior) {
                'scheduled' => ['confirmed', 'checked_in', 'no_show', 'cancelled'],
                'confirmed' => ['checked_in', 'no_show', 'cancelled'],
                'checked_in' => ['in_chair', 'cancelled'],
                'in_chair' => ['completed'],
                default => [],
            };
            if (! in_array($state, $allowed, true) || (! $staff && ($state !== 'cancelled' || ! in_array($prior, ['scheduled', 'confirmed'], true)))) {
                $this->rules->reject('This appointment state change is not permitted.', 'status');
            }
            if ($state === 'cancelled') {
                $this->reason($reason);
                $this->cancellationWindow($actor, $appointment, $reason);
            }
            if ($state === 'no_show' && $appointment->start_at > CarbonImmutable::now()) {
                $this->rules->reject('No-show can only be recorded after the scheduled start.', 'status');
            }
            $appointment->status = $state;
            $appointment->version++;
            $timestamp = ['checked_in' => 'checked_in_at', 'in_chair' => 'in_chair_at', 'completed' => 'completed_at'][$state] ?? null;
            if ($timestamp) {
                $appointment->{$timestamp} = CarbonImmutable::now();
            }
            $appointment->save();
            $this->changed($actor, $appointment, $prior, $reason, 'appointment.'.$state);

            return $appointment;
        });
    }

    private function actor(User $actor): User
    {
        if ((int) $actor->tenant_id !== app(TenantContext::class)->id()) {
            $this->rules->reject('This account cannot operate in this clinic.', 'actor');
        }
        $actor = User::findOrFail($actor->id);
        if ($actor->status !== 'active') {
            $this->rules->reject('This account is inactive.', 'actor');
        }

        return $actor;
    }

    private function authorize(User $actor, int $patientId, int $practitionerId): bool
    {
        $patient = PatientProfile::findOrFail($patientId);
        if ($patient->status !== 'active') {
            $this->rules->reject('Patient is inactive.', 'patient_id');
        }
        if ($actor->hasRole('owner', 'receptionist')) {
            return true;
        }
        if ($actor->hasRole('practitioner') && Practitioner::whereKey($practitionerId)->where('user_id', $actor->id)->where('active', true)->exists()) {
            return true;
        }
        if (! $actor->hasRole('patient') || (int) $patient->user_id !== $actor->id || ! $actor->email_verified_at) {
            $this->rules->reject('A verified patient may manage only their own appointments.', 'patient_id');
        }

        return false;
    }

    private function appointment(Appointment $appointment, int $version): Appointment
    {
        if ((int) $appointment->tenant_id !== app(TenantContext::class)->id()) {
            $this->rules->reject('Appointment is not available.', 'appointment');
        }
        $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
        if ($appointment->version !== $version) {
            $this->rules->reject('This appointment changed. Refresh before editing.', 'version');
        }

        return $appointment;
    }

    private function reservation(User $actor, array $data, ?Appointment $existing, string $override): array
    {
        $tenant = $this->rules->tenant();
        if (! $this->rules->entitled($tenant)) {
            $this->rules->reject('New reservations are unavailable for this clinic.', 'billing');
        }
        foreach (['patient_id', 'service_id', 'practitioner_id', 'operatory_id'] as $field) {
            if (! isset($data[$field]) || filter_var($data[$field], FILTER_VALIDATE_INT) === false || (int) $data[$field] < 1) {
                $this->rules->reject('Select a valid resource.', $field);
            }
        }
        $patientId = (int) $data['patient_id'];
        if ($existing && $patientId !== $existing->patient_id) {
            $this->rules->reject('An appointment patient cannot be reassigned.', 'patient_id');
        }
        $practitioner = Practitioner::whereKey($data['practitioner_id'])->lockForUpdate()->firstOrFail();
        $chair = Operatory::whereKey($data['operatory_id'])->lockForUpdate()->firstOrFail();
        $service = Service::findOrFail($data['service_id']);
        $staff = $this->authorize($actor, $patientId, $practitioner->id);
        $start = $this->rules->instant((string) ($data['start_at'] ?? ''), $tenant->timezone);
        $local = $start->setTimezone($tenant->timezone);
        if (($local->hour * 60 + $local->minute) % max(1, $tenant->slot_interval_minutes) !== 0) {
            $this->rules->reject('Choose a time on the clinic booking grid.');
        }
        $now = CarbonImmutable::now();
        if ($start > $now->setTimezone($tenant->timezone)->addDays($tenant->booking_horizon_days)->endOfDay()->utc()) {
            $this->rules->reject('This time is outside the booking horizon.');
        }
        if ($start < $now->addMinutes($tenant->minimum_lead_minutes) && (! $staff || trim($override) === '')) {
            $this->rules->reject('This time is inside the minimum booking lead time. Staff must record an override reason.');
        }
        if ($start < $now) {
            $this->rules->reject('Cannot reserve an appointment in the past.');
        }
        $snapshot = $existing && $existing->service_id === $service->id ? $existing : $service;
        $end = $start->addMinutes($snapshot->duration_minutes);
        $occupiedStart = $start->subMinutes($snapshot->buffer_before_minutes);
        $occupiedEnd = $end->addMinutes($snapshot->buffer_after_minutes);
        if (! $this->rules->fits($service, $practitioner, $chair, $occupiedStart, $occupiedEnd, $tenant->timezone, $existing?->id)) {
            $this->rules->reject('The slot is no longer available. Refresh available times.');
        }

        return ['patient_id' => $patientId, 'service_id' => $service->id, 'practitioner_id' => $practitioner->id, 'operatory_id' => $chair->id, 'start_at' => $start, 'end_at' => $end, 'occupied_start_at' => $occupiedStart, 'occupied_end_at' => $occupiedEnd, 'price_minor' => $snapshot->price_minor, 'currency' => $snapshot->currency, 'duration_minutes' => $snapshot->duration_minutes, 'buffer_before_minutes' => $snapshot->buffer_before_minutes, 'buffer_after_minutes' => $snapshot->buffer_after_minutes, 'timezone' => $tenant->timezone];
    }

    private function cancellationWindow(User $actor, Appointment $appointment, ?string $reason): void
    {
        if ($appointment->start_at < CarbonImmutable::now()->addHours($this->rules->tenant()->cancellation_window_hours)
            && (! $this->authorize($actor, $appointment->patient_id, $appointment->practitioner_id) || ! trim((string) $reason))) {
            $this->rules->reject('This appointment is inside the cancellation window. Staff must record an override reason.', 'reason');
        }
    }

    private function reason(?string $reason): void
    {
        if (! trim((string) $reason) || mb_strlen((string) $reason) > 255) {
            $this->rules->reject('Record a reason of at most 255 characters.', 'reason');
        }
    }

    private function changed(User $actor, Appointment $appointment, ?string $prior, ?string $reason, string $action): void
    {
        AppointmentEvent::create(['appointment_id' => $appointment->id, 'actor_id' => $actor->id, 'prior_state' => $prior, 'new_state' => $appointment->status, 'reason' => $reason, 'occurred_at' => CarbonImmutable::now(), 'version' => $appointment->version]);
        AuditEvent::create(['actor_id' => $actor->id, 'action' => $action, 'resource_type' => 'appointment', 'resource_id' => $appointment->id, 'metadata' => ['version' => $appointment->version, 'prior_state' => $prior, 'new_state' => $appointment->status], 'occurred_at' => CarbonImmutable::now()]);
        OutboxMessage::create(['event_type' => $action, 'resource_id' => $appointment->id, 'version' => $appointment->version, 'deduplication_key' => 'appointment:'.$appointment->id.':'.$appointment->version, 'state' => 'pending', 'attempts' => 0, 'available_at' => CarbonImmutable::now()]);
    }
}
