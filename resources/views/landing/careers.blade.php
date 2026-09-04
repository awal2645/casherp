@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', 'careers')

@section('title', 'Careers | CashERP')
@section('meta_description', 'Learn about CashERP career areas and contact the team to ask about currently confirmed vacancies.')

@section('content')


<section class="page-hero">
    <div class="container page-hero-grid">
        <div class="page-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Careers</span></nav>
            <p class="eyebrow">Careers at CashERP</p>
            <h1>Build Your Future With Us</h1>
            <p class="hero-lead">Join a team empowering businesses across Africa with modern cloud ERP — and grow your career while you do it.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="#positions">Career Enquiries <span>→</span></a>
                <a class="button button-outline button-large" href="#life">Life at CashERP</a>
            </div>
        </div>
        <div class="page-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="CashERP team">
                <p class="script-note">Great People Build Great Products.</p>
            </div>
        </div>
    </div>
    <div class="container hero-feature-bar">
        <div><strong>Meaningful Work</strong><span>Solve real business challenges.</span></div>
        <div><strong>Grow Your Skills</strong><span>Learn, develop and advance.</span></div>
        <div><strong>Be Part of Impact</strong><span>Help businesses and communities thrive.</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-title">Why Work at CashERP?</h2>
            <p>We care about craft, customers and each other.</p>
        </div>
        <div class="cards-4">
            <article class="info-card tint-green reveal"><div class="tile-icon icon-green">💡</div><h3>Useful Innovation</h3><p>Focus product work on clear customer and operational needs.</p></article>
            <article class="info-card tint-violet reveal"><div class="tile-icon icon-violet">◎</div><h3>Open Collaboration</h3><p>Work clearly across product, engineering and customer operations.</p></article>
            <article class="info-card tint-orange reveal"><div class="tile-icon icon-orange">📈</div><h3>Professional Growth</h3><p>Develop practical skills by solving multi-industry business problems.</p></article>
            <article class="info-card tint-blue reveal"><div class="tile-icon icon-blue">🌍</div><h3>Real Impact</h3><p>Your work helps businesses run and grow every day.</p></article>
        </div>
    </div>
</section>

<section id="positions" class="section section-soft">
    <div class="container">
        <div class="section-heading left">
            <h2 class="section-title">Career Areas</h2>
            <p>Ask the CashERP team which vacancies are currently approved before applying.</p>
        </div>
        <div class="job-list" data-job-list>
            @foreach ([
                ['Software Engineering', 'Product & Engineering', 'Build and maintain dependable Laravel, React and integration workflows.'],
                ['Product & Experience', 'Product & Design', 'Translate real operating needs into clear ERP experiences.'],
                ['Customer Enablement', 'Support & Onboarding', 'Help companies configure and use their industry workspaces.'],
                ['Growth & Partnerships', 'Commercial', 'Develop responsible market, implementation and partner programmes.'],
            ] as $job)
                <article class="job-card" data-name="{{ strtolower($job[0].' '.$job[1]) }}">
                    <div>
                        <div class="job-meta">
                            <h3>{{ $job[0] }}</h3>
                            <span class="pill-green">Career area</span>
                        </div>
                        <p class="job-loc">{{ $job[1] }}</p>
                        <p>{{ $job[2] }}</p>
                    </div>
                    <a class="button button-outline" href="mailto:careers@casherp.com?subject={{ urlencode('CashERP career enquiry: '.$job[0]) }}">Ask About Vacancies <span>→</span></a>
                </article>
            @endforeach
        </div>
        <div class="soft-banner">
            <div>
                <strong>Confirm before applying</strong>
                <p>Send a short enquiry to learn which roles, locations and engagement types are currently open.</p>
            </div>
            <a class="button button-outline" href="mailto:careers@casherp.com?subject=CashERP%20career%20enquiry">Contact Careers <span>→</span></a>
        </div>
    </div>
</section>

<section id="life" class="section">
    <div class="container">
        <div class="section-heading">
            <h2 class="section-title">Life at CashERP</h2>
            <p>Collaborate, innovate, grow and succeed together.</p>
        </div>
        <div class="life-grid">
            @foreach ([['Collaborate', 't1.jpg'], ['Innovate', 'dashboard.png'], ['Grow', 't2.jpg'], ['Succeed', 't3.jpg']] as $item)
                <a class="life-card" href="#positions" style="background-image:url('{{ asset('img/landing/'.$item[1]) }}')">
                    <span>{{ $item[0] }} <em>→</em></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endsection
