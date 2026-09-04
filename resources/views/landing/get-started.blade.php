@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', '')

@section('title', 'Get Started | CashERP')
@section('meta_description', 'Tell CashERP about your company, choose a published package and continue to industry-based registration.')
@section('body_class', 'page-get-started')

@section('content')
@php($googlePendingEmail = data_get(session('auth.google.pending_identity'), 'email'))

<section class="section get-started-section">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Get Started</span></nav>
        <div class="get-started-head reveal">
            <div>
                <h1>Set up your <em>CashERP</em> workspace</h1>
                <p class="hero-lead">Tell us about your company, review the available packages and continue with the correct industry profile.</p>
            </div>
            <p class="script-note inline">Simple Fast Secure</p>
        </div>

        <ol class="setup-steps reveal" aria-label="Setup progress">
            <li class="is-active"><span>1</span> Company profile</li>
            <li><span>2</span> Choose package</li>
            <li><span>3</span> Complete registration</li>
        </ol>

        <div class="get-started-grid">
            <div class="setup-panel reveal">
                <div class="setup-form-col">
                    <h2>Tell us about your business</h2>
                    <p class="muted">Your selection is carried into package selection and the registration form.</p>
                    @if($googlePendingEmail)
                        <div class="social-connected" role="status">
                            <strong>Google email verified</strong>
                            <span>{{ $googlePendingEmail }}</span>
                        </div>
                    @elseif(config('services.google.enabled') && config('services.google.client_id') && config('services.google.client_secret'))
                        <a class="button button-outline button-large social-btn google-btn" href="{{ route('auth.google.redirect', ['intent' => 'signup']) }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.3c1.9-1.8 2.9-4.4 2.9-7.4Z"/><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.7-2.4l-3.3-2.5c-.9.6-2.1 1-3.4 1a5.9 5.9 0 0 1-5.5-4.1H3.1v2.6A10.1 10.1 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.5 14a6 6 0 0 1 0-3.9V7.5H3.1a10.1 10.1 0 0 0 0 9.1L6.5 14Z"/><path fill="#EA4335" d="M12 6a5.5 5.5 0 0 1 3.9 1.5l2.9-2.8A9.7 9.7 0 0 0 12 2a10.1 10.1 0 0 0-8.9 5.5l3.4 2.6A5.9 5.9 0 0 1 12 6Z"/></svg>
                            Sign up with Google
                        </a>
                        <div class="or-sep"><span>OR ENTER COMPANY DETAILS</span></div>
                    @endif
                    <form class="setup-form" method="post" action="{{ route('landing.get-started.store') }}">
                        @csrf
                        <label>Business name *
                            <input type="text" name="business_name" value="{{ old('business_name', optional($intent ?? null)->business_name) }}" placeholder="e.g. Mungo Villas Ltd" autocomplete="organization" maxlength="255" required>
                        </label>
                        <label>Industry *
                            <select name="industry" required>
                                <option value="">Select industry</option>
                                @foreach ([
                                    'general_business' => 'General Business & Trading',
                                    'restaurant_food_service' => 'Restaurant, Café & Fast Food',
                                    'hotel_lodge_guesthouse' => 'Hotel, Lodge & Guest House',
                                    'hotel_with_restaurant' => 'Hotel / Lodge with Restaurant',
                                    'property_management_rentals' => 'Property Management & Real Estate',
                                    'professional_services' => 'Professional Services',
                                ] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('industry', optional($intent ?? null)->industry_code) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Country *
                            <input type="text" name="country" value="{{ old('country', optional($intent ?? null)->country) }}" placeholder="e.g. Zambia" autocomplete="country-name" maxlength="100" required>
                        </label>
                        <label>Email address *
                            <input type="email" name="email" value="{{ old('email', $googlePendingEmail ?: optional($intent ?? null)->email) }}" autocomplete="email" maxlength="255" @readonly($googlePendingEmail) required>
                        </label>
                        <label class="reminder-consent">
                            <input type="checkbox" name="reminder_consent" value="1" @checked(old('reminder_consent', optional($intent ?? null)->reminder_consent))>
                            <span>Email me up to two reminders if I leave before completing registration. This is optional.</span>
                        </label>
                        @if($errors->any())
                            <div class="site-flash is-error" role="alert">{{ $errors->first() }}</div>
                        @endif
                        <button class="button button-primary button-large" type="submit">Review packages <span>→</span></button>
                        <p class="form-assurance">Package price, billing period, trial days and capacity are shown before registration.</p>
                    </form>
                </div>
                <div class="setup-visual-col">
                    <img src="{{ asset('img/landing/dashboard.png') }}" alt="CashERP dashboard preview">
                    <p class="script-note">Your business in one place</p>
                    <ul class="check-list">
                        <li><span>✓</span> One of six selectable industry profiles</li>
                        <li><span>✓</span> Company-specific modules and terminology</li>
                        <li><span>✓</span> Separate data for each company</li>
                        <li><span>✓</span> Role-based staff access</li>
                        <li><span>✓</span> Package-controlled companies, locations and users</li>
                    </ul>
                    <div class="help-box">
                        <strong>Clear package selection</strong>
                        <p>Only public packages configured by the CashERP Super Admin are offered.</p>
                    </div>
                </div>
            </div>

            <aside class="setup-aside reveal reveal-delay">
                <div class="aside-card">
                    <h3>Why choose CashERP?</h3>
                    <ul class="check-list">
                        <li><span>✓</span> Industry-based onboarding</li>
                        <li><span>✓</span> Published package allowances</li>
                        <li><span>✓</span> Multi-company and multi-location readiness</li>
                        <li><span>✓</span> Permission-controlled workspaces</li>
                    </ul>
                </div>
                <div class="aside-card testimonial">
                    <p><strong>Why industry comes first</strong></p>
                    <p>Your company’s industry determines its default modules, navigation and setup guidance. A user may manage other companies with different industries without mixing their records.</p>
                </div>
                <div class="aside-card">
                    <h3>Need help getting started?</h3>
                    <a class="button button-outline" href="mailto:sales@casherp.com">Talk to Sales <span>→</span></a>
                </div>
                <div class="aside-card">
                    <h3>Your data is safe with us</h3>
                    <p class="muted">Company separation and role-based permissions help limit staff access to the records and actions they need.</p>
                </div>
            </aside>
        </div>

        <div class="trusted-strip reveal">
            <h2>Configured for six practical business profiles</h2>
            <div class="trusted-logos" aria-label="Selectable CashERP industries">
                @foreach ([['Trading','General Business'],['Restaurant','Food Service'],['Hotel','Hospitality'],['Hotel + Restaurant','Combined Operations'],['Property','Real Estate'],['Services','Professional']] as $profile)
                    <div class="logo-chip"><b>{{ $profile[0] }}</b><small>{{ $profile[1] }}</small></div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection
