@extends('layouts.app')
@section('title','Schedule')
@section('content')
<div class="page-heading"><div><div class="eyebrow">THE DAY, AT A GLANCE</div><h1>Make room for better care.</h1><p>Manage appointments, arrivals, and the moments in between.</p></div><a href="/appointments/create" class="button primary"><x-icon name="plus"/>New appointment</a></div>
<section class="panel"><div class="panel-heading"><div class="date-control"><a class="icon-button" href="?date={{ $date->subDay()->format('Y-m-d') }}" aria-label="Previous day">‹</a><h2>{{ $date->format('l, F j') }}</h2><a class="icon-button" href="?date={{ $date->addDay()->format('Y-m-d') }}" aria-label="Next day">›</a><a class="button small secondary" href="/schedule">Today</a></div><form method="GET"><label class="sr-only" for="schedule-date">Schedule date</label><input id="schedule-date" type="date" name="date" value="{{ $date->format('Y-m-d') }}"><button class="button small secondary">Go</button></form></div><x-appointment-table :appointments="$appointments"/></section>
<div class="subtle-note"><x-icon name="clock"/>All times are shown in {{ $clinic->timezone }}. Service buffers reserve both the practitioner and chair.</div>
@endsection
