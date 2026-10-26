@extends('content.layout')
@section('title', 'Announcements & services')
@section('content')
<h1>Announcements & services</h1>
<h2>Latest updates</h2>
@forelse($announcements as $announcement)
<article class="card" style="padding:24px;margin:16px 0"><h3>{{ $announcement['title'] }}</h3><p style="white-space:pre-wrap">{{ $announcement['body'] ?? '' }}</p></article>
@empty<p>No published announcements are available.</p>@endforelse
<h2>Our services</h2>
<p>Service descriptions are for information. Your clinic confirms appointment availability and fees during booking.</p>
@forelse($services as $service)
<article class="card" style="padding:24px;margin:16px 0"><h3>{{ $service['title'] }}</h3><p style="white-space:pre-wrap">{{ $service['body'] ?? '' }}</p></article>
@empty<p>No published service descriptions are available.</p>@endforelse
@endsection
