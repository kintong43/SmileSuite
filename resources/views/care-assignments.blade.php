@extends('layouts.app')
@section('title', 'Care assignments')
@section('content')
<div class="page-heading"><div><div class="eyebrow">CLINICAL ACCESS</div><h1>Care assignments</h1><p>{{ $patient->full_name }} · Encounter access with an explicit expiry.</p></div><a class="button secondary" href="/patients/{{ $patient->id }}/clinical">Clinical record</a></div>
<div class="content-columns"><section class="panel form-panel"><h2>Your assignment history</h2>
<p class="helper">Only assignments you issued or received are listed. Revocation removes delegated access immediately.</p>
@forelse($assignments as $assignment)
<article class="note-entry">
<h3>{{ $recipientNames[$assignment->practitioner_id] ?? 'Practitioner' }}</h3>
<p>Encounter #{{ $assignment->appointment_id ?? 'All encounters (existing assignment)' }} · {{ ucfirst(str_replace('_', ' ', $assignment->purpose)) }}</p>
<p class="helper">Issued by {{ $issuerNames[$assignment->granted_by] ?? 'Practitioner' }}. Expires {{ $assignment->valid_to?->setTimezone($clinic->timezone)->format('M j, Y · g:i A T') ?? 'Without an expiry (existing assignment)' }}.</p>
@if($assignment->revoked_at)<span class="status">Revoked</span>
@elseif($assignment->valid_to && $assignment->valid_to->isPast())<span class="status">Expired</span>
@elseif($assignment->granted_by === $actor->id)
<form method="POST" action="/patients/{{ $patient->id }}/care-assignments/{{ $assignment->id }}/revoke">@csrf<button class="button secondary">Revoke access</button></form>
@else<span class="status status-confirmed">Active</span>@endif
</article>
@empty<div class="empty-state"><strong>No assignments yet.</strong><p>Assignments you issue or receive will appear here.</p></div>@endforelse
</section><aside class="panel form-panel"><h2>Delegate encounter access</h2>
@if($appointments->isNotEmpty())
<form method="POST" action="/patients/{{ $patient->id }}/care-assignments">@csrf
<label>Encounter<select name="appointment_id" required>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" @selected(old('appointment_id') == $appointment->id)>{{ $appointment->public_code }} · {{ $appointment->start_at->setTimezone($clinic->timezone)->format('M j, Y g:i A') }}</option>@endforeach</select></label>
<label>Receiving practitioner<select name="practitioner_id" required><option value="">Select a practitioner</option>@foreach($practitioners as $practitioner)<option value="{{ $practitioner->id }}" @selected(old('practitioner_id') == $practitioner->id)>{{ $practitioner->user->name }}</option>@endforeach</select></label>
<label>Care purpose<select name="purpose">@foreach(['follow_up' => 'Follow-up care', 'covering_care' => 'Covering care', 'consultation' => 'Consultation'] as $purpose => $label)<option value="{{ $purpose }}" @selected(old('purpose', 'covering_care') === $purpose)>{{ $label }}</option>@endforeach</select></label>
<label>Access expires (with UTC offset)<input name="valid_to" required value="{{ old('valid_to', now()->addDays(7)->setTimezone($clinic->timezone)->toIso8601String()) }}" aria-describedby="expiry-help"></label>
<p class="helper" id="expiry-help">Use a date and time with an offset, such as {{ now()->addDays(7)->setTimezone($clinic->timezone)->toIso8601String() }}. Access must expire within 90 days. Only the selected encounter is delegated.</p>
<button class="button primary full">Grant encounter access</button></form>
@else<p>You currently have no encounter access available to delegate. You can still revoke assignments you issued.</p>@endif
</aside></div>
@endsection
