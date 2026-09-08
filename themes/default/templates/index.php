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

$posts     = $vars['posts'] ?? [];
$filterCat = (string)($vars['filterCat'] ?? '');
$filterTag = (string)($vars['filterTag'] ?? '');
$pageNo    = max(1, (int)($vars['page'] ?? 1));
$filtered  = $filterCat !== '' || $filterTag !== '';
$lead      = $pageNo === 1 && !empty($posts) ? $posts[0] : null;
$rows      = $lead !== null ? array_slice($posts, 1) : $posts;
$fmt       = static fn(string $d): string => ($t = strtotime($d)) ? date('F j, Y', $t) : $d;
$postUrl   = static fn(string $slug): string => htmlspecialchars(BASE_URL . 'post/' . urlencode($slug));
?>
<?php if ($filtered): ?>
    <nav class="crumbs" role="navigation" aria-label="<?= t('a11y.breadcrumb', 'Breadcrumb') ?>">
        <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
        <span class="crumb-sep" aria-hidden="true">/</span>
        <?php if ($filterCat !== ''): ?>
            <?= t('common.category', 'Category') ?>: <strong><?= htmlspecialchars($filterCat) ?></strong>
        <?php else: ?>
            <?= t('common.tag', 'Tag') ?>: <strong>#<?= htmlspecialchars($filterTag) ?></strong>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<header class="index-head">
    <h1><?= htmlspecialchars((string)$vars['pageTitle']) ?></h1>
    <?php if (!$filtered && $pageNo === 1): ?>
        <p class="index-desc"><?= htmlspecialchars(SITE_DESCRIPTION) ?></p>
    <?php endif; ?>
</header>

<?php if (empty($posts)): ?>
    <p class="empty-state"><?= t('common.no_posts', 'No posts yet.') ?></p>
<?php else: ?>
    <?php if ($lead !== null): ?>
        <article class="post-card post-lead">
            <?php if (!empty($lead['cover'])): ?>
                <a class="post-card-thumb" href="<?= $postUrl($lead['slug']) ?>">
                    <img src="<?= htmlspecialchars($lead['cover']) ?>" alt="" loading="lazy" decoding="async">
                </a>
            <?php endif; ?>
            <div class="post-lead-body">
                <p class="post-eyebrow">
                    <a class="post-cat" href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($lead['category'])) ?>"><?= htmlspecialchars($lead['category']) ?></a>
                    <span class="meta-sep" aria-hidden="true">&middot;</span>
                    <time datetime="<?= htmlspecialchars(date('c', strtotime($lead['date']))) ?>"><?= htmlspecialchars($fmt($lead['date'])) ?></time>
                </p>
                <h2 class="post-title"><a href="<?= $postUrl($lead['slug']) ?>"><?= htmlspecialchars($lead['title']) ?></a></h2>
                <?php if ($lead['excerpt'] !== ''): ?>
                    <p class="excerpt"><?= htmlspecialchars($lead['excerpt']) ?></p>
                <?php endif; ?>
                <?php if (!empty($lead['tags'])): ?>
                    <div class="post-tags">
                        <?php foreach ($lead['tags'] as $tag): ?>
                            <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($tag)) ?>">#<?= htmlspecialchars($tag) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="read-more" href="<?= $postUrl($lead['slug']) ?>"><?= t('post.read_more', 'Read the full article') ?> &rarr;</a>
            </div>
        </article>
    <?php endif; ?>

    <?php if (!empty($rows)): ?>
        <div class="post-list">
            <?php foreach ($rows as $post): ?>
                <article class="post-card<?= empty($post['cover']) ? ' post-card--plain' : '' ?>">
                    <?php if (!empty($post['cover'])): ?>
                        <a class="post-card-thumb" href="<?= $postUrl($post['slug']) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= htmlspecialchars($post['cover']) ?>" alt="" loading="lazy" decoding="async">
                        </a>
                    <?php endif; ?>
                    <div class="post-card-body">
                        <p class="meta">
                            <span><?= htmlspecialchars($post['category']) ?></span>
                            <span class="meta-sep" aria-hidden="true">&middot;</span>
                            <time datetime="<?= htmlspecialchars(date('c', strtotime($post['date']))) ?>"><?= htmlspecialchars($fmt($post['date'])) ?></time>
                        </p>
                        <h2 class="post-title"><a href="<?= $postUrl($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                        <?php if ($post['excerpt'] !== ''): ?>
                            <p class="excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php $__pgnav = \Wawoo\Core\Hook::filter('pagination.render', null, $vars); ?>
    <?php if (is_string($__pgnav) && $__pgnav !== ''): ?>
        <?= $__pgnav ?>
    <?php elseif (($vars['pages'] ?? 1) > 1): ?>
        <nav class="pagination" role="navigation" aria-label="<?= t('a11y.pagination', 'Pagination') ?>">
            <?php $baseUrl = htmlspecialchars(BASE_URL); ?>
            <?php if ($vars['page'] > 1): ?>
                <a href="<?= $baseUrl ?>?page=<?= (int)$vars['page'] - 1 ?>"><?= t('common.prev', '&laquo; Prev') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.prev', '&laquo; Prev') ?></span>
            <?php endif; ?>
            <span><?= sprintf(t('common.page', 'Page %d / %d'), (int)$vars['page'], (int)$vars['pages']) ?></span>
            <?php if ($vars['page'] < $vars['pages']): ?>
                <a href="<?= $baseUrl ?>?page=<?= (int)$vars['page'] + 1 ?>"><?= t('common.next', 'Next &raquo;') ?></a>
            <?php else: ?>
                <span class="disabled"><?= t('common.next', 'Next &raquo;') ?></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php Hook::fire('theme.index.after', $vars); ?>
