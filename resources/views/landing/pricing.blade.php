@extends('layouts.landing')

@section('nav_active', 'pricing')
@section('footer_active', '')

@section('title', 'Pricing | CashERP')
@section('meta_description', 'Review CashERP packages, billing periods, trial days, company limits, locations, users and optional premium-module capacity.')
@section('body_class', 'page-pricing')

@section('content')
<section class="pricing-hero">
    <div class="container pricing-hero-grid">
        <div class="pricing-hero-copy reveal">
            <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Pricing</span></nav>
            <h1>Simple, transparent pricing for every business.</h1>
            <p class="hero-lead">Compare the packages currently published by CashERP. Prices, billing periods, trials, capacity and included modules come directly from the subscription configuration.</p>
            <ul class="trust-list">
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m8 12 2.6 2.6L16 9"/><circle cx="12" cy="12" r="9"/></svg></span> Transparent package limits</li>
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></span> Trial days shown per plan</li>
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg></span> Capacity that grows with you</li>
                <li><span class="trust-icon trust-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6z"/></svg></span> Verified hosted checkout</li>
            </ul>
        </div>
        <div class="pricing-hero-visual reveal reveal-delay">
            <div class="photo-frame">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="Business owner reviewing CashERP pricing">
                <p class="script-note">Grow Manage Succeed with CashERP.</p>
            </div>
            <div class="pricing-hero-float">
                <div class="pricing-float-card blue">
                    <strong>Need help choosing?</strong>
                    <p>Tell us about your business and we’ll recommend the right plan.</p>
                    <a class="text-link" href="mailto:sales@casherp.com">Talk to Sales <span>→</span></a>
                </div>
                <div class="pricing-float-card green">
                    <strong>Create your workspace</strong>
                    <p>Choose a published package and create your company workspace.</p>
                    <a class="button button-primary" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section-soft" id="plans">
    <div class="container">
        <div class="pricing-head reveal">
            <div>
                <h2 class="section-title">Choose Your Plan</h2>
                <p class="muted">Flexible plans for startups, growing teams and enterprises.</p>
            </div>
            @php
                $packageIntervals = collect($packages ?? [])->pluck('interval')->filter()->unique()->values();
                $currencyCode = !empty($systemCurrency->code) ? strtoupper($systemCurrency->code) : '';
                $cardIcons = ['icon-green' => '🚀', 'icon-blue' => '🏢', 'icon-violet' => '◈', 'icon-orange' => '▦'];
            @endphp
            @if($packageIntervals->count() > 1)
                <div class="billing-toggle" data-billing-toggle aria-label="Filter plans by billing period">
                    <button type="button" class="is-active" data-period="all">All plans</button>
                    @foreach($packageIntervals as $interval)
                        <button type="button" data-period="{{ $interval }}">{{ ucfirst($interval) }}</button>
                    @endforeach
                    <span class="save-badge">Prices set by CashERP</span>
                </div>
            @endif
        </div>

        <div class="pricing-layout">
            <div class="pricing-cards" data-plan-list>
                @forelse ($packages ?? [] as $package)
                    @php
                        $iconClass = array_keys($cardIcons)[$loop->index % count($cardIcons)];
                        $intervalCount = max(1, (int) $package->interval_count);
                        $intervalLabel = $intervalCount === 1
                            ? \Illuminate\Support\Str::singular($package->interval)
                            : $package->interval;
                        $registrationUrl = $package->enable_custom_link
                            ? $package->custom_link
                            : url('/business/register').'?'.http_build_query(array_merge(
                                $registrationContext ?? [],
                                ['package' => $package->id]
                            ));
                    @endphp
                    <article class="price-card {{ $package->mark_package_as_popular ? 'is-popular' : '' }} reveal" data-plan-interval="{{ $package->interval }}">
                        @if ($package->mark_package_as_popular)
                            <span class="card-ribbon">Most Popular</span>
                        @endif
                        <div class="tile-icon {{ $iconClass }}" aria-hidden="true">{{ $cardIcons[$iconClass] }}</div>
                        <h3>{{ $package->name }}</h3>
                        <p class="plan-audience">{{ $package->description ?: 'A configurable CashERP subscription package.' }}</p>
                        <p class="price-amount">
                            @if ((float) $package->price > 0)
                                <strong>{{ trim($currencyCode.' '.number_format((float) $package->price, 2)) }}</strong>
                                <span class="price-period">/ {{ $intervalCount }} {{ $intervalLabel }}</span>
                            @else
                                <strong>Free</strong>
                                <span class="price-period">for {{ $intervalCount }} {{ $intervalLabel }}</span>
                            @endif
                        </p>
                        <ul class="check-list compact">
                            <li><span>✓</span> {{ (int) $package->max_businesses === 0 ? 'Unlimited' : number_format($package->max_businesses) }} companies</li>
                            <li><span>✓</span> {{ (int) $package->location_count === 0 ? 'Unlimited' : number_format($package->location_count) }} locations per company</li>
                            <li><span>✓</span> {{ (int) $package->user_count === 0 ? 'Unlimited' : number_format($package->user_count) }} users per company</li>
                            <li><span>✓</span> {{ (int) $package->product_count === 0 ? 'Unlimited' : number_format($package->product_count) }} products per company</li>
                            <li><span>✓</span> {{ (int) $package->invoice_count === 0 ? 'Unlimited' : number_format($package->invoice_count) }} invoices per plan period</li>
                            @if($package->data_import_enabled)
                                <li><span>✓</span> Data import: {{ (int) $package->monthly_import_rows === 0 ? 'unlimited' : number_format($package->monthly_import_rows).' rows' }} per month</li>
                            @endif
                            @if((int) $package->trial_days > 0)
                                <li><span>✓</span> {{ number_format($package->trial_days) }} trial days</li>
                            @endif
                            @foreach((array) $package->custom_permissions as $permission => $enabled)
                                @if($enabled && isset($permission_formatted[$permission]))
                                    <li><span>✓</span> {{ $permission_formatted[$permission] }}</li>
                                @endif
                            @endforeach
                            @foreach($package->premiumModules as $premiumModule)
                                @php($allowance = $premiumModule->pivot->included_allowance_override ?? $premiumModule->included_allowance)
                                <li><span>★</span> {{ $premiumModule->name }}: {{ (int) $allowance === 0 ? 'unlimited' : number_format($allowance) }} {{ $premiumModule->allowance_name }}{{ $premiumModule->allowance_reset === 'monthly' ? ' per month' : '' }}</li>
                            @endforeach
                        </ul>
                        <a class="button {{ $package->mark_package_as_popular ? 'button-primary' : 'button-outline' }} price-cta" href="{{ $registrationUrl }}">{{ $package->enable_custom_link ? ($package->custom_link_text ?: 'Learn more') : ((float) $package->price > 0 ? 'Choose plan' : 'Register free') }} <span>→</span></a>
                    </article>
                @empty
                    <article class="price-card reveal">
                        <div class="tile-icon icon-blue" aria-hidden="true">◈</div>
                        <h3>Packages are being prepared</h3>
                        <p class="plan-audience">No public subscription package is currently available. Contact the CashERP team for assistance.</p>
                        <a class="button button-outline price-cta" href="mailto:sales@casherp.com">Contact Sales <span>→</span></a>
                    </article>
                @endforelse
            </div>

            <aside class="pricing-aside reveal">
                <div class="pricing-float-card blue">
                    <strong>Need help choosing?</strong>
                    <p>Not sure which plan fits? Our team can guide you.</p>
                    <a class="text-link" href="mailto:sales@casherp.com">Talk to Sales <span>→</span></a>
                </div>
                <div class="pricing-float-card green">
                    <strong>Create your workspace</strong>
                    <p>Select a published package and continue to industry-based registration.</p>
                    <a class="button button-primary" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
                </div>
                <div class="aside-card">
                    <h3>All plans include</h3>
                    <ul class="check-list compact">
                        <li><span>✓</span> Cloud-based access</li>
                        <li><span>✓</span> Company-separated business data</li>
                        <li><span>✓</span> Industry-specific feature profiles</li>
                        <li><span>✓</span> Role-based permissions</li>
                        <li><span>✓</span> Mobile-responsive workspace</li>
                        <li><span>✓</span> Auditable business documents</li>
                    </ul>
                </div>
                <div class="aside-card testimonial">
                    <p><strong>Need a tailored package?</strong></p>
                    <p>Ask about additional companies, locations, users, modules and monthly capacity.</p>
                    <a class="text-link" href="mailto:sales@casherp.com">Contact our team <span>→</span></a>
                </div>
            </aside>
        </div>

        @if(($premiumModules ?? collect())->isNotEmpty())
            <div class="live-packages reveal">
                <h2 class="section-title">Optional premium modules</h2>
                <p class="muted">Published capacity add-ons managed by the CashERP Super Admin.</p>
                <div class="live-package-grid">
                    @foreach($premiumModules as $module)
                        <article class="info-card">
                            <h3>{{ $module->name }}</h3>
                            <p>{{ $module->description }}</p>
                            <p><strong>{{ (int) $module->included_allowance === 0 ? 'Unlimited' : number_format($module->included_allowance) }}</strong> {{ $module->allowance_name }}{{ $module->allowance_reset === 'monthly' ? ' per month' : '' }}</p>
                            @if((int) $module->capacity_increment > 0)
                                <p class="muted">Extra {{ number_format($module->capacity_increment) }}: {{ trim($currencyCode.' '.number_format((float) $module->capacity_price, 2)) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

<section class="section" id="compare">
    <div class="container">
        <div class="section-heading reveal">
            <h2 class="section-title">Compare Plans</h2>
            <p>See exactly what’s included in every CashERP plan.</p>
        </div>
        <div class="compare-table-wrap reveal">
            @if(($packages ?? collect())->isNotEmpty())
                <table class="compare-table pricing-compare">
                    <thead>
                        <tr>
                            <th>Published allowance</th>
                            @foreach($packages as $package)
                                <th class="plan-col">{{ $package->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Billing</td>
                            @foreach($packages as $package)
                                <td>{{ max(1, (int) $package->interval_count) }} {{ $package->interval }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Companies</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->max_businesses === 0 ? 'Unlimited' : number_format($package->max_businesses) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Locations per company</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->location_count === 0 ? 'Unlimited' : number_format($package->location_count) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Users per company</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->user_count === 0 ? 'Unlimited' : number_format($package->user_count) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Products per company</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->product_count === 0 ? 'Unlimited' : number_format($package->product_count) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Invoices per plan period</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->invoice_count === 0 ? 'Unlimited' : number_format($package->invoice_count) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Trial</td>
                            @foreach($packages as $package)
                                <td>{{ (int) $package->trial_days > 0 ? number_format($package->trial_days).' days' : 'Not included' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Data import</td>
                            @foreach($packages as $package)
                                <td>{{ $package->data_import_enabled ? ((int) $package->monthly_import_rows === 0 ? 'Unlimited rows' : number_format($package->monthly_import_rows).' rows/month') : 'Not included' }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="muted">Plan comparison will appear when the Super Admin publishes subscription packages.</p>
            @endif
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container pricing-faq-grid">
        <div class="reveal">
            <div class="section-heading-split">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <a class="text-link" href="{{ url('/resources') }}">View all FAQs <span>→</span></a>
            </div>
            <div class="faq-list" data-faq>
                <details open>
                    <summary>Can I try CashERP for free?</summary>
                    <p>Trial availability and duration are configured per package. Any included trial days are displayed on the published plan above.</p>
                </details>
                <details>
                    <summary>Can I change plans later?</summary>
                    <p>Package changes follow the subscription terms configured by CashERP. Contact the billing team when you need different capacity or modules.</p>
                </details>
                <details>
                    <summary>Do you offer yearly billing?</summary>
                    <p>Available billing periods appear in the published package list. Use the billing-period filter when more than one period is available.</p>
                </details>
                <details>
                    <summary>What payment methods do you accept?</summary>
                    <p>The secure checkout displays the payment gateways enabled for your country and selected package. Contact billing if you need assistance.</p>
                </details>
                <details>
                    <summary>Is support included in every plan?</summary>
                    <p>Support entitlements are defined by the selected package or a written service agreement. Contact our team for the applicable support terms.</p>
                </details>
            </div>
        </div>
        <aside class="custom-plan-card reveal reveal-delay">
            <span class="tile-icon icon-coral" aria-hidden="true">🏛</span>
            <h3>Need a custom plan?</h3>
            <p>We’ll tailor capacity, modules, onboarding and support for your organisation.</p>
            <a class="button button-outline" href="mailto:sales@casherp.com">Contact Our Team <span>→</span></a>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container dark-cta pricing-cta">
        <div>
            <h2>Ready to simplify your business?</h2>
            <p>Review the published packages and create the workspace that fits your organisation.</p>
            <div class="hero-actions">
                <a class="button button-white button-large" href="{{ url('/get-started') }}">Get Started <span>→</span></a>
                <a class="button button-ghost button-large" href="mailto:sales@casherp.com">Talk to Sales <span>→</span></a>
            </div>
        </div>
        <img src="{{ asset('img/landing/cta-devices.png') }}" alt="" class="cta-devices-img">
    </div>
</section>
@endsection
