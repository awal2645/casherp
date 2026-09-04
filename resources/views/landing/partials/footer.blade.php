@php
    $footerActive = trim($__env->yieldContent('footer_active'));
@endphp
<footer class="site-footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <img src="{{ asset('img/landing/casherp-logo-white.png') }}" alt="CashERP" width="149" height="42">
            <p>The cloud ERP that brings every part of your business together.</p>
            <div class="socials">
                <a href="https://www.facebook.com" aria-label="Facebook" rel="noopener noreferrer" target="_blank"><svg viewBox="0 0 24 24"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h2.6L16 12h-3V10c0-.6.4-1 1-1z"/></svg></a>
                <a href="https://www.linkedin.com" aria-label="LinkedIn" rel="noopener noreferrer" target="_blank"><svg viewBox="0 0 24 24"><path d="M6.5 9H4v11h2.5zM5.2 4A1.7 1.7 0 1 0 5.2 7.4 1.7 1.7 0 0 0 5.2 4zM20 20h-2.5v-5.6c0-1.6-.6-2.6-2-2.6s-2.1 1-2.1 2.6V20H11V9h2.4v1.5c.5-.9 1.7-1.8 3.5-1.8 2.6 0 3.1 1.7 3.1 4.2z"/></svg></a>
                <a href="https://www.youtube.com" aria-label="YouTube" rel="noopener noreferrer" target="_blank"><svg viewBox="0 0 24 24"><path d="M21.6 7.2a2.7 2.7 0 0 0-1.9-1.9C18 5 12 5 12 5s-6 0-7.7.3a2.7 2.7 0 0 0-1.9 1.9A28 28 0 0 0 2 12a28 28 0 0 0 .4 4.8 2.7 2.7 0 0 0 1.9 1.9C6 19 12 19 12 19s6 0 7.7-.3a2.7 2.7 0 0 0 1.9-1.9A28 28 0 0 0 22 12a28 28 0 0 0-.4-4.8zM10 15.5v-7l6 3.5z"/></svg></a>
                <a href="https://x.com" aria-label="X" rel="noopener noreferrer" target="_blank"><svg viewBox="0 0 24 24"><path d="M4 4h4.6l4 5.6L17.2 4H20l-6.4 7.6L20 20h-4.6l-4.2-5.8L6.8 20H4l6.7-8z"/></svg></a>
            </div>
        </div>
        <div>
            <h2>Product</h2>
            <a href="{{ url('/') }}#features">Features</a>
            <a href="{{ url('/industries') }}">Industries</a>
            <a href="{{ url('/pricing') }}">Pricing</a>
            <a href="{{ url('/') }}#features">Integrations</a>
            <a href="{{ url('/resources') }}">What's New</a>
        </div>
        <div>
            <h2>Solutions</h2>
            <a href="{{ url('/industries') }}#trading">Retail &amp; Trading</a>
            <a href="{{ url('/industries') }}#restaurant">Restaurants</a>
            <a href="{{ url('/industries') }}#hotel">Hotels</a>
            <a href="{{ url('/industries') }}#property">Property</a>
            <a href="{{ url('/industries') }}#services">Professional Services</a>
        </div>
            <div>
            <h2>Resources</h2>
            <a href="{{ url('/resources') }}" class="{{ $footerActive === 'resources' ? 'is-active' : '' }}">Help Centre</a>
            <a href="{{ url('/guides') }}" class="{{ $footerActive === 'guides' ? 'is-active' : '' }}">Guides</a>
            <a href="{{ url('/resources') }}#articles">Blog</a>
            <a href="{{ url('/resources') }}">Integration guidance</a>
            <a href="{{ url('/') }}#security">Security overview</a>
        </div>
        <div>
            <h2>Company</h2>
            <a href="{{ url('/about') }}" class="{{ $footerActive === 'about' ? 'is-active' : '' }}">About Us</a>
            <a href="{{ url('/partners') }}" class="{{ $footerActive === 'partners' ? 'is-active' : '' }}">Partners</a>
            <a href="{{ url('/careers') }}" class="{{ $footerActive === 'careers' ? 'is-active' : '' }}">Careers</a>
            <a href="{{ url('/contact') }}" class="{{ $footerActive === 'contact' ? 'is-active' : '' }}">Contact Us</a>
        </div>
        <div class="footer-legal" id="legal">
            <h2>Legal</h2>
            <a href="mailto:legal@casherp.com?subject=CashERP%20privacy%20enquiry">Privacy enquiries</a>
            <a href="mailto:legal@casherp.com?subject=CashERP%20terms%20enquiry">Terms enquiries</a>
            <a href="{{ url('/') }}#security">Security</a>
            <a href="mailto:legal@casherp.com?subject=CashERP%20cookie%20enquiry">Cookie enquiries</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>© {{ date('Y') }} CashERP. All rights reserved.</p>
        <label class="language">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
            <select aria-label="Language"><option selected>English</option></select>
        </label>
    </div>
</footer>
