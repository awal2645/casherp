@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', '')

@section('title', 'Log in | CashERP')
@section('meta_description', 'Log in to your CashERP account and continue growing your business.')
@section('body_class', 'page-login')

@section('content')
@php
    $username = old('username');
    $password = null;
    if (config('app.env') == 'demo') {
        $username = 'admin';
        $password = '123456';
    }
@endphp

<section class="section login-section">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Login</span></nav>
        <div class="login-intro reveal">
            <h1>Welcome back</h1>
            <p>Log in to your CashERP account and continue growing your business.</p>
        </div>

        <div class="login-card reveal">
            <div class="login-form-col">
                <h2>Log in to CashERP</h2>
                <p class="muted">Use your username or email address, then your password.</p>

                @if ($errors->any())
                    <div class="field-error" role="alert">{{ $errors->first() === 'auth.failed' ? 'These credentials do not match our records.' : $errors->first() }}</div>
                @endif

                @if (config('app.env') == 'demo')
                    <div class="demo-note">Demo mode: use username <strong>admin</strong> / password <strong>123456</strong></div>
                @endif

                @include('auth.partials.role_wise_logins')

                <form method="POST" action="{{ route('login') }}" id="login-form" class="setup-form">
                    {{ csrf_field() }}
                    <label>Email address / Username
                        <span class="input-with-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                            <input type="text" name="username" id="username" value="{{ $username }}" required autofocus placeholder="Enter your email or username">
                        </span>
                        @if ($errors->has('username') && $errors->first('username') !== $errors->first())
                            <span class="field-error">{{ $errors->first('username') }}</span>
                        @endif
                    </label>
                    <label>Password
                        <span class="input-with-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input type="password" name="password" id="password" value="{{ $password }}" required placeholder="Enter your password">
                            <button type="button" class="eye-toggle" id="show_hide_icon" aria-label="Show password">👁</button>
                        </span>
                        @if ($errors->has('password'))
                            <span class="field-error">{{ $errors->first('password') }}</span>
                        @endif
                    </label>
                    <div class="form-row-between">
                        <label class="check-inline"><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> Keep me logged in</label>
                        @if (config('app.env') != 'demo')
                            <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>
                        @endif
                    </div>
                    @if(config('constants.enable_recaptcha'))
                        <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                        @if ($errors->has('g-recaptcha-response'))
                            <span class="field-error">{{ $errors->first('g-recaptcha-response') }}</span>
                        @endif
                    @endif
                    <button class="button button-primary button-large" type="submit">Log in <span>→</span></button>
                </form>

                @if(config('services.google.enabled') && config('services.google.client_id') && config('services.google.client_secret'))
                    <div class="or-sep"><span>OR</span></div>
                    <a class="button button-outline button-large social-btn google-btn" href="{{ route('auth.google.redirect', ['intent' => 'login']) }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.3c1.9-1.8 2.9-4.4 2.9-7.4Z"/><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.7-2.4l-3.3-2.5c-.9.6-2.1 1-3.4 1a5.9 5.9 0 0 1-5.5-4.1H3.1v2.6A10.1 10.1 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.5 14a6 6 0 0 1 0-3.9V7.5H3.1a10.1 10.1 0 0 0 0 9.1L6.5 14Z"/><path fill="#EA4335" d="M12 6a5.5 5.5 0 0 1 3.9 1.5l2.9-2.8A9.7 9.7 0 0 0 12 2a10.1 10.1 0 0 0-8.9 5.5l3.4 2.6A5.9 5.9 0 0 1 12 6Z"/></svg>
                        Continue with Google
                    </a>
                @endif
                <div class="or-sep"><span>NEW TO CASHERP?</span></div>
                <a class="button button-outline button-large social-btn" href="{{ url('/get-started') }}">Create a CashERP account</a>
                <p class="login-switch">Need a company workspace? <a class="text-link" href="{{ url('/get-started') }}">Review registration options <span>→</span></a></p>
            </div>
            <div class="login-promo-col">
                <h2>Smarter Business <em>Starts Here</em></h2>
                <p>Run sales, inventory, accounting, people and industry operations in one company-controlled workspace.</p>
                <ul class="promo-features">
                    <li><span class="tile-icon icon-green">📈</span> Track sales &amp; inventory</li>
                    <li><span class="tile-icon icon-violet">◎</span> Work with your team</li>
                    <li><span class="tile-icon icon-orange">⚙</span> Manage operations</li>
                    <li><span class="tile-icon icon-blue">🛡</span> Secure &amp; reliable</li>
                </ul>
                <div class="photo-frame compact">
                    <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="">
                    <p class="script-note">Grow Manage Succeed</p>
                </div>
            </div>
        </div>

        <div class="inline-stats login-trust reveal">
            <div><strong>6</strong><span>Industry profiles</span></div>
            <div><strong>Multi-company</strong><span>Separated business records</span></div>
            <div><strong>Role-based</strong><span>Controlled staff access</span></div>
            <div><strong>Audit-ready</strong><span>Operational histories</span></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.getElementById('show_hide_icon')?.addEventListener('click', function () {
    var input = document.getElementById('password');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
});
</script>
@endpush
