<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · SmileSuite</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="topbar"><a class="brand" href="{{ url('/') }}">smilesuite<span class="brand-dot">®</span></a><nav aria-label="Public navigation"><a href="{{ route('content.about') }}">About</a> · <a href="{{ route('content.features') }}">Features</a> · <a href="{{ route('content.announcements') }}">Announcements & services</a></nav></header>
<main id="main" class="main-content" style="max-width:960px;margin:auto">
@if($unavailable)<div class="alert" role="status">Live CMS content is temporarily unavailable. This is the basic site overview. Clinic booking remains available.</div>@endif
@yield('content')
<footer class="workspace-footer"><span>Thoughtfully connected care.</span><a href="{{ url('/') }}">Return to SmileSuite</a></footer>
</main>
</body>
</html>
