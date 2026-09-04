@extends('layouts.landing')

@section('nav_active', '')
@section('footer_active', 'contact')

@section('title', 'Contact Us | CashERP')
@section('meta_description', 'Get in touch with CashERP support, sales, billing or partnerships. We’re here to help your business succeed.')

@section('content')
<section class="contact-hero">
    <div class="container contact-hero-inner reveal">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a> <span>›</span> <span>Contact Us</span></nav>
        <p class="eyebrow">Contact us</p>
        <h1>We’re Here to Help</h1>
        <p class="hero-lead">Questions, feedback or support — our team is ready to assist you.</p>
        <div class="contact-hero-visual">
            <p class="script-note inline left-note">Your Success Matters</p>
            <div class="photo-frame contact-hero-photo">
                <img src="{{ asset('img/landing/hero-professional.jpg') }}" alt="CashERP support specialist">
            </div>
            <p class="script-note inline right-note">Let’s Build a Smarter Business Together</p>
        </div>
    </div>
</section>

<section class="section contact-topics-wrap">
    <div class="container">
        <div class="contact-topics" data-contact-topics>
            <button type="button" class="topic-card is-active" data-topic="General Support">
                <span class="tile-icon icon-blue" aria-hidden="true">🎧</span>
                <strong>General Support</strong>
                <span>Help with your account and day-to-day questions.</span>
            </button>
            <button type="button" class="topic-card" data-topic="Billing & Payments">
                <span class="tile-icon icon-violet" aria-hidden="true">💳</span>
                <strong>Billing &amp; Payments</strong>
                <span>Invoices, plans, renewals and payment help.</span>
            </button>
            <button type="button" class="topic-card" data-topic="Technical Help">
                <span class="tile-icon icon-green" aria-hidden="true">⚙</span>
                <strong>Technical Help</strong>
                <span>Setup issues, bugs and product troubleshooting.</span>
            </button>
            <button type="button" class="topic-card" data-topic="Sales & Partnerships">
                <span class="tile-icon icon-orange" aria-hidden="true">📢</span>
                <strong>Sales &amp; Partnerships</strong>
                <span>Demos, pricing and partner opportunities.</span>
            </button>
            <button type="button" class="topic-card" data-topic="Careers">
                <span class="tile-icon icon-sky" aria-hidden="true">◎</span>
                <strong>Careers</strong>
                <span>Join the team building CashERP.</span>
            </button>
        </div>
    </div>
</section>

