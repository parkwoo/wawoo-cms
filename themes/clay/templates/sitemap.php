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
<section class="clay-card sitemap-head">
    <h1><?= t('nav.sitemap', 'Sitemap') ?></h1>
    <p class="muted"><?= sprintf(t('sitemap.total', '%1$d posts in total.'), (int)$vars['total']) ?></p>
</section>

<div class="sitemap-list">
    <?php foreach (($vars['byYear'] ?? []) as $year => $posts): ?>
        <section class="sitemap-year">
            <h2><?= htmlspecialchars($year) ?> <span class="muted sitemap-count">(<?= count($posts) ?>)</span></h2>
            <ul>
                <?php foreach ($posts as $p): ?>
                    <li>
                        <time datetime="<?= htmlspecialchars(substr((string)$p['date'], 0, 10)) ?>"><?= htmlspecialchars(substr((string)$p['date'], 0, 10)) ?></time>
                        <a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>

<?php if (($vars['pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="<?= t('nav.pagination', 'Pagination') ?>">
        <?php if ($vars['page'] > 1): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= $vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
        <?php endif; ?>
        <span class="page-num"><?= sprintf(t('common.page', 'Page %d / %d'), $vars['page'], $vars['pages']) ?></span>
        <?php if ($vars['page'] < $vars['pages']): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= $vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php Hook::fire('theme.sitemap.after', $vars); ?>
