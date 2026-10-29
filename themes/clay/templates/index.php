<?php
/**
 * @var array $vars posts, pageTitle, page, pages, filterCat, filterTag, stats
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$posts = $vars['posts'] ?? [];
?>
<div class="crumbs">
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
    <?php if (!empty($vars['filterCat'])): ?>
        / <?= t('common.category', 'Category') ?>: <strong><?= htmlspecialchars($vars['filterCat']) ?></strong>
    <?php elseif (!empty($vars['filterTag'])): ?>
        / <?= t('common.tag', 'Tag') ?>: <strong>#<?= htmlspecialchars($vars['filterTag']) ?></strong>
    <?php endif; ?>
</div>

<?php if (empty($posts)): ?>
    <div class="clay-card"><p><?= t('common.no_posts', 'No posts yet.') ?></p></div>
<?php else: ?>
    <div class="post-list">
        <?php foreach ($posts as $i => $post): ?>
            <article class="clay-card post-card<?= $i === 0 ? ' post-featured' : '' ?>">
                <?php if (!empty($post['cover'])): ?>
                    <a class="post-card-thumb" href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?= htmlspecialchars($post['cover']) ?>" alt="" loading="lazy" decoding="async">
                    </a>
                <?php endif; ?>
                <div class="post-card-body">
                    <div class="meta">
                        <?php if ($post['category'] !== ''): ?>
                            <a class="cat" href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($post['category'])) ?>"><?= htmlspecialchars($post['category']) ?></a>
                        <?php endif; ?>
                        <time datetime="<?= htmlspecialchars((string)$post['date']) ?>"><?= htmlspecialchars($post['date']) ?></time>
                        <?php if (!empty($post['tags'])): ?>
                            <?php foreach ($post['tags'] as $tag): ?>
                                <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($tag)) ?>">#<?= htmlspecialchars($tag) ?></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <h2 class="post-card-title"><a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                    <?php if ($post['excerpt'] !== ''): ?>
                        <div class="excerpt"><?= htmlspecialchars($post['excerpt']) ?></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
    <?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
        <?= $__pgnav ?>
    <?php elseif (($vars['pages'] ?? 1) > 1): ?>
        <nav class="pagination" aria-label="<?= t('nav.pagination', 'Pagination') ?>">
            <?php if ($vars['page'] > 1): ?>
                <a href="<?= htmlspecialchars(BASE_URL) ?>?page=<?= $vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
            <?php endif; ?>
            <span class="page-num"><?= sprintf(t('common.page', 'Page %d / %d'), $vars['page'], $vars['pages']) ?></span>
            <?php if ($vars['page'] < $vars['pages']): ?>
                <a href="<?= htmlspecialchars(BASE_URL) ?>?page=<?= $vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php Hook::fire('theme.index.after', $vars); ?>
