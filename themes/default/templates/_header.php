<?php
/**
 * @var array $vars
 *  pageTitle, pageDescription, structuredType, canonical, ...
 * @var \Wawoo\Core\Theme $theme (via $this)
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
<header class="site-header">
    <div class="container">
        <a class="site-brand" href="<?= htmlspecialchars(BASE_URL) ?>"><?= htmlspecialchars(SITE_TITLE) ?></a>
        <nav class="site-nav">
            <?php $__menu = \Wawoo\Core\Hook::filter('theme.nav.items', null, $vars); ?>
            <?php if (is_string($__menu) && $__menu !== ''): ?>
                <?= $__menu ?>
            <?php else: ?>
                <a href="<?= htmlspecialchars(BASE_URL) ?>"><?= t('nav.home', 'Home') ?></a>
                <?php foreach (\Wawoo\Core\Pages::all() as $pg): ?>
                    <a href="<?= htmlspecialchars(BASE_URL) ?><?= htmlspecialchars($pg['slug']) ?>"><?= htmlspecialchars($pg['title']) ?></a>
                <?php endforeach; ?>
                <?php foreach ($stats['categories'] as $cat): ?>
                    <a href="<?= htmlspecialchars(BASE_URL) ?>?cat=<?= urlencode(strtolower($cat)) ?>"><?= htmlspecialchars($cat) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php Hook::fire('theme.nav', $vars); ?>
        </nav>
    </div>
</header>
<main class="container">
