@extends('layouts.auth')
@section('title','Join your clinic')
@section('content')
<span class="eyebrow">YOU’RE INVITED</span><h1>A place on the team.</h1><p class="auth-intro">Join {{ request()->attributes->get('tenant')->name }} as {{ $invitation->email }}.</p><form method="POST" action="/invitation/{{ $token }}">@csrf<label>Your full name<input name="name" required autocomplete="name"></label><label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label><button class="button primary full">Accept invitation<x-icon name="arrow"/></button></form>
@endsection
