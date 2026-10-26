@extends('platform.layout')
@section('title', 'Platform sign in')
@section('content')
<div class="auth-form-wrap">
    <span class="eyebrow">PLATFORM TEAM</span>
    <h1>Platform sign in</h1>
    <p class="auth-intro">Manage clinic lifecycle and billing metadata.</p>
    <form method="POST" action="{{ route('platform.login') }}">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary full">Continue</button>
    </form>
</div>
@endsection
