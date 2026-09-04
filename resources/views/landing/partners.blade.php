@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', 'partners')

@section('title', 'Partners | CashERP')
@section('meta_description', 'Partner with CashERP — technology, referral, reseller and implementation partnerships across Africa.')

@section('content')


<section class="page-hero">
    <div class="container page-hero-grid">
        <div class="page-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Partners</span></nav>
            <p class="eyebrow">Our partners</p>
            <h1><em>Stronger Together</em> Building a Smarter Future</h1>
            <p class="hero-lead">We collaborate with technology leaders, consultants and implementers to help businesses grow with CashERP.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="mailto:partners@casherp.com">Become a Partner <span>→</span></a>
                <a class="button button-outline button-large" href="{{ url('/login') }}">Partner Login</a>
            </div>
        </div>
        <div class="page-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="Partnership handshake">
                <p class="script-note">Partnerships for a Brighter Tomorrow</p>
                <aside class="float-list">
                    <div><span>✓</span> More opportunities</div>
                    <div><span>✓</span> Greater innovation</div>
                    <div><span>✓</span> Bigger impact</div>
                </aside>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-title">Why Partner With CashERP?</h2>
            <p>Grow together with a platform built for real African businesses.</p>
        </div>
        <div class="cards-4">
            <article class="info-card reveal"><div class="tile-icon icon-green">📈</div><h3>Grow Your Business</h3><p>Reach more customers with a modern ERP they can adopt quickly.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-violet">◎</div><h3>Co-innovate</h3><p>Shape integrations and solutions with our product team.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-orange">🤝</div><h3>Shared success</h3><p>Referral and reseller models that reward outcomes.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-blue">🌍</div><h3>Regional impact</h3><p>Help more businesses digitise operations across Africa.</p></article>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-title">Our Partner Types</h2>
            <p>Choose the partnership model that fits how you work.</p>
        </div>
        <div class="cards-4">
            <article class="info-card reveal"><div class="tile-icon icon-blue">⇄</div><h3>Technology Partners</h3><p>Integrate payments, messaging, cloud and fintech services.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-green">☺</div><h3>Referral Partners</h3><p>Introduce CashERP and earn when customers succeed.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-orange">⌂</div><h3>Reseller Partners</h3><p>Sell, package and support CashERP for your clients.</p></article>
            <article class="info-card reveal"><div class="tile-icon icon-violet">🎓</div><h3>Implementation Partners</h3><p>Onboard, train and customise industry workflows.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading-split">
            <h2 class="section-title">Our Trusted Partners</h2>
            <a class="text-link" href="mailto:partners@casherp.com">Become a Partner <span>→</span></a>
        </div>
        <div class="partner-logo-grid" aria-label="Partnership capability areas">
            @foreach (['Implementation','Training','Cloud operations','Payments','Messaging','Accounting','Data import','Local onboarding','Support','Security','Hospitality','Property'] as $capability)
                <div class="partner-logo-chip">{{ $capability }}</div>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container split-2">
        <div class="photo-frame reveal">
            <img src="{{ asset('img/landing/t1.jpg') }}" alt="David Mwangi">
            <p class="script-note">“Together we empower businesses.”</p>
        </div>
        <div class="reveal reveal-delay">
            <p class="eyebrow">Partner success story</p>
            <h2 class="section-title">Creating Greater Opportunities</h2>
            <p class="muted">Our partners help businesses go live faster with industry templates, local support and trusted integrations — turning complex ERP rollouts into confident launches.</p>
            <a class="button button-outline" href="{{ url('/resources') }}#articles">Read Full Story <span>→</span></a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container dark-cta">
        <div>
            <h2>Let's Build the Future Together</h2>
            <p>Join a growing partner network helping businesses run smarter.</p>
        </div>
        <a class="button button-white button-large" href="mailto:partners@casherp.com">Become a Partner <span>→</span></a>
        <p class="script-note light">Global Partnerships Local Impact</p>
    </div>
</section>
@endsection
