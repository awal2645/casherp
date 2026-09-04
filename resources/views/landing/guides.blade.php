@extends('layouts.landing')

@section('nav_active', 'resources')
@section('footer_active', 'guides')

@section('title', 'Guides | CashERP')
@section('meta_description', 'Step-by-step CashERP guides and tutorials — getting started, invoicing, inventory, reports, API and more.')

@section('content')
<section class="guides-hero">
    <div class="container guides-hero-grid">
        <div class="guides-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Guides</span></nav>
            <p class="eyebrow">Help guides</p>
            <h1>Learn. Do. <em>Grow with CashERP</em></h1>
            <p class="hero-lead">Step-by-step guides, tutorials and resources to help you get the most out of CashERP. Whether you're just starting or looking for advanced tips, we've got you covered.</p>
            <label class="guides-search">
                <span class="sr-only">Search guides</span>
                <input type="search" placeholder="Search guides (e.g. setup, invoicing, inventory...)" data-guides-search>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </label>
            <div class="guides-popular-searches">
                <span>Popular searches:</span>
                @foreach (['Getting started', 'Invoicing', 'Inventory', 'Reports', 'API'] as $tag)
                    <button type="button" class="tag" data-guide-tag="{{ strtolower($tag) }}">{{ $tag }}</button>
                @endforeach
            </div>
        </div>
        <div class="guides-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="Learning CashERP on a laptop">
                <p class="script-note">Knowledge Today A Smarter Tomorrow</p>
            </div>
            <aside class="guides-help-card">
                <span class="tile-icon icon-blue" aria-hidden="true">🎧</span>
                <div>
                    <p>Need more help? Visit our Help Centre or contact our support team.</p>
                    <a class="text-link" href="{{ url('/contact') }}">Contact Support <span>→</span></a>
                </div>
            </aside>
        </div>
    </div>
</section>

