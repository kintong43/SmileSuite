<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Overview') · SmileSuite</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
@php($clinic = request()->attributes->get('tenant'))
@php($staff = auth()->check() && auth()->user()->hasRole('owner','receptionist','practitioner'))
<div class="app-shell" x-data="{ menu: false }">
<aside class="sidebar" :class="{ 'is-open': menu }" aria-label="Main navigation">
    <a href="{{ $staff ? '/dashboard' : '/portal' }}" class="brand"><span class="brand-symbol">s<span>·</span></span>smilesuite<span class="brand-dot">®</span></a>
    <div class="clinic-switch"><span class="clinic-mark">{{ mb_substr($clinic->name,0,1) }}</span><div><strong>{{ $clinic->name }}</strong><small>Clinic workspace</small></div><x-icon name="chevrons"/></div>
    <div class="nav-label">{{ $staff ? 'WORKSPACE' : 'YOUR CLINIC' }}</div>
    <nav>
        @if($staff)
        <a @class(['nav-link','active'=>request()->is('dashboard')]) href="/dashboard"><x-icon name="grid"/>Overview</a>
        <a @class(['nav-link','active'=>request()->is('schedule','appointments*')]) href="/schedule"><x-icon name="calendar"/>Schedule<span class="nav-dot"></span></a>
        <a @class(['nav-link','active'=>request()->is('patients*')]) href="/patients"><x-icon name="users"/>Patients</a>
        <a @class(['nav-link','active'=>request()->is('services')]) href="/services"><x-icon name="spark"/>Services</a>
        <a @class(['nav-link','active'=>request()->is('notifications*')]) href="/notifications"><x-icon name="mail"/>Notifications</a>
        @if(auth()->user()->hasRole('owner','receptionist'))
        <a @class(['nav-link','active'=>request()->is('reports*')]) href="{{ route('reports.index') }}"><x-icon name="grid"/>Reports</a>
        @endif
        @else
        <a @class(['nav-link','active'=>request()->is('portal')]) href="/portal"><x-icon name="grid"/>My appointments</a>
        <a class="nav-link" href="/appointments/create"><x-icon name="calendar"/>Book a visit</a>
        @endif
        <div class="nav-label">{{ $staff ? 'CLINIC' : 'MORE' }}</div>
        <a @class(['nav-link','active'=>request()->is('profile')]) href="{{ route('profile.edit') }}"><x-icon name="users"/>My profile</a>
        <a @class(['nav-link','active'=>request()->is('store*')]) href="/store"><x-icon name="bag"/>Clinic store<span class="nav-tag">SHOP</span></a>
        @if(auth()->user()->hasRole('owner'))
        <a @class(['nav-link','active'=>request()->is('billing*')]) href="/billing"><x-icon name="card"/>Subscription</a>
        <a @class(['nav-link','active'=>request()->is('settings*')]) href="/settings"><x-icon name="settings"/>Settings</a>
        @endif
    </nav>
    <div class="sidebar-bottom">
        @if(config('smilesuite.sandbox'))<div class="sandbox-note"><span class="live-dot"></span>Synthetic demo workspace<small>Explore freely. Sample data only.</small></div>@endif
        <div class="profile"><span class="avatar teal">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->roleNames()[0] ?? 'Member') }}</small></div><form method="POST" action="/logout">@csrf<button class="icon-button" title="Sign out" aria-label="Sign out"><x-icon name="logout"/></button></form></div>
    </div>
</aside>
<div class="workspace">
    <header class="topbar"><div class="topbar-left"><button class="icon-button mobile-menu" @click="menu = !menu" aria-label="Toggle menu"><x-icon name="menu"/></button><span class="breadcrumb">Workspace <span>/</span> <strong>@yield('title','Overview')</strong></span></div><div class="topbar-right"><span class="timezone"><x-icon name="globe"/>{{ $clinic->timezone }}</span><span class="top-divider"></span><a class="icon-button" href="{{ $staff ? '/schedule' : '/portal' }}" title="Appointment updates" aria-label="Appointment updates"><x-icon name="bell"/></a><span class="avatar small teal">{{ mb_substr(auth()->user()->name,0,1) }}</span></div></header>
    <main id="main" class="main-content">
        @if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert error" role="alert"><strong>Please check your entries.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
        <footer class="workspace-footer"><span>Thoughtfully connected care.</span><span>SmileSuite <span class="footer-dot">·</span> {{ now()->year }}</span></footer>
    </main>
</div>
</div>
</body></html>
