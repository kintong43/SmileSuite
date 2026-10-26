@extends('layouts.app')
@section('title','Content management')
@section('content')
<h1>Content management</h1>
<p>Publish clinic pages, announcements and service descriptions through Directus.</p>
<div class="card" style="padding:24px">
<p role="status">{{ $available ? 'Directus is connected.' : 'Directus is temporarily unavailable. Booking remains available.' }}</p>
<p>Clinic content scope: <strong>{{ $scope }}</strong></p>
@if($adminUrl)<a class="button primary" href="{{ $adminUrl }}" target="_blank" rel="noopener noreferrer">Open Directus editor</a>@endif
<p>Use your separate clinic editor account. Content is displayed as plain text. Patient records and account credentials stay in SmileSuite.</p>
@if($available)<dl>@foreach($counts as $collection => $count)<dt>{{ str_replace(['ss_','_'], ['', ' '], $collection) }}</dt><dd>{{ $count }} records shown (up to 500)</dd>@endforeach</dl>@endif
<p>Your administrator refreshes operational snapshots. Directory entries contain staff display names and roles; reports contain aggregate appointment counts and scheduled values. Booking information remains authoritative in SmileSuite.</p>
</div>
@endsection
