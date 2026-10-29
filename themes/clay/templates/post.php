<?php
/**
 * @var array $vars post, stats
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$post = $vars['post'];
?>
<div class="crumbs">
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
    / <a href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($post['category'])) ?>"><?= htmlspecialchars($post['category']) ?></a>
</div>

<article class="post-article" id="post-<?= htmlspecialchars($post['slug']) ?>">
    <header class="post-head">
        <?php if ($post['category'] !== ''): ?>
            <p class="post-kicker">
                <a class="cat" href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($post['category'])) ?>"><?= htmlspecialchars($post['category']) ?></a>
            </p>
        <?php endif; ?>
        <h1><?= htmlspecialchars($post['title']) ?></h1>
        <div class="meta">
            <time datetime="<?= htmlspecialchars((string)$post['date']) ?>"><?= htmlspecialchars($post['date']) ?></time>
            <?php if ($post['author'] !== ''): ?>
                &middot; <?= t('common.by', 'by') ?> <?= htmlspecialchars($post['author']) ?>
            <?php endif; ?>
            <?php if (!empty($post['tags'])): ?>
                <?php foreach ($post['tags'] as $tag): ?>
                    <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($tag)) ?>">#<?= htmlspecialchars($tag) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($post['cover'])): ?>
        <?php $coverCaption = (string)($post['meta']['cover_caption'] ?? ''); ?>
        <figure class="post-cover">
            <a class="post-zoom" href="<?= htmlspecialchars($post['cover']) ?>">
                <img src="<?= htmlspecialchars($post['cover']) ?>" alt="<?= htmlspecialchars((string)($post['meta']['cover_alt'] ?? $post['title'])) ?>"
                     loading="eager" fetchpriority="high" decoding="async">
            </a>
            <?php if ($coverCaption !== ''): ?>
                <figcaption><?= htmlspecialchars($coverCaption) ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endif; ?>

    <div class="post-content">
        <?= Hook::filter('post.html', $post['html'], $post) ?>
    </div>
</article>

<p class="back-link"><a href="<?= htmlspecialchars(BASE_URL) ?>">&larr; <?= t('nav.back_home', 'Back to home') ?></a></p>

<?php Hook::fire('theme.post.after', $vars); ?>
