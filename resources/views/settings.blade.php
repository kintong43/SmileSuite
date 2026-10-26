@extends('layouts.app')
@section('title','Settings')
@section('content')
<div class="page-heading"><div><div class="eyebrow">MAKE IT YOURS</div><h1>A workspace that feels like your clinic.</h1><p>Manage your team, resources, and the rhythm of your day.</p></div><span class="privacy-pill"><x-icon name="shield"/>Owner settings</span></div>
<div class="settings-grid"><section class="panel form-panel"><h2>Clinic preferences</h2><form method="POST" action="/settings/profile">@csrf<label>Clinic name<input name="name" value="{{ $clinic->name }}" required maxlength="100"></label><div class="form-row"><label>IANA timezone<input name="timezone" value="{{ $clinic->timezone }}" required></label><label>Currency<input name="currency" value="{{ $clinic->currency }}" required pattern="[A-Z]{3}" maxlength="3"></label></div><div class="form-row"><label>Start-time grid<select name="slot_interval_minutes">@foreach([5,10,15,20,30,60] as $interval)<option value="{{ $interval }}" @selected($clinic->slot_interval_minutes==$interval)>{{ $interval }} minutes</option>@endforeach</select></label><label>Booking horizon (days)<input required type="number" name="booking_horizon_days" min="1" max="365" value="{{ $clinic->booking_horizon_days }}"></label></div><div class="form-row"><label>Lead time (minutes)<input required type="number" name="minimum_lead_minutes" min="0" value="{{ $clinic->minimum_lead_minutes }}"></label><label>Cancellation window (hours)<input required type="number" name="cancellation_window_hours" min="0" value="{{ $clinic->cancellation_window_hours }}"></label></div><p class="helper">Existing appointments keep their time and service snapshots.</p><button class="button primary">Save preferences</button></form></section>
<section class="panel form-panel"><h2>Your team</h2>
<div class="team-list">
@foreach($users as $user)
@if($user->hasRole('owner','practitioner','receptionist'))
<details class="spaced"><summary class="details-heading"><span>{{ $user->name }} · {{ $user->status }}</span></summary>
<p class="helper">{{ $user->email }} · {{ implode(' · ', $user->roleNames()) }}</p>
<form method="POST" action="/settings/staff/{{ $user->id }}">@csrf
<input type="hidden" name="roles" value="">
<fieldset><legend>Team roles for {{ $user->name }}</legend>
@foreach(['owner'=>'Clinic owner','practitioner'=>'Practitioner','receptionist'=>'Receptionist'] as $role=>$label)
<label class="checkbox-label"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked($user->hasRole($role))>{{ $label }}</label>
@endforeach
</fieldset>
<label>Account status<select name="status"><option value="active" @selected($user->status==='active')>Active</option><option value="disabled" @selected($user->status==='disabled')>Disabled</option></select></label>
<p class="helper">Existing patient access is preserved. Changes require this account to sign in again. Keep an active clinic owner; resolve upcoming appointments before removing practitioner access.</p>
<button class="button secondary full">Save team access</button>
</form></details>
@endif
@endforeach
</div>
<details class="spaced"><summary class="details-heading">Invite a team member <x-icon name="plus"/></summary><form method="POST" action="/settings/invitations">@csrf<label>Email<input name="email" type="email" required></label><fieldset><legend>Clinic roles</legend><label class="checkbox-label"><input type="checkbox" name="roles[]" value="practitioner">Practitioner</label><label class="checkbox-label"><input type="checkbox" name="roles[]" value="receptionist">Receptionist</label></fieldset><button class="button secondary full">Send invitation</button></form></details><p class="helper">{{ $invitations->count() }} pending invitation(s). Invitations expire after 48 hours.</p></section>
<section class="panel form-panel"><h2>Clinic hours & shifts</h2><div class="hours-list">@foreach($hours as $hour)<div><strong>{{ ['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$hour->weekday] }}</strong><span>{{ substr($hour->local_start,0,5) }} – {{ substr($hour->local_end,0,5) }}</span></div>@endforeach</div><details class="spaced"><summary class="details-heading">Add or update availability <x-icon name="plus"/></summary><form method="POST" action="/settings/hours">@csrf<div class="form-row"><label>Rule<select name="type"><option value="clinic">Clinic hours (replace day)</option><option value="shift">Practitioner shift</option><option value="break">Practitioner break</option></select></label><label>Day<select name="weekday">@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)<option value="{{ $loop->iteration }}">{{ $day }}</option>@endforeach</select></label></div><label>Practitioner (for shifts/breaks)<select name="practitioner_id"><option value="">Clinic-wide</option>@foreach($practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->user->name }}</option>@endforeach</select></label><div class="form-row"><label>Start<input type="time" name="local_start" value="09:00" required></label><label>End<input type="time" name="local_end" value="17:00" required></label></div><p class="helper">Split overnight hours into separate days. Conflicting changes roll back.</p><button class="button secondary full">Save availability</button></form></details></section>
<section class="panel form-panel"><h2>Chairs & practitioners</h2>
@foreach(['chair'=>$operatories, 'practitioner'=>$practitioners] as $type=>$resources)
@foreach($resources as $resource)
<details class="spaced"><summary class="details-heading"><span>{{ $type==='chair' ? $resource->name : $resource->user->name }} · {{ $resource->active ? 'Active' : 'Inactive' }}</span></summary>
<form method="POST" action="/settings/eligibility">@csrf
<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="resource_id" value="{{ $resource->id }}">
<fieldset><legend>Compatible services</legend>
@foreach($allServices as $service)
<label class="checkbox-label"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked($resource->services->contains('id', $service->id))>{{ $service->name }}</label>
@endforeach
</fieldset><button class="button secondary full">Save compatible services</button></form>
@if($resource->active)
<form class="spaced" method="POST" action="/settings/resources/{{ $type }}/{{ $resource->id }}/deactivate">@csrf<button class="button secondary full">Deactivate {{ $type==='chair' ? 'chair' : 'practitioner' }}</button></form>
@endif
</details>
@endforeach
@endforeach
<p class="helper">Resolve upcoming appointments before removing a compatible service or deactivating its resource. Historical appointments remain on record.</p>
<details class="spaced"><summary class="details-heading">Add a resource <x-icon name="plus"/></summary><form action="/settings/resources" method="POST">@csrf<div class="form-row"><label>Resource type<select name="type"><option value="chair">Chair</option><option value="practitioner">Practitioner</option></select></label><label>Name / display title<input name="name" required maxlength="120"></label></div><label>Staff account (practitioners)<select name="user_id"><option value="">Select account</option>@foreach($users as $user)@if($user->hasRole('practitioner'))<option value="{{ $user->id }}">{{ $user->name }}</option>@endif @endforeach</select></label><fieldset><legend>Compatible services</legend>@foreach($allServices as $service)<label class="checkbox-label"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}">{{ $service->name }}</label>@endforeach</fieldset><button class="button secondary full">Create resource</button></form></details></section>
<section class="panel form-panel"><h2>Closures, leave & maintenance</h2><p class="helper">Enter dated instants with an explicit UTC offset, for example 2026-12-25T09:00:00+08:00.</p>@foreach($blocks as $block)<div class="hours-list"><div><strong>{{ ucfirst($block->resource_type) }}</strong><span>{{ $block->starts_at->setTimezone($clinic->timezone)->format('M j, H:i') }} – {{ $block->ends_at->setTimezone($clinic->timezone)->format('M j, H:i') }}</span></div></div>@endforeach<form method="POST" action="/settings/blocks">@csrf<div class="form-row"><label>Resource<select name="resource_type"><option value="clinic">Whole clinic</option><option value="practitioner">Practitioner</option><option value="operatory">Chair</option></select></label><label>Resource ID (if applicable)<input name="resource_id" type="number" min="1"></label></div><label>Start with offset<input name="starts_at" required placeholder="2026-12-25T09:00:00+08:00"></label><label>End with offset<input name="ends_at" required placeholder="2026-12-25T17:00:00+08:00"></label><label>Reason<input name="reason" required maxlength="200"></label><button class="button secondary full">Create schedule block</button></form></section>
<section class="panel form-panel settings-note"><x-icon name="shield"/><h2>Thoughtful access, by design.</h2><p>Clinic ownership gives operational access. Patient intake and encounter notes are reserved for assigned practitioners.</p><p class="helper">Appointments stay on record when staff, chairs, or services are deactivated. Subscription limits are enforced when resources are added.</p><a class="text-link" href="/billing">View subscription <x-icon name="arrow"/></a></section></div>
@endsection
