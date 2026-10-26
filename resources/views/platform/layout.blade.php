<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Platform administration') · SmileSuite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-body">
    <a class="skip-link" href="#main">Skip to main content</a>
    <header class="landing-nav">
        <a class="brand" href="{{ route('platform.index') }}">smilesuite<span class="brand-dot">®</span></a>
        <span class="eyebrow">PLATFORM ADMINISTRATION</span>
        @auth('platform')
            <form method="POST" action="{{ route('platform.logout') }}">@csrf<button class="button secondary">Sign out</button></form>
        @endauth
    </header>
    <main id="main" class="main-content">
        @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @yield('content')
    </main>
</body>
</html>
