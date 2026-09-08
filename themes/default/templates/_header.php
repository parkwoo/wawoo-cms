<?php
/**
 * @var array $vars
 *  pageTitle, pageDescription, structuredType, canonical, ...
 */
use Wawoo\Core\Hook;
use Wawoo\Core\Posts;

if (!function_exists('t')) {
    function t(string $key, string $default = ''): string
    {
        return $default !== '' ? $default : $key;
    }
}

$seoTitle       = $vars['pageTitle']       ?? SITE_TITLE;
$seoDescription = $vars['pageDescription'] ?? SITE_DESCRIPTION;
$seoRobots      = 'index, follow';
$structuredType = $vars['structuredType']  ?? 'WebSite';
$canonical      = $vars['canonical']       ?? BASE_URL;
$post           = $vars['post']            ?? null;
$stats          = $vars['stats']           ?? Posts::stats();

// Let plugins (e.g. seo) tweak meta before output.
$meta = ['title' => $seoTitle, 'description' => $seoDescription, 'robots' => $seoRobots, 'type' => $structuredType, 'canonical' => $canonical];
Hook::fire('theme.head', $meta);
$seoTitle       = $meta['title'];
$seoDescription = $meta['description'];
$seoRobots      = $meta['robots'];
$structuredType = $meta['type'];
$canonical      = $meta['canonical'];
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)($_GET['mlang'] ?? DEFAULT_LANGUAGE)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($seoTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
    <meta name="robots" content="<?= htmlspecialchars($seoRobots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <link rel="stylesheet" href="<?= \Wawoo\Core\Theme::asset('css/style.css') ?>">
    <?php if ($post): ?>
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?= htmlspecialchars($post['title']) ?>">
        <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
        <meta property="article:published_time" content="<?= htmlspecialchars(date('c', strtotime($post['date']))) ?>">
        <meta property="article:section" content="<?= htmlspecialchars($post['category']) ?>">
        <?php if (!empty($post['cover'])): ?>
            <meta property="og:image" content="<?= htmlspecialchars(\Wawoo\Core\Url::absolute($post['cover'])) ?>">
        <?php endif; ?>
        <?php foreach ((array)$post['tags'] as $tag): ?>
            <meta property="article:tag" content="<?= htmlspecialchars($tag) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php Hook::fire('theme.head.after', $vars); ?>
</head>
<body>
<a class="skip-link" href="#main"><?= t('a11y.skip', 'Skip to content') ?></a>
<span class="top-anchor" id="wawoo-top" aria-hidden="true"></span>
<header class="site-header">
    <div class="container">
        <a class="site-brand" href="<?= htmlspecialchars(BASE_URL) ?>"><?= htmlspecialchars(SITE_TITLE) ?></a>
        <nav class="site-nav" role="navigation" aria-label="<?= t('nav.label', 'Primary') ?>">
            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="nav-menu">
                <span class="nav-toggle-label"><?= t('nav.menu', 'Menu') ?></span>
            </button>
            <?php $__menu = \Wawoo\Core\Hook::filter('theme.nav.items', null, $vars); ?>
            <?php if (is_string($__menu) && $__menu !== ''): ?>
                <div id="nav-menu" class="nav-menu"><?= $__menu ?></div>
            <?php else: ?>
                <?php
                    $__pagesAll = \Wawoo\Core\Pages::all();
                    $__catsAll  = $stats['categories'] ?? [];
                    $__pagesInline = array_slice($__pagesAll, 0, 4);
                    $__catsInline  = array_slice($__catsAll, 0, 3);
                    $__overflow    = array_slice($__pagesAll, 4);
                    $__overflowCats = array_slice($__catsAll, 3);
                ?>
                <div id="nav-menu" class="nav-menu">
                    <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
                    <?php foreach ($__pagesInline as $pg): ?>
                        <a href="<?= htmlspecialchars(BASE_URL) ?><?= htmlspecialchars($pg['slug']) ?>"><?= htmlspecialchars($pg['title']) ?></a>
                    <?php endforeach; ?>
                    <?php foreach ($__catsInline as $cat): ?>
                        <a href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($cat)) ?>"><?= htmlspecialchars($cat) ?></a>
                    <?php endforeach; ?>
                    <?php if ($__overflow !== [] || $__overflowCats !== []): ?>
                        <div class="nav-more">
                            <button type="button" class="nav-more-toggle" aria-expanded="false" aria-haspopup="true">
                                <?= t('nav.topics', 'Topics') ?>
                            </button>
                            <div class="nav-more-menu" hidden>
                                <?php foreach ($__overflow as $pg): ?>
                                    <a href="<?= htmlspecialchars(BASE_URL) ?><?= htmlspecialchars($pg['slug']) ?>"><?= htmlspecialchars($pg['title']) ?></a>
                                <?php endforeach; ?>
                                <?php if ($__overflow !== [] && $__overflowCats !== []): ?>
                                    <span class="nav-more-sep" role="separator"></span>
                                <?php endif; ?>
                                <?php foreach ($__overflowCats as $cat): ?>
                                    <a href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($cat)) ?>"><?= htmlspecialchars($cat) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php Hook::fire('theme.nav', $vars); ?>
        </nav>
    </div>
</header>
<main id="main" class="container">