<section class="section section-soft" id="message">
    <div class="container contact-main">
        <div class="contact-form-panel reveal">
            <h2 class="section-title">Send Us a Message</h2>
            <p class="muted">Complete the form to open a prepared email in your device’s mail application.</p>
            <form class="setup-form contact-form" method="post" action="mailto:support@casherp.com" enctype="text/plain">
                <div class="form-grid-2">
                    <label>Your name *
                        <input type="text" name="name" placeholder="Enter your full name" required>
                    </label>
                    <label>Email address *
                        <input type="email" name="email" placeholder="Enter your email address" required>
                    </label>
                </div>
                <label>Subject *
                    <select name="subject" id="contact-subject" required>
                        <option value="General Support" selected>General Support</option>
                        <option value="Billing & Payments">Billing &amp; Payments</option>
                        <option value="Technical Help">Technical Help</option>
                        <option value="Sales & Partnerships">Sales &amp; Partnerships</option>
                        <option value="Careers">Careers</option>
                    </select>
                </label>
                <label>Message *
                    <textarea name="message" rows="6" placeholder="Tell us how we can help..." required></textarea>
                </label>
                <button class="button button-primary button-large" type="submit">Open Email App <span>→</span></button>
            </form>

            <div class="contact-form-fill">
                <h3>What happens next?</h3>
                <ul class="check-list compact">
                    <li><span>✓</span> Your mail application opens with the entered details</li>
                    <li><span>✓</span> You can review the message before sending</li>
                    <li><span>✓</span> CashERP support replies through the published support channel</li>
                </ul>
                <div class="contact-quick-links">
                    <a class="text-link" href="{{ url('/guides') }}">Browse help guides <span>→</span></a>
                    <a class="text-link" href="{{ url('/resources') }}">Visit Help Centre <span>→</span></a>
                    <a class="text-link" href="{{ url('/get-started') }}">Review registration options <span>→</span></a>
                </div>
                <div class="contact-assurance">
                    <span class="tile-icon icon-green" aria-hidden="true">🛡</span>
                    <div>
                        <strong>Review before sending</strong>
                        <p>The message is sent only after you confirm it in your own mail application.</p>
                    </div>
                </div>
            </div>
        </div>

        <aside class="contact-aside reveal reveal-delay">
            <div class="aside-card">
                <h3>Other Ways to Reach Us</h3>
                <div class="reach-item">
                    <span class="tile-icon icon-blue" aria-hidden="true">✉</span>
                    <div>
                        <strong>Email Us</strong>
                        <a href="mailto:support@casherp.com">support@casherp.com</a>
                        <small>We usually respond within 24 hours.</small>
                    </div>
                </div>
                <div class="reach-item">
                    <span class="tile-icon icon-green" aria-hidden="true">☎</span>
                    <div>
                        <strong>Call Us</strong>
                        <a href="tel:+260211000000">+260 211 000 000</a>
                        <small>Mon–Fri, 8:00 AM – 6:00 PM CAT</small>
                    </div>
                </div>
                <div class="reach-item">
                    <span class="tile-icon icon-violet" aria-hidden="true">💬</span>
                    <div>
                        <strong>Live Chat</strong>
                        <small>Chat with our support team in real time.</small>
                        <a class="button button-outline" href="mailto:support@casherp.com">Start Live Chat <span>→</span></a>
                    </div>
                </div>
            </div>

            <div class="aside-card">
                <h3>Our Office</h3>
                <div class="reach-item">
                    <span class="tile-icon icon-orange" aria-hidden="true">📍</span>
                    <div>
                        <strong>CashERP HQ</strong>
                        <small>Lusaka, Zambia<br>Central Business District</small>
                        <a class="text-link" href="https://maps.google.com/?q=Lusaka+Zambia" target="_blank" rel="noopener noreferrer">Get Directions <span>→</span></a>
                    </div>
                </div>
                <div class="office-map" aria-hidden="true">
                    <div class="map-pin">📍</div>
                    <span>Lusaka</span>
                </div>
            </div>

            <div class="aside-card">
                <h3>Business Hours</h3>
                <ul class="hours-list">
                    <li><span>Monday – Friday</span><strong>8:00 AM – 6:00 PM</strong></li>
                    <li><span>Saturday</span><strong>9:00 AM – 1:00 PM</strong></li>
                    <li><span>Sunday</span><strong>Closed</strong></li>
                </ul>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading-split reveal">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <a class="text-link" href="{{ url('/resources') }}">View All FAQs <span>→</span></a>
        </div>
        <div class="faq-list contact-faq reveal" data-faq>
            <details open>
                <summary>How do I create a CashERP account?</summary>
                <p>Go to <a href="{{ url('/get-started') }}">Get Started</a>, tell us about your company, review the currently published packages and continue to registration.</p>
            </details>
            <details>
                <summary>What payment methods do you accept?</summary>
                <p>The hosted checkout shows the gateways enabled for your country and selected package. Contact billing if you need help with an available method.</p>
            </details>
            <details>
                <summary>Can I change or cancel my subscription?</summary>
                <p>Subscription changes follow the selected package and billing terms. Contact billing to confirm the effective date and available upgrade, downgrade or cancellation options.</p>
            </details>
            <details>
                <summary>Do you offer support in my country?</summary>
                <p>Contact the CashERP team to confirm the support channels, hours and onboarding assistance available for your country and package.</p>
            </details>
            <details>
                <summary>How can I become a partner?</summary>
                <p>Visit our <a href="{{ url('/partners') }}">Partners</a> page or email <a href="mailto:partners@casherp.com">partners@casherp.com</a> to explore technology, referral, reseller and implementation options.</p>
            </details>
        </div>

        <div class="soft-cta contact-soft-cta reveal">
            <div>
                <h2 class="section-title">Still need help?</h2>
                <p class="muted">Our support team is just a message away.</p>
            </div>
            <a class="button button-primary button-large" href="#message">Contact Support <span>→</span></a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var topics = document.querySelector('[data-contact-topics]');
    var subject = document.getElementById('contact-subject');
    if (!topics || !subject) return;
    topics.addEventListener('click', function (e) {
        var card = e.target.closest('.topic-card');
        if (!card) return;
        topics.querySelectorAll('.topic-card').forEach(function (c) { c.classList.toggle('is-active', c === card); });
        subject.value = card.dataset.topic;
        document.getElementById('message')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
@endpush
