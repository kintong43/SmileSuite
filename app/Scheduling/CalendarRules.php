<?php

namespace App\Scheduling;

use App\Billing\EntitlementService;
use App\Models\Appointment;
use App\Models\ClinicHour;
use App\Models\Operatory;
use App\Models\Practitioner;
use App\Models\PractitionerBreak;
use App\Models\PractitionerShift;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared calendar rules used again inside the reservation transaction. */
class CalendarRules
{
    public function tenant(): Tenant
    {
        return Tenant::findOrFail(app(TenantContext::class)->id());
    }

    public function reject(string $message, string $field = 'start_at'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function instant(string $value, string $timezone): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            $this->reject('Choose an exact minute with an explicit timezone offset.');
        }
        try {
            $instant = CarbonImmutable::parse($value);
            if ($instant->format('Y-m-d\TH:i:sP') !== str_replace('Z', '+00:00', $value)) {
                $this->reject('Invalid appointment date.');
            }
            if (! str_ends_with($value, 'Z') && ! str_ends_with($value, '+00:00') && $instant->setTimezone($timezone)->format('Y-m-d\TH:i:sP') !== $value) {
                $this->reject('The offset is not valid for this clinic local time.');
            }

            return $instant->utc();
        } catch (\InvalidArgumentException $exception) {
            $this->reject('Invalid appointment date.');
        }
    }

    public function entitled(Tenant $tenant): bool
    {
        if ($tenant->lifecycle_status !== 'active') {
            return false;
        }
        $entitlement = DB::table('billing_entitlements')->where('tenant_id', $tenant->id)->first();

        return $entitlement && app(EntitlementService::class)->allowsNewBookings()
            && ($entitlement->chair_limit === null || Operatory::where('active', true)->count() <= $entitlement->chair_limit)
            && ($entitlement->practitioner_limit === null || Practitioner::where('active', true)->count() <= $entitlement->practitioner_limit);
    }

    public function eligible(Service $service, Practitioner $practitioner, Operatory $chair): bool
    {
        if (! $service->active || ! $practitioner->active || ! $chair->active) {
            return false;
        }
        $user = $practitioner->user;
        if (! $user || $user->status !== 'active' || ! $user->hasRole('practitioner')) {
            return false;
        }
        $tenantId = app(TenantContext::class)->id();

        return DB::table('practitioner_services')->where('tenant_id', $tenantId)->where('practitioner_id', $practitioner->id)->where('service_id', $service->id)->exists()
            && DB::table('operatory_services')->where('tenant_id', $tenantId)->where('operatory_id', $chair->id)->where('service_id', $service->id)->exists();
    }

    public function fits(Service $service, Practitioner $practitioner, Operatory $chair, CarbonImmutable $occupiedStart, CarbonImmutable $occupiedEnd, string $timezone, ?int $except = null, bool $overlaps = true): bool
    {
        if (! $this->eligible($service, $practitioner, $chair)) {
            return false;
        }
        if (! $this->covered(ClinicHour::all(), $occupiedStart, $occupiedEnd, $timezone)
            || ! $this->covered(PractitionerShift::where('practitioner_id', $practitioner->id)->get(), $occupiedStart, $occupiedEnd, $timezone)) {
            return false;
        }
        foreach ($this->intervals(PractitionerBreak::where('practitioner_id', $practitioner->id)->get(), $occupiedStart, $occupiedEnd, $timezone) as [$start, $end]) {
            if ($start < $occupiedEnd && $end > $occupiedStart) {
                return false;
            }
        }
        $blocked = ScheduleBlock::where('starts_at', '<', $occupiedEnd)->where('ends_at', '>', $occupiedStart)
            ->where(fn ($query) => $query->where('resource_type', 'clinic')->orWhere('practitioner_id', $practitioner->id)->orWhere('operatory_id', $chair->id))->exists();
        if ($blocked) {
            return false;
        }

        return ! $overlaps || ! Appointment::where('status', '!=', 'cancelled')
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->where('occupied_start_at', '<', $occupiedEnd)->where('occupied_end_at', '>', $occupiedStart)
            ->where(fn ($query) => $query->where('practitioner_id', $practitioner->id)->orWhere('operatory_id', $chair->id))->exists();
    }

    private function covered(iterable $rows, CarbonImmutable $start, CarbonImmutable $end, string $timezone): bool
    {
        $intervals = $this->intervals($rows, $start, $end, $timezone);
        usort($intervals, fn ($left, $right) => $left[0] <=> $right[0]);
        $cursor = $start;
        foreach ($intervals as [$from, $to]) {
            if ($from <= $cursor && $to > $cursor) {
                $cursor = $to;
                if ($cursor >= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function intervals(iterable $rows, CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
        $intervals = [];
        for ($day = $start->setTimezone($timezone)->startOfDay()->subDay(); $day <= $end->setTimezone($timezone)->startOfDay(); $day = $day->addDay()) {
            foreach ($rows as $row) {
                $date = $day->toDateString();
                if ((int) $row->weekday !== $day->isoWeekday()
                    || ($row->effective_from && $date < CarbonImmutable::parse($row->effective_from)->toDateString())
                    || ($row->effective_to && $date > CarbonImmutable::parse($row->effective_to)->toDateString())) {
                    continue;
                }
                $from = substr($row->local_start, 0, 8);
                $to = substr($row->local_end, 0, 8);
                $endDate = $to <= $from ? $day->addDay()->toDateString() : $date;
                $starts = $this->localInstants($date.' '.$from, $timezone);
                $ends = $this->localInstants($endDate.' '.$to, $timezone);
                // An ambiguous recurring boundary covers both folds. Explicit booking offsets still select one instant.
                if ($starts && $ends) {
                    $intervals[] = [min($starts), max($ends)];
                }
            }
        }

        return $intervals;
    }

    private function localInstants(string $wall, string $timezone): array
    {
        $naive = CarbonImmutable::parse($wall, 'UTC');
        // PostgreSQL TIME permits 24:00 as the boundary used to split overnight hours.
        if (str_ends_with($wall, ' 24:00:00')) {
            $wall = $naive->format('Y-m-d H:i:s');
        }
        $zone = new DateTimeZone($timezone);
        $offsets = array_unique(array_column($zone->getTransitions($naive->subDays(2)->timestamp, $naive->addDays(2)->timestamp), 'offset'));
        $instants = [];
        foreach ($offsets as $offset) {
            $instant = $naive->subSeconds($offset);
            if ($instant->setTimezone($timezone)->format('Y-m-d H:i:s') === $wall) {
                $instants[] = $instant;
            }
        }

        return $instants;
    }
}
