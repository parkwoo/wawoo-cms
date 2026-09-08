<?php
/**
 * @var array $vars byYear, total, page, pages
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}
?>
<header class="index-head">
    <h1><?= t('nav.sitemap', 'Sitemap') ?></h1>
    <p class="index-desc"><?= sprintf(t('sitemap.total', '%1$d posts in total.'), (int)$vars['total']) ?></p>
</header>

<?php foreach (($vars['byYear'] ?? []) as $year => $posts): ?>
    <h2 class="sitemap-year"><?= htmlspecialchars((string)$year) ?> <span class="sitemap-count">(<?= count($posts) ?>)</span></h2>
    <ul class="sitemap-list">
    <?php foreach ($posts as $p): ?>
        <li>
            <time class="sitemap-time" datetime="<?= htmlspecialchars((string)substr((string)$p['date'], 0, 10)) ?>"><?= htmlspecialchars(substr((string)$p['date'], 0, 10)) ?></time>
            &middot;
            <a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
            <?php if (!empty($p['category'])): ?>
                &middot; <span class="sitemap-time"><?= htmlspecialchars($p['category']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<?php if (($vars['pages'] ?? 1) > 1): ?>
    <nav class="pagination" role="navigation" aria-label="<?= t('a11y.pagination', 'Pagination') ?>">
        <?php if ($vars['page'] > 1): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= (int)$vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
        <?php endif; ?>
        <span><?= sprintf(t('common.page', 'Page %d / %d'), (int)$vars['page'], (int)$vars['pages']) ?></span>
        <?php if ($vars['page'] < $vars['pages']): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= (int)$vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php Hook::fire('theme.sitemap.after', $vars); ?>
