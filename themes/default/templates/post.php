<?php
/**
 * @var array $vars
 *  post, stats, ...
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
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a> /
    <a href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($post['category'])) ?>"><?= htmlspecialchars($post['category']) ?></a>
</div>

<article class="post-content">
    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <div class="meta">
        <time><?= htmlspecialchars($post['date']) ?></time>
        <?php if ($post['author'] !== ''): ?>
            &middot; <?= t('common.by', 'by') ?> <?= htmlspecialchars($post['author']) ?>
        <?php endif; ?>
        <?php if (!empty($post['tags'])): ?>
            &middot; <?php foreach ($post['tags'] as $tag): ?>
                <a class="tag" href="<?= htmlspecialchars(BASE_URL) ?>?tag=<?= urlencode(strtolower($tag)) ?>">#<?= htmlspecialchars($tag) ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

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

    <?= Hook::filter('post.html', $post['html'], $post) ?>
</article>

<hr>
<p><a href="<?= htmlspecialchars(BASE_URL) ?>">&larr; <?= t('nav.back_home', 'Back to home') ?></a></p>

<?php Hook::fire('theme.post.after', $vars); ?>
