<?php
/**
 * Single post page.
 */

declare(strict_types=1);

require __DIR__ . '/includes/core/Bootstrap.php';
\Wawoo\Core\Bootstrap::init(__DIR__);

use Wawoo\Core\Hook;
use Wawoo\Core\Posts;
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

$post = Posts::bySlug($slug, false, $lang);
if ($post === null) {
    header('HTTP/1.1 404 Not Found');
    Theme::render('404', ['pageTitle' => 'Not found', 'slug' => $slug]);
    exit;
}

// Canonical points at the translated URL only when a real translation was
// served; a missing translation that fell back to the default content keeps
// the default-language canonical URL.
$postPath = $post['lang'] !== '' ? $post['lang'] . '/post/' : 'post/';

$vars = [
    'pageTitle'       => $post['title'] . ' - ' . SITE_TITLE,
    'pageDescription' => $post['excerpt'] !== '' ? $post['excerpt'] : mb_substr(strip_tags($post['content']), 0, 160),
    'structuredType'  => 'BlogPosting',
    'canonical'       => BASE_URL . $postPath . $post['slug'],
    'post'            => $post,
    'stats'           => Posts::stats(),
];

Hook::fire('post.before_render', $vars);
Hook::fire('post.before_render.' . $post['slug'], $vars);

Theme::render('post', $vars);
