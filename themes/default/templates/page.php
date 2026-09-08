<?php
/**
 * @var array $vars
 *  page, stats, ...
 */
use Wawoo\Core\Hook;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$page = $vars['page'];
$coverCaption = (string)($page['meta']['cover_caption'] ?? '');
$coverAlt     = (string)($page['meta']['cover_alt'] ?? $page['title']);
?>
<nav class="crumbs" role="navigation" aria-label="<?= t('a11y.breadcrumb', 'Breadcrumb') ?>">
    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
    <span class="crumb-sep" aria-hidden="true">/</span>
    <span><?= htmlspecialchars($page['title']) ?></span>
</nav>

<article class="post-content page-content">
    <header class="article-head">
        <h1><?= htmlspecialchars($page['title']) ?></h1>
        <?php if ($page['excerpt'] !== ''): ?>
            <p class="article-standfirst"><?= htmlspecialchars($page['excerpt']) ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($page['cover'])): ?>
        <figure class="post-cover">
            <a class="post-zoom" href="<?= htmlspecialchars($page['cover']) ?>">
                <img src="<?= htmlspecialchars($page['cover']) ?>" alt="<?= htmlspecialchars($coverAlt) ?>"
                     loading="eager" fetchpriority="high" decoding="async">
            </a>
            <?php if ($coverCaption !== ''): ?>
                <figcaption><?= htmlspecialchars($coverCaption) ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endif; ?>

    <div class="post-body">
        <?= Hook::filter('post.html', $page['html'], $page) ?>
    </div>
</article>

<?php Hook::fire('theme.page.after', $vars); ?>
