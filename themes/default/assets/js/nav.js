/* wawoo-cms nav: mobile menu toggle + "More" dropdown (no deps) */
(function () {
    'use strict';

    function closeDropdown(toggle) {
        var panel = toggle.nextElementSibling;
        if (!panel) { return; }
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    // Mobile hamburger: toggles .site-nav[data-open] (CSS shows/hides #nav-menu).
    var nav = document.querySelector('.site-nav');
    var navToggle = document.querySelector('.nav-toggle');
    if (nav && navToggle) {
        navToggle.addEventListener('click', function () {
            var open = nav.hasAttribute('data-open');
            if (open) {
                nav.removeAttribute('data-open');
                navToggle.setAttribute('aria-expanded', 'false');
            } else {
                nav.setAttribute('data-open', '');
                navToggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // "More" dropdown: hidden attr toggles.
    Array.prototype.forEach.call(document.querySelectorAll('.nav-more-toggle'), function (t) {
        t.addEventListener('click', function (e) {
            e.preventDefault();
            var panel = t.nextElementSibling;
            if (!panel) { return; }
            var opening = panel.hidden;
            // Close all More dropdowns first.
            Array.prototype.forEach.call(document.querySelectorAll('.nav-more-toggle[aria-expanded="true"]'), function (o) {
                if (o !== t) { closeDropdown(o); }
            });
            panel.hidden = !opening;
            t.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
    });

    // Close on outside click.
    document.addEventListener('click', function (e) {
        Array.prototype.forEach.call(document.querySelectorAll('.nav-more'), function (root) {
            if (root.contains(e.target)) { return; }
            var tg = root.querySelector('.nav-more-toggle');
            if (tg) { closeDropdown(tg); }
        });
    });

    // Escape closes and returns focus.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var open = document.querySelector('.nav-more-toggle[aria-expanded="true"]');
        if (open) {
            closeDropdown(open);
            open.focus();
        } else if (nav && nav.hasAttribute('data-open')) {
            nav.removeAttribute('data-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.focus();
        }
    });
})();
