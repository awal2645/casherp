(function () {
    'use strict';

    var header = document.querySelector('[data-site-header]');
    var menu = document.querySelector('[data-menu]');
    var toggle = document.querySelector('[data-menu-toggle]');
    var dropdowns = document.querySelectorAll('[data-dropdown]');

    function updateHeader() {
        if (header) {
            header.classList.toggle('scrolled', window.scrollY > 8);
        }
    }

    function closeMenu() {
        if (!menu || !toggle) return;
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        document.body.classList.remove('menu-open');
        closeDropdowns();
    }

    function closeDropdowns(except) {
        dropdowns.forEach(function (item) {
            if (item !== except) {
                item.classList.remove('open');
                var button = item.querySelector('.nav-link');
                if (button) button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var isOpen = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            document.body.classList.toggle('menu-open', isOpen);
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 820) closeMenu();
        });
    }

    dropdowns.forEach(function (item) {
        var button = item.querySelector('.nav-link');
        if (!button) return;

        button.addEventListener('click', function (event) {
            event.preventDefault();
            var willOpen = !item.classList.contains('open');
            closeDropdowns(item);
            item.classList.toggle('open', willOpen);
            button.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-dropdown]')) {
            closeDropdowns();
        }
    });

    var billing = document.querySelector('[data-billing-toggle]');
    if (billing) {
        billing.addEventListener('click', function (event) {
            var btn = event.target.closest('button[data-period]');
            if (!btn) return;
            billing.querySelectorAll('button[data-period]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });

            var period = btn.dataset.period;
            document.querySelectorAll('[data-plan-interval]').forEach(function (card) {
                card.hidden = period !== 'all' && card.dataset.planInterval !== period;
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdowns();
            closeMenu();
        }
    });

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    var revealItems = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealItems.forEach(function (item) { item.classList.add('revealed'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    revealItems.forEach(function (item) { observer.observe(item); });
})();
