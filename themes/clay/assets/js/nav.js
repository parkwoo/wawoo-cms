/* wawoo-cms nav: mobile menu toggle + "More" dropdown (no deps)
   Progressive enhancement: JS adds .has-nav-js; without it the whole nav
   and More lists stay visible. */
(function () {
    'use strict';

    document.documentElement.classList.add('has-nav-js');

    function setMore(root, open) {
        root.classList.toggle('nav-more-open', open);
        var tg = root.querySelector('.nav-more-toggle');
        if (tg) { tg.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    }

    // Mobile hamburger: toggles .site-nav[data-open] (small screens only).
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

    // "More" dropdown.
    Array.prototype.forEach.call(document.querySelectorAll('.nav-more'), function (root) {
        var tg = root.querySelector('.nav-more-toggle');
        if (!tg) { return; }
        tg.addEventListener('click', function (e) {
            e.preventDefault();
            var opening = !root.classList.contains('nav-more-open');
            Array.prototype.forEach.call(document.querySelectorAll('.nav-more'), function (o) {
                if (o !== root) { setMore(o, false); }
            });
            setMore(root, opening);
        });
    });

    // Close on outside click.
    document.addEventListener('click', function (e) {
        Array.prototype.forEach.call(document.querySelectorAll('.nav-more'), function (root) {
            if (root.contains(e.target)) { return; }
            setMore(root, false);
        });
    });

    // Escape closes and returns focus.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var openMore = document.querySelector('.nav-more.nav-more-open');
        if (openMore) {
            setMore(openMore, false);
            var tg = openMore.querySelector('.nav-more-toggle');
            if (tg) { tg.focus(); }
        } else if (nav && nav.hasAttribute('data-open')) {
            nav.removeAttribute('data-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.focus();
        }
    });
})();