<section class="section" id="categories">
    <div class="container">
        <div class="section-heading-split reveal">
            <div>
                <h2 class="section-title">Browse Guides by Category</h2>
                <p class="muted">Find the right guide for every part of your business.</p>
            </div>
            <a class="text-link" href="#popular">View All Guides <span>→</span></a>
        </div>
        <div class="guide-category-grid" data-guide-categories>
            @foreach ([
                ['Getting Started', 'Create your workspace and go live fast.', 'icon-blue', '🚀', 'getting started'],
                ['Account & Settings', 'Company profile, preferences and defaults.', 'icon-green', '⚙', 'settings account'],
                ['Sales & Invoicing', 'Quotes, invoices, payments and customers.', 'icon-violet', '📈', 'invoicing sales'],
                ['Inventory', 'Products, stock levels, warehouses and transfers.', 'icon-orange', '📦', 'inventory'],
                ['Users & Permissions', 'Invite teammates and control access roles.', 'icon-pink', '◎', 'users permissions'],
                ['Billing & Payments', 'Plans, renewals, invoices and payment methods.', 'icon-teal', '💳', 'billing payments'],
                ['Reports & Analytics', 'Understand performance with clear reports.', 'icon-sky', '◔', 'reports analytics'],
                ['Integrations', 'Connect payments, messaging and other tools.', 'icon-amber', '🧩', 'integrations api'],
                ['Security & Compliance', 'Permissions, backups and secure practices.', 'icon-green', '🛡', 'security'],
                ['Troubleshooting', 'Fix common issues and get unblocked quickly.', 'icon-coral', '🛟', 'troubleshooting'],
            ] as $cat)
                <article class="guide-cat-card reveal" data-name="{{ $cat[4] }}">
                    <div class="tile-icon {{ $cat[2] }}" aria-hidden="true">{{ $cat[3] }}</div>
                    <h3>{{ $cat[0] }}</h3>
                    <p>{{ $cat[1] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-soft" id="popular">
    <div class="container guides-body">
        <div class="guides-list-col reveal">
            <div class="section-heading-split">
                <div>
                    <h2 class="section-title">Popular Guides</h2>
                    <p class="muted">The guides teams open most often.</p>
                </div>
                <a class="text-link" href="#categories">View All Guides <span>→</span></a>
            </div>
            <div class="popular-guide-list" data-popular-guides>
                @foreach ([
                    ['Creating your CashERP account', 'Set up your company, industry and first workspace.', '5 min read', 'Beginner', 'getting started account'],
                    ['Sending your first invoice', 'Create, customise and send a professional invoice.', '7 min read', 'Beginner', 'invoicing sales'],
                    ['Managing inventory stock', 'Add products, track quantities and avoid stockouts.', '8 min read', 'Intermediate', 'inventory'],
                    ['Adding team members & roles', 'Invite users and assign the right permissions.', '6 min read', 'Beginner', 'users permissions'],
                    ['Understanding reports', 'Read sales, inventory and financial reports with confidence.', '10 min read', 'Intermediate', 'reports analytics'],
                ] as $i => $guide)
                    <a class="popular-guide-row" href="{{ url('/contact') }}" data-name="{{ $guide[4] }}">
                        <span class="guide-num">{{ $i + 1 }}</span>
                        <span class="guide-copy">
                            <strong>{{ $guide[0] }}</strong>
                            <small>{{ $guide[1] }}</small>
                            <span class="guide-meta"><em>{{ $guide[2] }}</em><em>{{ $guide[3] }}</em></span>
                        </span>
                        <span class="guide-chevron" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        </div>

        <aside class="guides-side-col reveal reveal-delay">
            <div class="aside-card">
                <h3>Featured Resources</h3>
                <a class="featured-resource" href="{{ url('/resources') }}#guides"><span class="tile-icon icon-blue">▶</span><span><strong>Video Tutorials</strong><small>Watch and learn step by step</small></span></a>
                <a class="featured-resource" href="{{ url('/resources') }}"><span class="tile-icon icon-violet">📄</span><span><strong>Downloadable PDFs</strong><small>Printable checklists &amp; playbooks</small></span></a>
                <a class="featured-resource" href="{{ url('/resources') }}#guided-sessions"><span class="tile-icon icon-orange">📅</span><span><strong>Guided sessions</strong><small>Request help for a specific workflow</small></span></a>
                <a class="featured-resource" href="{{ url('/resources') }}"><span class="tile-icon icon-green">&lt;/&gt;</span><span><strong>Integration guidance</strong><small>Understand available connections</small></span></a>
            </div>
            <div class="guides-support-card">
                <img src="{{ asset('img/landing/t1.jpg') }}" alt="CashERP support">
                <div>
                    <h3>Still Need Help?</h3>
                    <p>Our support team is here for you.</p>
                    <a class="button button-primary" href="{{ url('/contact') }}">Contact Support <span>→</span></a>
                </div>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="soft-cta guides-bottom-cta reveal">
            <span class="tile-icon icon-blue" aria-hidden="true">🎓</span>
            <div>
                <h2 class="section-title">Get the most out of CashERP</h2>
                <p class="muted">Explore our complete library of guides and become a CashERP expert.</p>
            </div>
            <a class="button button-primary button-large" href="#categories">Browse All Guides <span>→</span></a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    function filterGuides(q) {
        q = (q || '').toLowerCase().trim();
        document.querySelectorAll('[data-guide-categories] .guide-cat-card, [data-popular-guides] .popular-guide-row').forEach(function (el) {
            el.hidden = q && !(el.dataset.name || '').includes(q);
        });
    }
    var input = document.querySelector('[data-guides-search]');
    input?.addEventListener('input', function () { filterGuides(input.value); });
    document.querySelectorAll('[data-guide-tag]').forEach(function (tag) {
        tag.addEventListener('click', function () {
            if (input) input.value = tag.dataset.guideTag;
            filterGuides(tag.dataset.guideTag);
            document.getElementById('popular')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>
@endpush
