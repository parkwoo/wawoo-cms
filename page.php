<?php
/**
 * Fixed page (about, privacy, ...). Rendered at its own top-level URL,
 * e.g. /about for pages/about/index.md.
 */

declare(strict_types=1);

require __DIR__ . '/includes/core/Bootstrap.php';
\Wawoo\Core\Bootstrap::init(__DIR__);

use Wawoo\Core\Hook;
use Wawoo\Core\Pages;
use Wawoo\Core\Theme;

$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
if ($slug === '' || strlen($slug) > 120) {
    header('HTTP/1.1 400 Bad Request');
    Theme::render('404', ['pageTitle' => 'Bad request']);
    exit;
}

$lang = '';
if (isset($_GET['mlang']) && in_array((string)$_GET['mlang'], ['ja', 'zh'], true)) {
    $lang = (string)$_GET['mlang'];
}

$page = Pages::get($slug, $lang);
if ($page === null) {
    header('HTTP/1.1 404 Not Found');
    Theme::render('404', ['pageTitle' => 'Not found', 'slug' => $slug]);
    exit;
}

// Canonical points at the translated URL only when a real translation was
// served; a missing translation that fell back to the default content keeps
// the default-language canonical URL.
$pagePrefix = $page['lang'] !== '' ? $page['lang'] . '/' : '';

$vars = [
    'pageTitle'       => $page['title'] . ' - ' . SITE_TITLE,
    'pageDescription' => $page['excerpt'] !== '' ? $page['excerpt'] : mb_substr(strip_tags($page['content']), 0, 160),
    'structuredType'  => 'WebPage',
    'canonical'       => BASE_URL . $pagePrefix . $page['slug'],
    'page'            => $page,
    'posts'           => [],
    'stats'           => \Wawoo\Core\Posts::stats(),
];

Hook::fire('page.before_render', $vars);
Hook::fire('page.before_render.' . $page['slug'], $vars);

Theme::render('page', $vars);
