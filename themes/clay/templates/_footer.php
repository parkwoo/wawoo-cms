</main>
<footer class="site-footer">
    <div class="container">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_TITLE) ?>.
        Powered by <a href="https://github.com/">wawoo-cms</a> &middot; theme: clay
    </div>
</footer>
<script<?= \Wawoo\Core\Csp::attr() ?> src="<?= \Wawoo\Core\Theme::asset('js/nav.js') ?>" defer></script>
<script<?= \Wawoo\Core\Csp::attr() ?> src="<?= \Wawoo\Core\Theme::asset('js/post-images.js') ?>" defer></script>
<?php \Wawoo\Core\Hook::fire('theme.footer', $vars); ?>
</body>
</html>
