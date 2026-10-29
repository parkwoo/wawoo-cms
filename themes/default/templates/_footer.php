</main>
<footer class="site-footer">
    <div class="container">
        <div>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_TITLE) ?>. Powered by <a href="https://github.com/">wawoo-cms</a>.</div>
    </div>
</footer>
<script<?= \Wawoo\Core\Csp::attr() ?> src="<?= \Wawoo\Core\Theme::asset('js/nav.js') ?>" defer></script>
<script<?= \Wawoo\Core\Csp::attr() ?> src="<?= \Wawoo\Core\Theme::asset('js/post-images.js') ?>" defer></script>
<script<?= \Wawoo\Core\Csp::attr() ?>>
document.querySelectorAll('pre > code[class*="language-"]').forEach(function (code) {
    var m = (code.className || '').match(/language-([A-Za-z0-9_-]+)/);
    if (m) { code.parentElement.setAttribute('data-lang', m[1]); }
});
</script>
<?php \Wawoo\Core\Hook::fire('theme.footer', $vars); ?>
</body>
</html>
