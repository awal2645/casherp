@php
    $navActive = trim($__env->yieldContent('nav_active'));
@endphp
<header class="site-header" data-site-header>
    <div class="container nav-shell">
        <a class="brand" href="{{ url('/') }}" aria-label="CashERP home">
            <img src="{{ asset('img/landing/casherp-logo.png') }}" alt="CashERP" width="149" height="42">
        </a>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle>
            <span></span><span></span><span></span>
        </button>
        <nav id="primary-navigation" class="primary-nav" aria-label="Primary navigation" data-menu>
            <div class="nav-item nav-item-mega" data-dropdown>
                <button class="nav-link {{ $navActive === 'product' ? 'is-active' : '' }}" type="button" aria-expanded="false" aria-haspopup="true">Product <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5"/></svg></button>
                <div class="mega-menu" role="menu">
                    <div class="mega-col">
                        <h3>Core modules</h3>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-blue">▣</span><span><strong>Dashboard</strong><small>Real-time business overview</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-coral">◎</span><span><strong>Sales &amp; CRM</strong><small>Pipeline, quotes &amp; invoices</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-orange">▤</span><span><strong>Purchases</strong><small>Orders, bills &amp; suppliers</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-green">▦</span><span><strong>Inventory</strong><small>Stock, warehouses &amp; transfers</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-violet">◈</span><span><strong>Accounting</strong><small>Books, VAT &amp; reports</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-pink">☺</span><span><strong>HR &amp; Payroll</strong><small>People, attendance &amp; pay</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-teal">⬡</span><span><strong>Projects</strong><small>Tasks, time &amp; delivery</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-amber">▣</span><span><strong>Assets</strong><small>Track &amp; depreciate assets</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-sky">◔</span><span><strong>Reports &amp; Analytics</strong><small>Insights that drive growth</small></span></a>
                    </div>
                    <div class="mega-col">
                        <h3>Business essentials</h3>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-blue">⊞</span><span><strong>Point of Sale</strong><small>Fast checkout for retail</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-green">▭</span><span><strong>Invoicing</strong><small>Professional invoices fast</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-orange">◈</span><span><strong>Expenses</strong><small>Capture &amp; categorise spend</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-violet">⇄</span><span><strong>Banking &amp; Reconciliation</strong><small>Match statements easily</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-teal">⌂</span><span><strong>Multi-branch Management</strong><small>One account, many locations</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-pink">⛓</span><span><strong>Integrations</strong><small>Connect the tools you use</small></span></a>
                        <a href="{{ url('/') }}#features"><span class="mega-ico icon-sky">▣</span><span><strong>Smart Documents</strong><small>Create, preview, print &amp; share</small></span></a>
                        <a href="{{ url('/') }}#security"><span class="mega-ico icon-green">🛡</span><span><strong>Security &amp; Permissions</strong><small>Role-based access control</small></span></a>
                    </div>
                    <div class="mega-promo">
                        <span class="mega-badge">New</span>
                        <h4>A complete ERP for growing businesses.</h4>
                        <div class="mega-promo-visual" aria-hidden="true">
                            <img src="{{ asset('img/landing/dashboard.png') }}" alt="">
                        </div>
                        <a class="button button-primary" href="{{ url('/') }}#features">View all features <span>→</span></a>
                        <a class="text-link" href="{{ url('/resources') }}">See how it works <span>→</span></a>
                    </div>
                </div>
            </div>
            <div class="nav-item" data-dropdown>
                <button class="nav-link {{ $navActive === 'industries' ? 'is-active' : '' }}" type="button" aria-expanded="false" aria-haspopup="true">Industries <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5"/></svg></button>
                <div class="dropdown" role="menu">
                    <a href="{{ url('/industries') }}">All Industries</a>
                    <a href="{{ url('/industries') }}#trading">General Business &amp; Trading</a>
                    <a href="{{ url('/industries') }}#restaurant">Restaurant, Café &amp; Fast Food</a>
                    <a href="{{ url('/industries') }}#hotel">Hotels, Lodges &amp; Guest Houses</a>
                    <a href="{{ url('/industries') }}#property">Property Management</a>
                    <a href="{{ url('/industries') }}#services">Professional Services</a>
                </div>
            </div>
            <div class="nav-item" data-dropdown>
                <button class="nav-link {{ $navActive === 'solutions' ? 'is-active' : '' }}" type="button" aria-expanded="false" aria-haspopup="true">Solutions <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5"/></svg></button>
                <div class="dropdown" role="menu">
                    <a href="{{ url('/') }}#multi-business">Multi-company accounts</a>
                    <a href="{{ url('/') }}#how-it-works">Guided setup</a>
                    <a href="mailto:sales@casherp.com">Talk to sales</a>
                </div>
            </div>
            <div class="nav-item" data-dropdown>
                <button class="nav-link {{ $navActive === 'resources' ? 'is-active' : '' }}" type="button" aria-expanded="false" aria-haspopup="true">Resources <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5"/></svg></button>
                <div class="dropdown" role="menu">
                    <a href="{{ url('/resources') }}">All Resources</a>
                    <a href="{{ url('/guides') }}">Guides &amp; tutorials</a>
                    <a href="{{ url('/resources') }}#articles">Blog &amp; articles</a>
                    <a href="{{ url('/resources') }}#guided-sessions">Guided sessions</a>
                    <a href="mailto:support@casherp.com">Help Centre</a>
                </div>
            </div>
            <a class="nav-link {{ $navActive === 'pricing' ? 'is-active' : '' }}" href="{{ url('/pricing') }}">Pricing</a>
        </nav>
        <div class="nav-actions">
            <a class="nav-search" href="{{ url('/resources') }}#resource-search" aria-label="Search CashERP resources">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </a>
            @auth
                <a class="nav-login" href="{{ url('/home') }}">Dashboard</a>
                <a class="button button-primary" href="{{ url('/home') }}">Open app <span aria-hidden="true">→</span></a>
            @else
                <a class="nav-login nav-login-outline" href="{{ url('/login') }}">Log in</a>
                <a class="button button-primary" href="{{ url('/get-started') }}">Get Started <span aria-hidden="true">→</span></a>
            @endauth
        </div>
    </div>
</header>
