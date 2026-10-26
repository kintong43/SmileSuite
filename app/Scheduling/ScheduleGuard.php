<?php

namespace App\Scheduling;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ScheduleGuard
{
    public function __construct(private CalendarRules $rules) {}

    /** All calendar/configuration writes and reservations acquire this same tenant row lock. */
    public function mutate(Closure $callback): mixed
    {
        return $this->serialized(function () use ($callback) {
            $result = $callback();
            foreach (Appointment::whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_chair'])->where('occupied_end_at', '>', CarbonImmutable::now())->get() as $appointment) {
                if (! $this->rules->fits($appointment->service, $appointment->practitioner, $appointment->operatory, CarbonImmutable::instance($appointment->occupied_start_at), CarbonImmutable::instance($appointment->occupied_end_at), $appointment->timezone, $appointment->id, false)) {
                    $this->rules->reject('This schedule change conflicts with an existing appointment. Resolve that appointment first.', 'schedule');
                }
            }

            return $result;
        });
    }

    public function serialized(Closure $callback): mixed
    {
        try {
            return DB::transaction(function () use ($callback) {
                Tenant::whereKey(app(TenantContext::class)->id())->lockForUpdate()->firstOrFail();

                return $callback();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? $exception->getCode(), ['23P01', '40001', '40P01'], true)) {
                $this->rules->reject('The slot is no longer available. Refresh available times.');
            }
            throw $exception;
        }
    }
}
