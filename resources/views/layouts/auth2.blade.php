<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CashERP')</title>
    <link rel="stylesheet" href="{{ asset('css/casherp-landing.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/casherp-landing-polish.css') }}?v=v21-merge-1">
    @yield('css')
    @stack('styles')
</head>
<body>
    <div class="container" style="padding: 48px 0;">
        @yield('content')
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    @yield('javascript')
    @stack('scripts')
</body>
</html>
