@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<div class="page-heading"><div><h1>My profile</h1><p>Manage your contact details and account security.</p></div></div>
<section class="panel" aria-labelledby="contact-heading">
    <div class="panel-heading"><div><h2 id="contact-heading">Account and contact details</h2><p>Changing your email requires your password and a new verification.</p></div></div>
    <form class="form-grid" method="POST" action="{{ route('profile.update') }}">
        @csrf
        <label for="profile-name">Display name<input id="profile-name" name="name" autocomplete="name" value="{{ old('name', $account->name) }}" maxlength="100" required></label>
        <label for="profile-email">Email address<input id="profile-email" type="email" name="email" autocomplete="email" value="{{ old('email', $account->email) }}" maxlength="254" required></label>
        @if($patient)
        <label for="profile-first">First name<input id="profile-first" name="first_name" autocomplete="given-name" value="{{ old('first_name', $patient->first_name) }}" maxlength="100" required></label>
        <label for="profile-last">Last name<input id="profile-last" name="last_name" autocomplete="family-name" value="{{ old('last_name', $patient->last_name) }}" maxlength="100" required></label>
        <label for="profile-phone">Phone<input id="profile-phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $patient->phone) }}" maxlength="40"></label>
        @endif
        <label for="profile-current">Current password (when changing email)<input id="profile-current" type="password" name="current_password" autocomplete="current-password"></label>
        <div><button class="button primary" type="submit">Save profile</button></div>
    </form>
</section>
<section class="panel" aria-labelledby="password-heading">
    <div class="panel-heading"><div><h2 id="password-heading">Change password</h2><p>Use at least 12 characters with letters and numbers. Sign in again after saving; other sessions will be revoked.</p></div></div>
    <form class="form-grid" method="POST" action="{{ route('profile.password') }}">
        @csrf
        <label for="password-current">Current password<input id="password-current" name="current_password" type="password" autocomplete="current-password" required></label>
        <label for="password-new">New password<input id="password-new" name="password" type="password" autocomplete="new-password" minlength="12" required></label>
        <label for="password-confirm">Confirm new password<input id="password-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label>
        <div><button class="button primary" type="submit">Change password</button></div>
    </form>
</section>
@endsection
