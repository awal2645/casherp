@extends('layouts.landing')

@section('nav_active', 'resources')
@section('footer_active', 'resources')

@section('title', 'Resources | CashERP')
@section('meta_description', 'Guides, tutorials, webinars and help articles to learn and grow with CashERP.')

@section('content')


<section class="section resources-shell">
    <div class="container resources-layout">
        <aside class="resource-sidebar reveal">
            <nav class="resource-nav" aria-label="Resource categories">
                <a href="{{ url('/') }}">Home</a>
                <a class="is-active" href="{{ url('/resources') }}">All Resources</a>
                <a href="#types">Help Centre</a>
                <a href="#guides">Guides</a>
                <a href="#guides">Video Tutorials</a>
                <a href="#articles">Blog</a>
                <a href="#articles">Industry Scenarios</a>
                <a href="#guided-sessions">Guided Sessions</a>
                <a href="#types">Product Updates</a>
                <a href="#types">Integration Guidance</a>
                <a href="mailto:support@casherp.com">Support Channels</a>
                <a href="{{ url('/') }}#security">Security Overview</a>
                <a href="#types">FAQs</a>
            </nav>
            <div class="help-box">
                <strong>Need help?</strong>
                <a class="text-link" href="mailto:support@casherp.com">Contact Support <span>→</span></a>
            </div>
            <div class="help-box">
                <strong>Need a support channel?</strong>
                <a class="text-link" href="mailto:support@casherp.com">Ask Support <span>→</span></a>
            </div>
            <div class="sidebar-promo">
                <img src="{{ asset('img/landing/t1.jpg') }}" alt="">
                <p>Behind every business is a team that cares.</p>
                <a class="button button-primary" href="mailto:sales@casherp.com">Talk to an Expert <span>→</span></a>
            </div>
        </aside>

        <div class="resource-main">
            <section class="resource-hero reveal">
                <div>
                    <h1>Learn. Grow. Do More with CashERP.</h1>
                    <label class="search-field wide" id="resource-search"><span class="sr-only">Search resources</span><input type="search" placeholder="Search resources, articles, videos..." aria-describedby="resource-search-note"></label>
                    <p class="resource-search-note" id="resource-search-note">Browse the published guidance below or contact support for a specific workflow.</p>
                    <div class="tag-row">
                        @foreach (['Getting started','POS setup','Invoicing','Inventory','Payroll'] as $tag)
                            <button type="button" class="tag">{{ $tag }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="photo-frame compact">
                    <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="">
                    <p class="script-note">Knowledge Today. A Smarter Tomorrow.</p>
                </div>
            </section>

            <section id="types" class="reveal">
                <h2 class="section-title">Browse by Resource Type</h2>
                <div class="resource-type-grid">
                    @foreach ([
                        ['Help Centre','Answers for everyday tasks'],
                        ['Guides','Step-by-step playbooks'],
                        ['Video Tutorials','Watch and learn quickly'],
                        ['Blog','Product news & tips'],
                        ['Industry Scenarios','Examples for supported workflows'],
                        ['Guided Sessions','Request a focused product session'],
                        ['Product Updates','What shipped recently'],
                        ['Integration Guidance','Understand available connections'],
                        ['Support Channels','Reach the appropriate CashERP team'],
                        ['FAQs','Common questions answered'],
                    ] as $type)
                        <article class="info-card"><div class="tile-icon icon-blue">▣</div><h3>{{ $type[0] }}</h3><p>{{ $type[1] }}</p></article>
                    @endforeach
                </div>
            </section>

            <section id="guides" class="reveal">
                <h2 class="section-title">Featured Resources</h2>
                <div class="featured-resources">
                    <article class="featured-card">
                        <img src="{{ asset('img/landing/dashboard.png') }}" alt="">
                        <div>
                            <span class="eyebrow">Guide</span>
                            <h3>Getting Started with CashERP</h3>
                            <p>Set up your company, invite your team and go live in minutes.</p>
                            <a class="text-link" href="{{ url('/get-started') }}">Read guide <span>→</span></a>
                        </div>
                    </article>
                    <article class="featured-card">
                        <img src="{{ asset('img/landing/cta-devices.png') }}" alt="">
                        <div>
                            <span class="eyebrow">Video</span>
                            <h3>How to Set Up Your Point of Sale</h3>
                            <p>Configure POS for retail and restaurants with confidence.</p>
                            <a class="text-link" href="mailto:support@casherp.com">Watch video <span>→</span></a>
                        </div>
                    </article>
                    <article class="featured-card">
                        <img src="{{ asset('img/landing/t2.jpg') }}" alt="">
                        <div>
                            <span class="eyebrow">Case study</span>
                            <h3>Restaurant POS, inventory and reporting</h3>
                            <p>A practical overview of the connected food-service workflow.</p>
                            <a class="text-link" href="#articles">Explore articles <span>→</span></a>
                        </div>
                    </article>
                </div>
            </section>

            <section class="articles-webinars reveal">
                <div id="articles">
                    <h2 class="section-title">Latest Articles</h2>
                    <div class="article-list">
                        @foreach ([['Inventory best practices','Operations guide'],['Multi-location reporting tips','Reporting guide'],['Working with HR & Payroll','People operations guide'],['Preparing for month-end','Accounting guide']] as $a)
                            <article class="article-row"><img src="{{ asset('img/landing/t3.jpg') }}" alt=""><span><strong>{{ $a[0] }}</strong><small>{{ $a[1] }}</small></span></article>
                        @endforeach
                    </div>
                </div>
                <div id="guided-sessions">
                    <h2 class="section-title">Guided Sessions</h2>
                    <div class="webinar-list">
                        @foreach ([['ON','01','Getting started with CashERP','Request a session'],['ON','02','POS for restaurants','Request a session'],['ON','03','Property rentals walkthrough','Request a session'],['ON','04','Accounting month-end','Request a session']] as $w)
                            <div class="webinar-row">
                                <div class="date-box"><strong>{{ $w[0] }}</strong><span>{{ $w[1] }}</span></div>
                                <div><strong>{{ $w[2] }}</strong><small>{{ $w[3] }}</small></div>
                                <a class="text-link" href="mailto:support@casherp.com">Register <span>→</span></a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="dark-cta compact reveal">
                <div>
                    <h2>Still have questions? Our experts are here to help.</h2>
                    <div class="hero-actions">
                        <a class="button button-white" href="mailto:support@casherp.com">Contact Support <span>→</span></a>
                        <a class="button button-ghost" href="mailto:sales@casherp.com">Book a Consultation <span>→</span></a>
                    </div>
                </div>
                <img src="{{ asset('img/landing/t1.jpg') }}" alt="" class="cta-person">
            </section>
        </div>

        <aside class="resource-aside reveal">
            <div class="aside-card">
                <h3>Quick Links</h3>
                <a href="{{ url('/get-started') }}">Get Started</a>
                <a href="{{ url('/pricing') }}">View Pricing</a>
                <a href="mailto:sales@casherp.com">Watch Demo</a>
                <a href="mailto:support@casherp.com">Contact Support</a>
            </div>
            <div class="aside-card promo">
                <h3>New to CashERP?</h3>
                <img src="{{ asset('img/landing/dashboard.png') }}" alt="">
                <a class="button button-primary" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
            </div>
            <div class="aside-card">
                <h3>Popular Articles</h3>
                <ol class="popular-list">
                    <li>Create your first invoice</li>
                    <li>Add products &amp; stock</li>
                    <li>Invite your team</li>
                    <li>Connect a payment method</li>
                </ol>
            </div>
                <div class="aside-card testimonial">
                    <p><strong>Bring a real workflow.</strong></p>
                    <p>Ask support about onboarding, POS, property, hospitality, accounting or people operations.</p>
            </div>
            <div class="aside-card">
                <h3>Stay Updated</h3>
                <form class="subscribe-form" action="mailto:support@casherp.com" method="get">
                    <input type="email" name="body" placeholder="Email address" required>
                    <button class="button button-primary" type="submit">Request Updates <span>→</span></button>
                </form>
            </div>
        </aside>
    </div>
</section>
@endsection
