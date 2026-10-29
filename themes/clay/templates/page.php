<?php
/**
 * @var array $vars page, stats
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$page = $vars['page'];
?>
<div class="crumbs">
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
    / <?= htmlspecialchars($page['title']) ?>
</div>

<article class="post-article" id="page-<?= htmlspecialchars($page['slug']) ?>">
    <header class="post-head">
        <h1><?= htmlspecialchars($page['title']) ?></h1>
    </header>

    <?php if (!empty($page['cover'])): ?>
        <?php $coverCaption = (string)($page['meta']['cover_caption'] ?? ''); ?>
        <figure class="post-cover">
            <a class="post-zoom" href="<?= htmlspecialchars($page['cover']) ?>">
                <img src="<?= htmlspecialchars($page['cover']) ?>" alt="<?= htmlspecialchars((string)($page['meta']['cover_alt'] ?? $page['title'])) ?>"
                     loading="eager" fetchpriority="high" decoding="async">
            </a>
            <?php if ($coverCaption !== ''): ?>
                <figcaption><?= htmlspecialchars($coverCaption) ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endif; ?>

    <div class="post-content">
        <?= Hook::filter('post.html', $page['html'], $page) ?>
    </div>
</article>

<?php Hook::fire('theme.page.after', $vars); ?>
