@extends('layouts.auth')
@section('title','Protect your workspace')
@section('content')
<span class="eyebrow">STAFF ACCOUNT SECURITY</span><h1>{{ $enrolling ? 'A little extra protection.' : 'One more step.' }}</h1><p class="auth-intro">{{ $enrolling ? 'Add this account to your authenticator, then enter its six-digit code.' : 'Enter the current code from your authenticator.' }}</p>
@if($enrolling)<label>Authenticator setup key<input readonly value="{{ $secret }}" aria-label="Authenticator secret key"></label><p class="helper">Use manual setup in your authenticator with this key. Keep it private.</p>@endif
<form method="POST" action="/security">@csrf<label>Authenticator code<input name="code" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" autofocus></label><button class="button primary full">Verify and continue <x-icon name="arrow"/></button></form><p class="helper">If you lose access, contact your clinic owner through the documented identity-verification process.</p>
@endsection
