@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', 'about')

@section('title', 'About Us | CashERP')
@section('meta_description', 'Learn how CashERP empowers businesses across Africa with simple, powerful cloud ERP tools.')

@section('content')


<section class="page-hero">
    <div class="container page-hero-grid">
        <div class="page-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>About Us</span></nav>
            <p class="eyebrow">About us</p>
            <h1>Empowering Businesses for a Smarter Tomorrow</h1>
            <p class="hero-lead">We build simple, powerful tools that help businesses manage operations, grow faster and succeed with confidence.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="#our-story">Our Story <span>↓</span></a>
                <a class="button button-outline button-large" href="{{ url('/resources') }}">Watch Our Video</a>
            </div>
        </div>
        <div class="page-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="CashERP team collaborating">
                <p class="script-note">People Technology Growth Together.</p>
            </div>
        </div>
    </div>
</section>

<section class="stats-wrap">
    <div class="container">
        <div class="stats-bar">
            <div class="stats-grid stats-grid-4">
                <div class="stat-item"><div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0"/><path d="M4 20a6 6 0 0 1 16 0"/></svg></div><div class="stat-copy"><strong>6</strong><span>Industry profiles</span></div></div>
                <div class="stat-item"><div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18"/></svg></div><div class="stat-copy"><strong>One account</strong><span>Multiple companies</span></div></div>
                <div class="stat-item"><div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6z"/></svg></div><div class="stat-copy"><strong>Role-based</strong><span>Company access</span></div></div>
                <div class="stat-item"><div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M12 3v3M5 10a7 7 0 0 0 14 0"/><path d="M8 17h8"/><path d="M10 21h4"/></svg></div><div class="stat-copy"><strong>Connected</strong><span>Operational workspaces</span></div></div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container split-2">
        <div class="reveal">
            <p class="eyebrow">Who we are</p>
            <h2 class="section-title">A trusted partner for business growth</h2>
            <p class="muted">CashERP was built for real operators — retailers, restaurants, hotels, property managers and service firms — who need one system that actually fits how they work.</p>
            <a class="button button-primary" href="#mission">Our Mission <span>→</span></a>
        </div>
        <div class="reveal reveal-delay photo-frame photo-frame-card">
            <img src="{{ asset('img/landing/dashboard.png') }}" alt="CashERP workspace">
            <div class="overlay-card">
                <strong>Built for Businesses. Designed for People.</strong>
                <span>Simple tools. Real impact.</span>
            </div>
        </div>
    </div>
</section>

<section id="mission" class="section section-soft">
    <div class="container cards-3">
        <article class="info-card reveal"><div class="tile-icon icon-blue">◎</div><h3>Our Mission</h3><p>Make powerful ERP tools accessible to every growing business — without complexity or enterprise pricing.</p></article>
        <article class="info-card reveal"><div class="tile-icon icon-violet">◉</div><h3>Our Vision</h3><p>A smarter Africa where every business runs on clear data, connected teams and confident decisions.</p></article>
        <article class="info-card reveal"><div class="tile-icon icon-orange">◆</div><h3>Our Values</h3><p>Simplicity, reliability, customer success and continuous improvement guide everything we ship.</p></article>
    </div>
</section>

<section id="our-story" class="section">
    <div class="container split-2 split-reverse">
        <div class="reveal photo-frame">
            <img src="{{ asset('img/landing/t2.jpg') }}" alt="Building CashERP">
            <p class="script-note">From Local Businesses to Global Impact.</p>
        </div>
        <div class="reveal reveal-delay">
            <p class="eyebrow">Our story</p>
            <h2 class="section-title">Built from real business needs</h2>
            <p class="muted">CashERP started when founders saw operators juggling spreadsheets, POS apps and accounting tools that never talked to each other. We built one connected workspace instead — and keep refining it with the businesses that use it every day.</p>
            <a class="button button-outline" href="{{ url('/careers') }}">Our Journey <span>→</span></a>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-heading-split">
            <div><h2 class="section-title">More than just software</h2><p class="muted">Why businesses choose CashERP to run and grow.</p></div>
            <a class="text-link" href="{{ url('/') }}#features">See All Features <span>→</span></a>
        </div>
        <div class="cards-4">
            <article class="mini-feature reveal"><div class="tile-icon icon-green">✓</div><h3>Easy to use</h3><p>Clean screens your whole team can learn quickly.</p></article>
            <article class="mini-feature reveal"><div class="tile-icon icon-violet">◎</div><h3>Industry aware</h3><p>Company profiles align modules and terms to the selected industry.</p></article>
            <article class="mini-feature reveal"><div class="tile-icon icon-orange">⚡</div><h3>Guided setup</h3><p>Package, company and industry choices flow into registration.</p></article>
            <article class="mini-feature reveal"><div class="tile-icon icon-blue">🛡</div><h3>Controlled access</h3><p>Company separation and role permissions protect business boundaries.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container soft-cta">
        <div>
            <h2 class="section-title">Build a CashERP workspace around the way your company operates.</h2>
            <p class="muted">Review the published packages and choose the correct industry profile.</p>
        </div>
        <a class="button button-primary button-large" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
    </div>
</section>
@endsection
