@extends('platform.layout')
@section('title', 'Platform account security')
@section('content')
<div class="auth-form-wrap">
    <span class="eyebrow">PLATFORM ACCOUNT SECURITY</span>
    <h1>{{ $enrolling ? 'Set up your authenticator' : 'Verify your sign in' }}</h1>
    <p class="auth-intro">{{ $enrolling ? 'Add a SmileSuite platform account in your authenticator using the setup key, then enter its current code. The key expires in ten minutes.' : 'Enter the current six-digit code for your SmileSuite platform account.' }}</p>
    @if($enrolling)
        <label>Authenticator setup key<input readonly value="{{ $secret }}" autocomplete="off"></label>
        <p class="helper">Keep this key private. It is saved only after you verify a code.</p>
    @endif
    <form method="POST" action="{{ route('platform.security') }}">
        @csrf
        <label>Authenticator code<input name="code" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" autofocus></label>
        <button class="button primary full">Verify and continue</button>
    </form>
</div>
@endsection
