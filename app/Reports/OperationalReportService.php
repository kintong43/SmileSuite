<?php

namespace App\Reports;

use App\Models\Appointment;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperationalReportService
{
    /**
     * @return array{filters: array{from_date: string, to_date: string, timezone: string}, currency: string, totals: array{appointments: int, completed: int, scheduled_value_minor: int}, scheduled_value_by_currency: list<array{currency: string, scheduled_value_minor: int}>, by_status: list<array{label: string, currency: string, appointments: int, completed: int, scheduled_value_minor: int}>, by_service: list<array{label: string, currency: string, appointments: int, completed: int, scheduled_value_minor: int}>, by_practitioner: list<array{label: string, currency: string, appointments: int, completed: int, scheduled_value_minor: int}>, by_date: list<array{label: string, currency: string, appointments: int, completed: int, scheduled_value_minor: int}>}
     */
    public function generate(string $fromDate, string $toDate): array
    {
        $tenant = TenantContext::current()->tenant;
        Validator::make(['from_date' => $fromDate, 'to_date' => $toDate], [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ])->validate();
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $fromDate, $tenant->timezone);
        $until = CarbonImmutable::createFromFormat('!Y-m-d', $toDate, $tenant->timezone)->addDay();
        if ($from->diffInDays($until) > 366) {
            throw ValidationException::withMessages(['to_date' => 'Choose a date range of at most 366 days.']);
        }

        $localDate = "to_char(appointments.start_at AT TIME ZONE ?, 'YYYY-MM-DD')";
        $buckets = Appointment::query()
            ->join('services', function (JoinClause $join): void {
                $join->on('services.id', '=', 'appointments.service_id')->on('services.tenant_id', '=', 'appointments.tenant_id');
            })
            ->join('practitioners', function (JoinClause $join): void {
                $join->on('practitioners.id', '=', 'appointments.practitioner_id')->on('practitioners.tenant_id', '=', 'appointments.tenant_id');
            })
            ->join('users', function (JoinClause $join): void {
                $join->on('users.id', '=', 'practitioners.user_id')->on('users.tenant_id', '=', 'appointments.tenant_id');
            })
            ->where('appointments.start_at', '>=', $from->utc())
            ->where('appointments.start_at', '<', $until->utc())
            ->selectRaw($localDate.' AS local_date', [$tenant->timezone])
            ->addSelect(['appointments.status', 'appointments.currency', 'services.id as service_id', 'services.name as service_name', 'practitioners.id as practitioner_id', 'users.name as practitioner_name'])
            ->selectRaw("COUNT(*) AS appointments, SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN appointments.status <> 'cancelled' THEN appointments.price_minor ELSE 0 END) AS scheduled_value_minor")
            ->groupBy('local_date')
            ->groupBy(['appointments.status', 'appointments.currency', 'services.id', 'services.name', 'practitioners.id', 'users.name'])
            ->toBase()->get();

        $totals = ['appointments' => 0, 'completed' => 0, 'scheduled_value_minor' => 0];
        $groups = ['by_status' => [], 'by_service' => [], 'by_practitioner' => [], 'by_date' => []];
        $currencies = [];
        foreach ($buckets as $bucket) {
            $counts = ['appointments' => (int) $bucket->appointments, 'completed' => (int) $bucket->completed, 'scheduled_value_minor' => (int) $bucket->scheduled_value_minor];
            $totals['appointments'] += $counts['appointments'];
            $totals['completed'] += $counts['completed'];
            if ($bucket->currency === $tenant->currency) {
                $totals['scheduled_value_minor'] += $counts['scheduled_value_minor'];
            }
            $currencies[$bucket->currency] ??= ['currency' => $bucket->currency, 'scheduled_value_minor' => 0];
            $currencies[$bucket->currency]['scheduled_value_minor'] += $counts['scheduled_value_minor'];
            foreach ([
                'by_status' => [$bucket->status, $bucket->status],
                'by_service' => [$bucket->service_id, $bucket->service_name],
                'by_practitioner' => [$bucket->practitioner_id, $bucket->practitioner_name],
                'by_date' => [$bucket->local_date, $bucket->local_date],
            ] as $group => [$id, $label]) {
                $key = $id.'|'.$bucket->currency;
                $groups[$group][$key] ??= ['label' => $label, 'currency' => $bucket->currency, 'appointments' => 0, 'completed' => 0, 'scheduled_value_minor' => 0];
                foreach ($counts as $field => $value) {
                    $groups[$group][$key][$field] += $value;
                }
            }
        }
        foreach ($groups as &$rows) {
            usort($rows, fn (array $left, array $right): int => [$left['label'], $left['currency']] <=> [$right['label'], $right['currency']]);
        }
        unset($rows);
        ksort($currencies);

        return [
            'filters' => ['from_date' => $fromDate, 'to_date' => $toDate, 'timezone' => $tenant->timezone],
            'currency' => $tenant->currency,
            'totals' => $totals,
            'scheduled_value_by_currency' => array_values($currencies),
            ...$groups,
        ];
    }
}
