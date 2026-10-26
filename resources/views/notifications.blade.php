@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">APPOINTMENT COMMUNICATIONS</div>
        <h1>Notification delivery</h1>
        <p>Review appointment email status and recover failed deliveries.</p>
    </div>
</div>
<section class="panel" aria-labelledby="delivery-heading">
    <div class="panel-heading">
        <div>
            <h2 id="delivery-heading">Appointment updates</h2>
            <p>Automatic delivery stops after {{ $maxAttempts }} attempts. An authorized retry starts a new attempt cycle.</p>
        </div>
    </div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Notification delivery status">
        <table class="data-table">
            <caption class="sr-only">Email delivery status for appointment updates</caption>
            <thead>
                <tr>
                    <th scope="col">Appointment</th>
                    <th scope="col">Outbox status</th>
                    <th scope="col">Email status</th>
                    <th scope="col">Attempts</th>
                    <th scope="col">Delivery details</th>
                    <th scope="col">Recovery</th>
                </tr>
            </thead>
            <tbody>
            @forelse($messages as $message)
                <tr>
                    <th scope="row">#{{ $message['appointment_id'] }}<br><small>Version {{ $message['version'] }}</small></th>
                    <td>{{ $message['state'] }}</td>
                    <td>{{ $message['delivery_state'] }}</td>
                    <td>{{ $message['attempts'] }} / {{ $maxAttempts }}</td>
                    <td>
                        @if($message['error'])<strong>{{ $message['error'] }}</strong><br>@endif
                        @if($message['sent_at'])
                            <small>Sent {{ $message['sent_at']->setTimezone($clinic->timezone)->format('M j, Y g:i a') }}</small>
                        @elseif($message['state'] === 'Failed' && $message['attempts'] >= $maxAttempts)
                            <small>Automatic retries exhausted</small>
                        @elseif(in_array($message['state'], ['Pending', 'Failed', 'Queued', 'Sending'], true))
                            <small>Available {{ $message['available_at']->setTimezone($clinic->timezone)->format('M j, Y g:i a') }}</small>
                        @else
                            <small>No delivery scheduled</small>
                        @endif
                    </td>
                    <td>
                        @if($message['can_retry'])
                            <form method="POST" action="/notifications/{{ $message['id'] }}/retry">
                                @csrf
                                <label for="retry-reason-{{ $message['id'] }}">Retry reason
                                    <select id="retry-reason-{{ $message['id'] }}" name="reason" required>
                                        <option value="">Select an operational reason</option>
                                        @foreach($reasons as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                </label>
                                <button class="button secondary small" type="submit" aria-label="Retry notification for appointment {{ $message['appointment_id'] }} version {{ $message['version'] }}">Request retry</button>
                            </form>
                        @else
                            <span class="muted">No retry available</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No appointment notifications to show.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel-heading">
        <p>{{ $messages->total() }} notification updates · Times shown in {{ $clinic->timezone }}</p>
        @if($messages->hasPages())
            <nav aria-label="Notification pages">
                @if($messages->previousPageUrl())<a class="button secondary small" href="{{ $messages->previousPageUrl() }}">Previous</a>@endif
                @if($messages->nextPageUrl())<a class="button secondary small" href="{{ $messages->nextPageUrl() }}">Next</a>@endif
            </nav>
        @endif
    </div>
</section>
@endsection
