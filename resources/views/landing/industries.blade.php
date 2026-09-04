@extends('layouts.landing')

@section('nav_active', 'industries')
@section('footer_active', '')

@section('title', 'Industries | CashERP')
@section('meta_description', 'Purpose-built CashERP workflows for retail, restaurants, hotels, property and professional services.')

@section('content')


<section class="page-hero">
    <div class="container page-hero-grid">
        <div class="page-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Industries</span></nav>
            <h1>Industries built for <em>real businesses</em></h1>
            <p class="hero-lead">Purpose-built ERP workflows for how your industry actually operates — not a one-size-fits-all spreadsheet.</p>
            <ul class="trust-list">
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m8 12 2.6 2.6L16 9"/><circle cx="12" cy="12" r="9"/></svg></span> Industry specific workflows</li>
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></span> Quick setup</li>
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg></span> Fully customizable</li>
            </ul>
        </div>
        <div class="page-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="Business owner using CashERP">
                <p class="script-note">Different Businesses. One Powerful ERP.</p>
                <aside class="float-quote">
                    <p>Choose an industry once for each company.</p>
                    <small>Modules and guidance follow that company profile.</small>
                </aside>
            </div>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container industries-layout">
        <aside class="industry-sidebar reveal">
            <h2>Browse Industries</h2>
            <nav class="industry-nav" aria-label="Industry categories">
                <a class="is-active" href="#all">All Industries</a>
                <a href="#trading">Retail &amp; Trading</a>
                <a href="#restaurant">Food &amp; Hospitality</a>
                <a href="#hotel">Hotels &amp; Lodges</a>
                <a href="#property">Property</a>
                <a href="#services">Professional Services</a>
            </nav>
            <div class="help-box">
                <strong>Need help choosing?</strong>
                <p>Talk to our team and we’ll match the right setup.</p>
                <a class="button button-outline" href="mailto:sales@casherp.com">Contact Sales</a>
            </div>
        </aside>

        <div>
            <div class="browse-toolbar">
                <div>
                    <h2 class="section-title" id="all">All Industries</h2>
                    <p class="muted">Explore purpose-built workflows for every kind of business.</p>
                </div>
                <div class="browse-tools">
                    <label class="search-field"><span class="sr-only">Search industries</span><input type="search" placeholder="Search industries..." data-industry-search></label>
                    <label class="sort-field"><span class="sr-only">Sort</span>
                        <select><option>Sort by: Popular</option><option>A–Z</option></select>
                    </label>
                </div>
            </div>

            <div class="industry-browse-grid" data-industry-grid>
                <article class="browse-card reveal" id="trading" data-name="general business trading">
                    <span class="card-ribbon">Most popular</span>
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/dashboard.png') }}')"></div>
                    <div class="tile-icon icon-blue">▣</div>
                    <h3>General Business &amp; Trading</h3>
                    <p>Sales, purchases, inventory and accounts in one trading workspace.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
                <article class="browse-card reveal" id="restaurant" data-name="restaurant cafe fast food">
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/hero-professional.jpg') }}')"></div>
                    <div class="tile-icon icon-coral">◎</div>
                    <h3>Restaurant, Cafe &amp; Fast Food</h3>
                    <p>POS, kitchen flow, inventory and daily sales reporting.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
                <article class="browse-card reveal" id="hotel" data-name="hotels lodges guest houses">
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/t1.jpg') }}')"></div>
                    <div class="tile-icon icon-orange">⌂</div>
                    <h3>Hotel, Lodge &amp; Guest House</h3>
                    <p>Bookings, rooms, guests and front-desk operations.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
                <article class="browse-card reveal" id="hotel-restaurant" data-name="hotel lodge restaurant">
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/cta-devices.png') }}')"></div>
                    <div class="tile-icon icon-violet">◆</div>
                    <h3>Hotel / Lodge with Restaurant</h3>
                    <p>Combine HMS and F&amp;B in one connected operation.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
                <article class="browse-card reveal" id="property" data-name="property management rentals">
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/t3.jpg') }}')"></div>
                    <div class="tile-icon icon-teal">▤</div>
                    <h3>Property Management &amp; Real Estate</h3>
                    <p>Units, leases, deposits, maintenance and rent collection.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
                <article class="browse-card reveal" id="services" data-name="professional services">
                    <div class="browse-card-media" style="background-image:url('{{ asset('img/landing/t2.jpg') }}')"></div>
                    <div class="tile-icon icon-green">◈</div>
                    <h3>Professional Services</h3>
                    <p>Projects, time, retainers, invoicing and client delivery.</p>
                    <a class="text-link" href="{{ url('/get-started') }}">Explore industry <span>→</span></a>
                </article>
            </div>

            <div class="inline-stats reveal">
                <div><strong>6</strong><span>Selectable profiles</span></div>
                <div><strong>HR</strong><span>Available across all six</span></div>
                <div><strong>Flexible</strong><span>Admin-controlled features</span></div>
                <div><strong>Isolated</strong><span>Company-specific records</span></div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container blue-cta">
        <div>
            <p class="eyebrow light">Ready to get started?</p>
            <h2>Choose your industry and review the right company package.</h2>
            <p>Prices, trials and capacity come from the currently published subscription plans.</p>
            <div class="hero-actions">
                <a class="button button-white button-large" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
                <a class="button button-ghost button-large" href="mailto:sales@casherp.com">Talk to Sales</a>
            </div>
        </div>
        <div class="blue-cta-visual" aria-hidden="true">
            <img src="{{ asset('img/landing/cta-devices.png') }}" alt="">
            <p class="script-note light">Your Business Simplified</p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelector('[data-industry-search]')?.addEventListener('input', function (e) {
    var q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('[data-industry-grid] .browse-card').forEach(function (card) {
        card.hidden = q && !(card.dataset.name || '').includes(q);
    });
});
</script>
@endpush
