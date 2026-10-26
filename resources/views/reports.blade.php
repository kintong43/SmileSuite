@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="page-heading">
    <div>
        <h1>Operational reports</h1>
        <p>Review appointment activity in {{ $report['filters']['timezone'] }}.</p>
    </div>
    <a class="button secondary" href="{{ route('reports.export', ['from_date' => $report['filters']['from_date'], 'to_date' => $report['filters']['to_date']]) }}">Export CSV</a>
</div>
<section class="panel" aria-labelledby="report-range-heading">
    <div class="panel-heading">
        <div><h2 id="report-range-heading">Reporting period</h2><p>Dates include both selected days. Choose up to 366 days.</p></div>
    </div>
    <form class="form-grid" method="GET" action="{{ route('reports.index') }}">
        <label for="report-from">From date<input id="report-from" type="date" name="from_date" value="{{ old('from_date', $report['filters']['from_date']) }}" required></label>
        <label for="report-to">To date<input id="report-to" type="date" name="to_date" value="{{ old('to_date', $report['filters']['to_date']) }}" required></label>
        <div><button class="button primary" type="submit">Update report</button></div>
    </form>
</section>
<div class="metrics-grid">
    <article class="metric"><div class="metric-top"><span>Appointments</span></div><div class="metric-value">{{ $report['totals']['appointments'] }}</div><div class="metric-foot"><span>Includes cancellations and no-shows</span></div></article>
    <article class="metric"><div class="metric-top"><span>Visits completed</span></div><div class="metric-value">{{ $report['totals']['completed'] }}</div><div class="metric-foot"><span>Current completed status</span></div></article>
    <article class="metric"><div class="metric-top"><span>Scheduled value · {{ $report['currency'] }}</span></div><div class="metric-value money">{{ number_format($report['totals']['scheduled_value_minor'] / 100, 2) }}</div><div class="metric-foot"><span>Appointment prices, excluding cancellations</span></div></article>
</div>
<section class="panel" aria-labelledby="report-values-heading">
    <div class="panel-heading"><div><h2 id="report-values-heading">Scheduled value by currency</h2><p>These are scheduled appointment prices, not collected revenue. Historical currencies are shown separately. Values reflect current appointment status and the price recorded when booked.</p></div></div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scheduled value by currency">
        <table class="data-table">
            <caption class="sr-only">Scheduled appointment value, excluding cancellations, in each recorded currency</caption>
            <thead><tr><th scope="col">Currency</th><th scope="col">Scheduled value</th></tr></thead>
            <tbody>@forelse($report['scheduled_value_by_currency'] as $value)<tr><th scope="row">{{ $value['currency'] }}</th><td>{{ number_format($value['scheduled_value_minor'] / 100, 2) }}</td></tr>@empty<tr><td colspan="2">No appointments in this date range. Choose another reporting period to review activity.</td></tr>@endforelse</tbody>
        </table>
    </div>
</section>
@foreach(['by_status' => 'Appointment status', 'by_service' => 'Service', 'by_practitioner' => 'Practitioner', 'by_date' => 'Local appointment date'] as $group => $heading)
<section class="panel" aria-labelledby="report-{{ $group }}-heading">
    <div class="panel-heading"><div><h2 id="report-{{ $group }}-heading">{{ $heading }}</h2><p>Aggregate counts and scheduled values for the selected period.</p></div></div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Report by {{ strtolower($heading) }}">
        <table class="data-table">
            <caption class="sr-only">Appointment activity by {{ strtolower($heading) }}</caption>
            <thead><tr><th scope="col">{{ $heading }}</th><th scope="col">Currency</th><th scope="col">Appointments</th><th scope="col">Completed</th><th scope="col">Scheduled value</th></tr></thead>
            <tbody>
            @forelse($report[$group] as $row)
                <tr><th scope="row">{{ $group === 'by_status' ? ucfirst(str_replace('_', ' ', $row['label'])) : $row['label'] }}</th><td>{{ $row['currency'] }}</td><td>{{ $row['appointments'] }}</td><td>{{ $row['completed'] }}</td><td>{{ number_format($row['scheduled_value_minor'] / 100, 2) }}</td></tr>
            @empty
                <tr><td colspan="5">No appointments in this date range.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endforeach
@endsection
