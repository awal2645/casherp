<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0052FF">
    <meta name="description" content="CashERP is a cloud ERP for modern businesses. Run sales, inventory, accounting, employees and operations from one connected workspace.">
    <meta property="og:title" content="CashERP | One ERP. Every part of your business.">
    <meta property="og:description" content="Run sales, inventory, accounting, employees and operations from one beautifully connected workspace.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/casherp-landing.css') }}?v=guides44">
    <link rel="stylesheet" href="{{ asset('css/casherp-landing-polish.css') }}?v=v21-merge-1">
    <title>CashERP | One ERP. Every part of your business.</title>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @include('landing.partials.header')

    <main id="main-content">
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy reveal">
                    <p class="eyebrow eyebrow-pill">Cloud ERP for modern businesses</p>
                    <h1>One ERP. <em>Every part of your business.</em></h1>
                    <p class="hero-lead">Run sales, inventory, accounting, employees and operations from one powerful, beautifully connected workspace.</p>
                    <div class="hero-actions">
                        <a class="button button-primary button-large" href="{{ url('/get-started') }}">Get Started <span aria-hidden="true">→</span></a>
                        <a class="button button-outline button-large" href="#industries">Explore Industries</a>
                    </div>
                    <ul class="trust-list">
                        <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.6 2.6L16 9"/></svg></span> Package terms shown before registration</li>
                        <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></span> Guided industry-based setup</li>
                        <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6z"/></svg></span> Role-controlled company access</li>
                    </ul>
                </div>
                <div class="hero-visual reveal reveal-delay">
                    <div class="hero-photo-wrap" aria-hidden="true">
                        <img class="hero-photo" src="{{ asset('img/landing/hero-professional.jpg') }}?v=compose3" alt="">
                        <div class="hero-photo-fade"></div>
                    </div>
                    <div class="dash-frame" aria-hidden="true">
                        <aside class="dash-sidebar">
                            <div class="dash-brand"><span class="dash-mark">C</span><strong>CashERP</strong></div>
                            <ul>
                                <li class="is-active">Dashboard</li>
                                <li>Sales</li>
                                <li>Purchases</li>
                                <li>Inventory</li>
                                <li>Customers</li>
                                <li>Suppliers</li>
                                <li>Accounting</li>
                                <li>Reports</li>
                            </ul>
                        </aside>
                        <div class="dash-main">
                            <div class="dash-topbar">
                                <span class="dash-search">Search anything...</span>
                                <strong>Sample Hospitality Co.</strong>
                                <small class="demo-data-label">Illustrative data</small>
                            </div>
                            <div class="metric-row metric-row-4">
                                <article class="metric-card"><span>Revenue</span><strong>K48,320</strong><em class="up">↑ 14.6%</em></article>
                                <article class="metric-card"><span>Outstanding Invoices</span><strong>K17,950</strong><em>12 invoices</em></article>
                                <article class="metric-card"><span>Total Customers</span><strong>1,250</strong><em class="up">↑ 8.5%</em></article>
                                <article class="metric-card metric-alert"><span>Low Stock Items</span><strong>12</strong><em>Needs attention</em></article>
                            </div>
                            <div class="dash-split">
                                <article class="chart-card">
                                    <div class="panel-head"><strong>Sales Overview</strong><span>This Week ▾</span></div>
                                    <div class="chart-wrap">
                                        <div class="chart-tip">K48,320 · May 7</div>
                                        <svg class="line-chart" viewBox="0 0 420 140" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="salesFill" x1="0" x2="0" y1="0" y2="1">
                                                    <stop offset="0%" stop-color="#0052FF" stop-opacity=".22"/>
                                                    <stop offset="100%" stop-color="#0052FF" stop-opacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <g stroke="#E8EEF6" stroke-width="1">
                                                <line x1="36" y1="18" x2="408" y2="18"/>
                                                <line x1="36" y1="48" x2="408" y2="48"/>
                                                <line x1="36" y1="78" x2="408" y2="78"/>
                                                <line x1="36" y1="108" x2="408" y2="108"/>
                                            </g>
                                            <g fill="#9AA8B8" font-size="10" font-family="Inter, Arial, sans-serif">
                                                <text x="4" y="22">60k</text>
                                                <text x="4" y="52">40k</text>
                                                <text x="4" y="82">20k</text>
                                                <text x="10" y="112">0</text>
                                            </g>
                                            <path d="M36 96 C78 90 100 70 130 74 C168 80 190 42 236 48 C278 54 310 28 352 34 C378 38 396 22 408 26 L408 118 L36 118 Z" fill="url(#salesFill)"/>
                                            <path d="M36 96 C78 90 100 70 130 74 C168 80 190 42 236 48 C278 54 310 28 352 34 C378 38 396 22 408 26" fill="none" stroke="#0052FF" stroke-width="3.2" stroke-linecap="round"/>
                                            <g fill="#fff" stroke="#0052FF" stroke-width="2.5">
                                                <circle cx="36" cy="96" r="4.5"/>
                                                <circle cx="100" cy="72" r="4.5"/>
                                                <circle cx="168" cy="78" r="4.5"/>
                                                <circle cx="236" cy="48" r="5.5"/>
                                                <circle cx="310" cy="30" r="4.5"/>
                                                <circle cx="352" cy="34" r="4.5"/>
                                                <circle cx="408" cy="26" r="4.5"/>
                                            </g>
                                            <line x1="236" y1="48" x2="236" y2="118" stroke="#0052FF" stroke-width="1.5" stroke-dasharray="3 3" opacity=".45"/>
                                        </svg>
                                    </div>
                                    <div class="chart-days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                                </article>
                                <article class="activity-card">
                                    <div class="panel-head"><strong>Recent Activities</strong></div>
                                    <ul>
                                        <li><b>INV-2048 paid</b><span>K18,450</span></li>
                                        <li><b>PO-1054 received</b><span>Stock in</span></li>
                                        <li><b>New customer</b><span>Just now</span></li>
                                        <li><b>Low stock alert</b><span>12 items</span></li>
                                    </ul>
                                </article>
                            </div>
                        </div>
                    </div>
                    <div class="hero-toast" role="status">
                        <span class="toast-check">✓</span>
                        <div><strong>Invoice #INV-2048 paid successfully</strong><small>+ K18,450</small></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-wrap" aria-label="Platform highlights">
            <div class="container">
                <div class="stats-bar">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="7"/><path d="M12 10v3.5l2 1.2M9 3.5h6M12 3.5V5"/></svg>
                            </span>
                            <div class="stat-copy"><strong>Guided</strong><span>Step-by-step setup</span></div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10l4-3v14M9 21V7l6-3v17M15 21V11l4 2v8"/></svg>
                            </span>
                            <div class="stat-copy"><strong>6</strong><span>Selectable industry profiles</span></div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M7 17a4.5 4.5 0 0 1 .35-8.9A5.5 5.5 0 0 1 18 9.8 3.8 3.8 0 0 1 18.2 17Z"/><path d="m9.8 12.6 1.6 1.6 3.2-3.2"/></svg>
                            </span>
                            <div class="stat-copy"><strong>Cloud</strong><span>Responsive web access</span></div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            <div class="stat-copy"><strong>One account</strong><span>Multiple businesses</span></div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M4.5 14a7.5 7.5 0 0 1 15 0"/><path d="M4.5 14v2.5A2.5 2.5 0 0 0 7 19h1.2v-5H7a2.5 2.5 0 0 0-2.5 2.5z"/><path d="M19.5 14v2.5A2.5 2.5 0 0 1 17 19h-1.2v-5H17a2.5 2.5 0 0 1 2.5 2.5z"/><circle cx="12" cy="10" r="1"/><path d="M12 19v2"/></svg>
                            </span>
                            <div class="stat-copy"><strong>Support</strong><span>Guidance when you need it</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="section section-steps">
            <div class="container">
                <div class="section-heading reveal">
                    <h2>Get your business running in 4 simple steps</h2>
                    <p>Fast, easy and hassle-free.</p>
                </div>
                <div class="steps-row">
                    <article class="step-card reveal">
                        <div class="step-badge">01</div>
                        <div class="step-mini">
                            <label>Business Name</label>
                            <div class="mini-input">Mungo Villas Ltd</div>
                            <label>Currency</label>
                            <div class="mini-input">ZMW — Zambian Kwacha</div>
                        </div>
                        <h3>Create your company</h3>
                        <p>Add your business details, currency, tax and preferences.</p>
                    </article>
                    <div class="step-arrow" aria-hidden="true">→</div>
                    <article class="step-card reveal">
                        <div class="step-badge">02</div>
                        <div class="step-mini mini-industry">
                            <ul>
                                <li class="is-on"><span>🍽</span> Restaurant</li>
                                <li><span>🛍</span> Retail</li>
                                <li><span>🏨</span> Hotel</li>
                            </ul>
                        </div>
                        <h3>Choose your industry</h3>
                        <p>We'll configure the right workspace for your business.</p>
                    </article>
                    <div class="step-arrow" aria-hidden="true">→</div>
                    <article class="step-card reveal">
                        <div class="step-badge">03</div>
                        <div class="step-mini mini-team">
                            <div class="avatars">
                                <img class="av" src="{{ asset('img/landing/t1.jpg') }}" alt="">
                                <img class="av" src="{{ asset('img/landing/t2.jpg') }}" alt="">
                                <img class="av" src="{{ asset('img/landing/t3.jpg') }}" alt="">
                                <span class="av av-plus">+</span>
                            </div>
                        </div>
                        <h3>Invite your team</h3>
                        <p>Add your team members and set roles &amp; permissions.</p>
                    </article>
                    <div class="step-arrow" aria-hidden="true">→</div>
                    <article class="step-card reveal">
                        <div class="step-badge">04</div>
                        <div class="step-mini mini-ops">
                            <small>Today's Revenue</small>
                            <strong>K48,320</strong>
                            <svg viewBox="0 0 120 36" aria-hidden="true"><path d="M0 28 C20 24 30 16 48 18 C66 20 78 8 96 10 C108 11 114 6 120 4" fill="none" stroke="#0052FF" stroke-width="3"/></svg>
                        </div>
                        <h3>Start operating</h3>
                        <p>Sell, buy, manage stock, track finances. You're all set!</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="industries" class="section section-soft">
            <div class="container">
                <div class="section-heading section-heading-split reveal">
                    <div>
                        <h2>Built for every industry</h2>
                        <p>Purpose-built workflows. Real business results.</p>
                    </div>
                    <a class="text-link" href="{{ url('/industries') }}">View all industries <span>→</span></a>
                </div>
                <div class="industry-grid industry-grid-6">
                    <article class="industry-card reveal" id="industry-trading">
                        <span class="industry-icon icon-blue"><svg viewBox="0 0 24 24"><circle cx="8" cy="19" r="2"/><circle cx="18" cy="19" r="2"/><path d="M3 5h3l2.2 9h10.3l2-6H8"/></svg></span>
                        <h3>General Business &amp; Trading</h3>
                        <p>POS, quotations, purchases, inventory and accounting.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                    <article class="industry-card reveal" id="industry-restaurant">
                        <span class="industry-icon icon-orange"><svg viewBox="0 0 24 24"><path d="M7 3v8M4 3v5c0 2 1 3 3 3s3-1 3-3V3M7 11v10M15 3v18M15 3c4 2 5 6 5 9h-5"/></svg></span>
                        <h3>Restaurant, Cafe &amp; Fast Food</h3>
                        <p>Menus, tables, kitchen orders and food stock.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                    <article class="industry-card reveal" id="industry-hotel">
                        <span class="industry-icon icon-violet"><svg viewBox="0 0 24 24"><path d="M3 18V11h15a3 3 0 0 1 3 3v4"/><path d="M3 18h18"/><path d="M7 11V7a3 3 0 0 1 6 0v4"/><path d="M3 21v-3"/></svg></span>
                        <h3>Hotel, Lodge &amp; Guest House</h3>
                        <p>Rooms, guests, bookings and housekeeping.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                    <article class="industry-card reveal" id="industry-hotel-restaurant">
                        <span class="industry-icon icon-coral"><svg viewBox="0 0 24 24"><path d="M3 19V12h10v7"/><path d="M3 19h18"/><path d="M17 8v3M20 5v6M17 5c2 1 3 3 3 5"/><path d="M7 12V8h5v4"/></svg></span>
                        <h3>Hotel / Lodge with Restaurant</h3>
                        <p>Hotel tools plus restaurant POS together. HMS and HRM remain separate modules.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                    <article class="industry-card reveal" id="industry-property">
                        <span class="industry-icon icon-green"><svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8M5 10v11h14V10M9 21v-6h6v6"/></svg></span>
                        <h3>Property Management &amp; Rentals</h3>
                        <p>Units, tenants, leases, rent and maintenance.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                    <article class="industry-card reveal" id="industry-services">
                        <span class="industry-icon icon-indigo"><svg viewBox="0 0 24 24"><rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7V5h6v2"/></svg></span>
                        <h3>Professional Services</h3>
                        <p>CRM, proposals, projects and invoices.</p>
                        <a class="text-link" href="{{ url('/business/register') }}">Explore workspace <span>→</span></a>
                    </article>
                </div>
            </div>
        </section>

        <section id="features" class="section section-dark">
            <div class="container features-split">
                <div class="feature-intro reveal">
                    <p class="eyebrow">All core modules. One seamless experience.</p>
                    <h2>Everything you need. All in one place.</h2>
                    <p>CashERP brings every part of your business together so you can save time, reduce errors and make smarter decisions.</p>
                    <a class="button button-primary button-large" href="{{ url('/business/register') }}">Explore all features <span>→</span></a>
                </div>
                <div class="feature-grid-9">
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-blue"><svg viewBox="0 0 24 24"><path d="M4 10h16v10H4z"/><path d="M8 10V7l4-3 4 3v3"/></svg></span>
                        <div><h3>Sales &amp; CRM</h3><p>Manage leads, customers, quotations &amp; invoices.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-green"><svg viewBox="0 0 24 24"><path d="M4 8h16v11H4zM8 8V6h8v2"/></svg></span>
                        <div><h3>Inventory</h3><p>Track stock in real-time across locations.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-orange"><svg viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11h11.2l2-7H7"/></svg></span>
                        <div><h3>Purchasing</h3><p>Manage suppliers, PO's and receive goods.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-bronze"><svg viewBox="0 0 24 24"><circle cx="6" cy="8" r="2.2"/><circle cx="18" cy="8" r="2.2"/><circle cx="12" cy="16" r="2.2"/><path d="M8 9.2 10.4 14.4M16 9.2 13.6 14.4"/></svg></span>
                        <div><h3>Multi-branch</h3><p>Manage multiple locations from one account.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-gold"><svg viewBox="0 0 24 24"><path d="M5 9h14v10H5z"/><path d="M8 9V7a4 4 0 0 1 8 0v2"/><path d="M12 13v3"/></svg></span>
                        <div><h3>Accounting</h3><p>General ledger, expenses, bank &amp; cash management.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-teal"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                        <div><h3>HR &amp; Payroll</h3><p>Employees, attendance, payroll &amp; leave.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-violet"><svg viewBox="0 0 24 24"><path d="M4 19h16M7 16V9m5 7V6m5 10v-4"/></svg></span>
                        <div><h3>Reports &amp; Analytics</h3><p>Powerful reports that help you grow.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h reveal">
                        <span class="tile-icon icon-sky"><svg viewBox="0 0 24 24"><path d="M4 16 9 9l4 4 3-5 4 8"/><circle cx="16" cy="8" r="1.5"/></svg></span>
                        <div><h3>Integrations</h3><p>Connect with your favorite apps and services.</p></div>
                    </article>
                    <article class="feature-tile feature-tile-h feature-security reveal" id="security">
                        <span class="tile-icon icon-coral"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6z"/><path d="m9.5 11.5 1.8 1.8 3.4-3.4"/></svg></span>
                        <div><h3>Security</h3><p>Role-based access and bank-level security.</p></div>
                    </article>
                </div>
            </div>
        </section>

        <section id="multi-business" class="section section-soft proof-section">
            <div class="container proof-stack">
                <div class="multi-grid reveal">
                    <div class="multi-copy">
                        <p class="eyebrow">One login, many businesses.</p>
                        <h2>Run multiple businesses from a single account.</h2>
                        <ul class="check-list">
                            <li><span>✓</span> Completely separate data for each business</li>
                            <li><span>✓</span> Separate teams, roles and permissions</li>
                            <li><span>✓</span> Separate accounting, inventory and reports</li>
                            <li><span>✓</span> Instant switching between companies</li>
                            <li><span>✓</span> Consolidated view for business owners</li>
                        </ul>
                    </div>
                    <div class="command-visual">
                        <div class="command-stage">
                            <aside class="companies-card">
                                <strong>Your Companies</strong>
                                <div class="company-row is-active">
                                    <span class="co-avatar av-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10l8-6 8 6v10"/><path d="M9 20v-6h6v6"/></svg></span>
                                    <div><b>Mungo Villas Ltd</b><small>Hospitality</small></div>
                                    <span class="co-check">✓</span>
                                </div>
                                <div class="company-row">
                                    <span class="co-avatar av-coral" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><rect x="5" y="8" width="14" height="12" rx="2"/></svg></span>
                                    <div><b>Prime Retail Ltd</b><small>Retail</small></div>
                                    <span class="co-check co-check-mute">✓</span>
                                </div>
                                <div class="company-row">
                                    <span class="co-avatar av-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4c0 3 2 5 4 7 2-2 4-4 4-7"/><path d="M7 21h10"/><path d="M12 11v10"/></svg></span>
                                    <div><b>Cash Foods</b><small>Restaurant</small></div>
                                    <span class="co-check co-check-mute">✓</span>
                                </div>
                                <button class="add-company" type="button">+ Add New Company</button>
                            </aside>
                            <div class="switch-orb" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8h11l-3-3M17 16H6l3 3"/></svg>
                            </div>
                            <div class="command-main-card">
                                <div class="panel-head"><strong>Business Command Centre</strong></div>
                                <div class="command-body">
                                    <article class="metric-card">
                                        <span>Total Revenue</span>
                                        <div class="metric-line"><strong>K128,850</strong><em>↑ 18.6%</em></div>
                                        <svg class="spark spark-blue" viewBox="0 0 72 24"><path d="M1 18 C12 16 18 8 30 10 C42 12 50 4 71 6"/></svg>
                                    </article>
                                    <article class="metric-card">
                                        <span>Total Cash In</span>
                                        <div class="metric-line"><strong>K86,240</strong><em>↑ 8.3%</em></div>
                                        <svg class="spark spark-coral" viewBox="0 0 72 24"><path d="M1 16 C16 14 24 8 36 10 C50 12 58 5 71 7"/></svg>
                                    </article>
                                    <article class="metric-card">
                                        <span>Total Profit</span>
                                        <div class="metric-line"><strong>K42,610</strong><em>↑ 22.1%</em></div>
                                        <svg class="spark spark-green" viewBox="0 0 72 24"><path d="M1 14 C14 16 22 8 34 10 C48 12 56 6 71 8"/></svg>
                                    </article>
                                    <article class="income-card">
                                        <strong>Income by Source</strong>
                                        <div class="income-row">
                                            <svg viewBox="0 0 120 120" aria-hidden="true">
                                                <circle cx="60" cy="60" r="38" fill="none" stroke="#E8EEF6" stroke-width="14"/>
                                                <circle cx="60" cy="60" r="38" fill="none" stroke="#0052FF" stroke-width="14" stroke-dasharray="143 239" stroke-linecap="round"/>
                                                <circle cx="60" cy="60" r="38" fill="none" stroke="#FF8A3D" stroke-width="14" stroke-dasharray="48 239" stroke-dashoffset="-145" stroke-linecap="round"/>
                                                <circle cx="60" cy="60" r="38" fill="none" stroke="#1DBF73" stroke-width="14" stroke-dasharray="24 239" stroke-dashoffset="-195" stroke-linecap="round"/>
                                                <circle cx="60" cy="60" r="38" fill="none" stroke="#1B3A6B" stroke-width="14" stroke-dasharray="24 239" stroke-dashoffset="-221" stroke-linecap="round"/>
                                            </svg>
                                            <ul>
                                                <li><i class="c-blue"></i> Sales <b>60%</b></li>
                                                <li><i class="c-orange"></i> Services <b>20%</b></li>
                                                <li><i class="c-green"></i> Rentals <b>10%</b></li>
                                                <li><i class="c-navy"></i> Others <b>10%</b></li>
                                            </ul>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="stories" class="stories-split reveal">
                    <div class="trust-block">
                        <p class="eyebrow">Designed for businesses like yours</p>
                        <div class="logo-grid">
                            <div class="logo-chip logo-mungo">
                                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10l8-6 8 6v10"/><path d="M9 20v-6h6v6"/></svg></span>
                                <div><b>Hospitality</b><small>Rooms, stays and events</small></div>
                            </div>
                            <div class="logo-chip logo-prime">
                                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M3 12h2M19 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
                                <div><b>General Business</b><small>Sales, stock and finance</small></div>
                            </div>
                            <div class="logo-chip logo-green">
                                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6z"/></svg></span>
                                <div><b>Property</b><small>Units, leases and tenants</small></div>
                            </div>
                            <div class="logo-chip logo-cash">
                                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 4c-2 3-5 4.5-5 8a5 5 0 0 0 10 0c0-3.5-3-5-5-8z"/><path d="M9 14c1 2 2 3 3 5 1-2 2-3 3-5"/></svg></span>
                                <div><b>Restaurant</b><small>POS, tables and kitchens</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="stories-panel">
                        <div class="story-grid">
                            <article class="story-card">
                                <img class="story-avatar" src="{{ asset('img/landing/t1.jpg') }}" alt="Hospitality workspace example">
                                <div class="story-body">
                                    <p>Manage bookings, front desk, accommodation documents and events while keeping hotel operations separate from HR.</p>
                                    <div class="story-meta"><strong>Hospitality workflow</strong><small>Hotel, lodge and guest house</small></div>
                                </div>
                            </article>
                            <article class="story-card">
                                <img class="story-avatar" src="{{ asset('img/landing/t2.jpg') }}" alt="Restaurant workspace example">
                                <div class="story-body">
                                    <p>Coordinate POS, tables, kitchen tickets, recipes, stock and settlement from one food-service workspace.</p>
                                    <div class="story-meta"><strong>Restaurant workflow</strong><small>Café, fast food and restaurant</small></div>
                                </div>
                            </article>
                            <article class="story-card">
                                <img class="story-avatar" src="{{ asset('img/landing/t3.jpg') }}" alt="Property workspace example">
                                <div class="story-body">
                                    <p>Structure properties, units, tenants, leases, deposits, maintenance and collection histories by company and location.</p>
                                    <div class="story-meta"><strong>Property workflow</strong><small>Commercial and residential real estate</small></div>
                                </div>
                            </article>
                        </div>
                        <div class="stories-link"><a class="text-link" href="{{ url('/industries') }}">Explore all six profiles <span>→</span></a></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="cta" class="cta-wrap">
            <div class="container">
                <div class="cta-banner reveal">
                    <div class="cta-devices" aria-hidden="true">
                        <img src="{{ asset('img/landing/cta-devices.png') }}?v=19" alt="" width="520" height="324">
                    </div>
                    <div class="cta-copy">
                        <h2>Take control of your business today with CashERP.</h2>
                        <p>Choose an industry, compare the currently published packages and create a company workspace with clear capacity limits.</p>
                    </div>
                    <div class="cta-actions">
                        <a class="button button-white button-large" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
                        <a class="button button-ghost button-large" href="mailto:sales@casherp.com">Talk to Sales</a>
                    </div>
                    <div class="cta-side">
                        <ul class="cta-checks">
                            <li><span>✓</span> Transparent package allowances</li>
                            <li><span>✓</span> Industry-specific feature profile</li>
                            <li><span>✓</span> Separate data for every company</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('landing.partials.footer')
    <script src="{{ asset('js/casherp-landing.js') }}?v=pages41" defer></script>
</body>
</html>
