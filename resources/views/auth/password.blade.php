@extends('layouts.auth')
@section('title','Reset password')
@section('content')
<a href="/login" class="back-link">← Back to sign in</a><h1>A fresh start.</h1><p class="auth-intro">Reset your password for this clinic account.</p><form method="POST" action="{{ $mode==='reset' ? '/reset-password/'.$token : '/forgot-password' }}">@csrf<label>Email address<input name="email" type="email" required value="{{ old('email',request('email')) }}" autocomplete="email"></label>@if($mode==='reset')<label>New password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label><label>Confirm password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>@endif<button class="button primary full">{{ $mode==='reset' ? 'Save new password' : 'Send reset link' }}<x-icon name="arrow"/></button></form>
@endsection
