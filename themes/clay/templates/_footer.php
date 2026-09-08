</main>
<footer class="site-footer">
    <div>
        &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_TITLE) ?>.
        Powered by <a href="https://github.com/">wawoo-cms</a> · theme: clay
    </div>
</footer>
<script src="<?= \Wawoo\Core\Theme::asset('js/post-images.js') ?>" defer></script>
<?php \Wawoo\Core\Hook::fire('theme.footer', $vars); ?>
</body>
</html>
