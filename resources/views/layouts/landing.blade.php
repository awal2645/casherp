<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0052FF">
    <meta name="description" content="@yield('meta_description', 'CashERP is a cloud ERP for modern businesses. Run sales, inventory, accounting, employees and operations from one connected workspace.')">
    <meta property="og:title" content="@yield('og_title', 'CashERP | One ERP. Every part of your business.')">
    <meta property="og:description" content="@yield('og_description', 'Run sales, inventory, accounting, employees and operations from one beautifully connected workspace.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/casherp-landing.css') }}?v=pricing48">
    <link rel="stylesheet" href="{{ asset('css/casherp-landing-polish.css') }}?v=v21-merge-2">
    @stack('styles')
    <title>@yield('title', 'CashERP | One ERP. Every part of your business.')</title>
</head>
<body class="@yield('body_class')">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @include('landing.partials.header')
    @if(session('status'))
        @php($flashStatus = session('status'))
        <div class="site-flash {{ !empty($flashStatus['success']) ? 'is-success' : 'is-error' }}" role="status" aria-live="polite">
            <div class="container">{{ $flashStatus['msg'] ?? '' }}</div>
        </div>
    @endif
    <main id="main-content">
        @yield('content')
    </main>
    @include('landing.partials.footer')
    <script src="{{ asset('js/casherp-landing.js') }}?v=pages41" defer></script>
    @stack('scripts')
</body>
</html>
