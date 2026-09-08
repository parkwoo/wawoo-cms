<?php
/**
 * @var array $vars
 *  pageTitle, posts (paginated), stats, page, pages, filterCat, filterTag
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
<h1><?= htmlspecialchars($vars['pageTitle']) ?></h1>

<?php if (!empty($vars['filterCat']) || !empty($vars['filterTag'])): ?>
    <div class="crumbs">
        <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a> /
        <?php if (!empty($vars['filterCat'])): ?>
            <?= t('common.category', 'Category') ?>: <strong><?= htmlspecialchars($vars['filterCat']) ?></strong>
        <?php else: ?>
            <?= t('common.tag', 'Tag') ?>: <strong>#<?= htmlspecialchars($vars['filterTag']) ?></strong>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p><?= t('common.no_posts', 'No posts yet.') ?></p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <article class="post-card">
            <?php if (!empty($post['cover'])): ?>
                <a class="post-card-thumb" href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>" tabindex="-1" aria-hidden="true">
                    <img src="<?= htmlspecialchars($post['cover']) ?>" alt="" loading="lazy" decoding="async">
                </a>
            <?php endif; ?>
            <div class="post-card-body">
                <h2><a href="<?= htmlspecialchars(BASE_URL) ?>post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                <div class="meta">
                    <time><?= htmlspecialchars($post['date']) ?></time>
                    &middot; <?= htmlspecialchars($post['category']) ?>
                    <?php if (!empty($post['tags'])): ?>
                        &middot; <?php foreach ($post['tags'] as $tag): ?>
                            <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($tag)) ?>">#<?= htmlspecialchars($tag) ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($post['excerpt'] !== ''): ?>
                    <div class="excerpt"><?= htmlspecialchars($post['excerpt']) ?></div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>

    <?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
    <?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
        <?= $__pgnav ?>
    <?php elseif (($vars['pages'] ?? 1) > 1): ?>
        <nav class="pagination">
            <?php $baseUrl = htmlspecialchars(BASE_URL); ?>
            <?php if ($vars['page'] > 1): ?>
                <a href="<?= $baseUrl ?>?page=<?= $vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
            <?php endif; ?>
            <span><?= sprintf(t('common.page', 'Page %d / %d'), $vars['page'], $vars['pages']) ?></span>
            <?php if ($vars['page'] < $vars['pages']): ?>
                <a href="<?= $baseUrl ?>?page=<?= $vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php Hook::fire('theme.index.after', $vars); ?>
