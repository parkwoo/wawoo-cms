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
<h1><?= t('nav.sitemap', 'Sitemap') ?></h1>
<p><?= sprintf(t('sitemap.total', '%1$d posts in total.'), (int)$vars['total']) ?></p>

<?php foreach (($vars['byYear'] ?? []) as $year => $posts): ?>
    <h2><?= htmlspecialchars($year) ?> <small style="color:var(--muted);font-weight:400">(<?= count($posts) ?>)</small></h2>
    <ul>
    <?php foreach ($posts as $p): ?>
        <li>
            <time style="color:var(--muted)"><?= htmlspecialchars(substr((string)$p['date'], 0, 10)) ?></time>
            ·
            <a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
            <?php if (!empty($p['category'])): ?>
                · <span style="color:var(--muted)"><?= htmlspecialchars($p['category']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<?php if (($vars['pages'] ?? 1) > 1): ?>
    <nav class="pagination">
        <?php if ($vars['page'] > 1): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= $vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
        <?php endif; ?>
        <span><?= sprintf(t('common.page', 'Page %d / %d'), $vars['page'], $vars['pages']) ?></span>
        <?php if ($vars['page'] < $vars['pages']): ?>
            <a href="<?= htmlspecialchars(BASE_URL) ?>sitemap-page?page=<?= $vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
        <?php else: ?>
            <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php Hook::fire('theme.sitemap.after', $vars); ?>
