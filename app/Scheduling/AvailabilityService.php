<?php

namespace App\Scheduling;

use App\Models\Operatory;
use App\Models\Practitioner;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function __construct(private CalendarRules $rules) {}

    public function slots(Service $service, string $date, ?int $practitionerId = null, ?int $operatoryId = null): array
    {
        $tenant = $this->rules->tenant();
        if ((int) $service->tenant_id !== app(TenantContext::class)->id()) {
            $this->rules->reject('Service not available.', 'service_id');
        }
        $service = Service::findOrFail($service->id);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            $this->rules->reject('Invalid date.', 'date');
        }
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tenant->timezone);
            if ($day->toDateString() !== $date) {
                $this->rules->reject('Invalid date.', 'date');
            }
        } catch (\InvalidArgumentException $exception) {
            $this->rules->reject('Invalid date.', 'date');
        }
        if (! $this->rules->entitled($tenant) || ! $service->active) {
            return [];
        }
        $now = CarbonImmutable::now();
        $horizon = $now->setTimezone($tenant->timezone)->addDays($tenant->booking_horizon_days)->endOfDay()->utc();
        $minimum = $now->addMinutes($tenant->minimum_lead_minutes);
        $practitioners = Practitioner::when($practitionerId, fn ($query) => $query->whereKey($practitionerId))->where('active', true)->orderBy('id')->get();
        $chairs = Operatory::when($operatoryId, fn ($query) => $query->whereKey($operatoryId))->where('active', true)->orderBy('id')->get();
        $slots = [];
        for ($start = $day->utc(); $start < $day->addDay()->utc(); $start = $start->addMinutes(1)) {
            $local = $start->setTimezone($tenant->timezone);
            if (($local->hour * 60 + $local->minute) % max(1, $tenant->slot_interval_minutes) !== 0 || $start < $minimum || $start > $horizon) {
                continue;
            }
            $end = $start->addMinutes($service->duration_minutes);
            foreach ($practitioners as $practitioner) {
                foreach ($chairs as $chair) {
                    if ($this->rules->fits($service, $practitioner, $chair, $start->subMinutes($service->buffer_before_minutes), $end->addMinutes($service->buffer_after_minutes), $tenant->timezone)) {
                        $slots[] = ['start_at' => $start->format('Y-m-d\TH:i:s\Z'), 'end_at' => $end->format('Y-m-d\TH:i:s\Z'), 'practitioner_id' => $practitioner->id, 'operatory_id' => $chair->id];
                    }
                }
            }
        }

        return $slots;
    }
}
