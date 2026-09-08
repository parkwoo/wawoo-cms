<?php
/**
 * Blog index: post list with category/tag filters & pagination.
 */

declare(strict_types=1);

require __DIR__ . '/includes/core/Bootstrap.php';
\Wawoo\Core\Bootstrap::init(__DIR__);

use Wawoo\Core\Hook;
use Wawoo\Core\Posts;
use Wawoo\Core\Theme;

$filterCat  = isset($_GET['cat'])  ? strtolower(trim((string)$_GET['cat']))  : '';
$filterTag  = isset($_GET['tag'])  ? strtolower(trim((string)$_GET['tag']))  : '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = (int)POSTS_PER_PAGE;

if ($filterCat !== '') {
    $posts     = Posts::byCategory($filterCat);
    $pageTitle = ucfirst($filterCat) . ' - ' . SITE_TITLE;
} elseif ($filterTag !== '') {
    $posts     = Posts::byTag($filterTag);
    $pageTitle = '#' . $filterTag . ' - ' . SITE_TITLE;
} else {
    $posts     = Posts::all();
    $pageTitle = SITE_TITLE;
}

$total    = count($posts);
$pages    = (int)ceil($total / $perPage);
$posts    = array_slice($posts, ($page - 1) * $perPage, $perPage);
$stats    = Posts::stats();

$canonical = BASE_URL;
if ($filterCat !== '') {
    $canonical .= '?cat=' . urlencode($filterCat);
} elseif ($filterTag !== '') {
    $canonical .= '?tag=' . urlencode($filterTag);
}
if ($page > 1) {
    $canonical .= (strpos($canonical, '?') === false ? '?' : '&') . 'page=' . $page;
}

$vars = [
    'pageTitle'       => $pageTitle,
    'pageDescription' => SITE_DESCRIPTION,
    'structuredType'  => 'CollectionPage',
    'canonical'       => $canonical,
    'posts'           => $posts,
    'stats'           => $stats,
    'page'            => $page,
    'pages'           => $pages,
    'filterCat'       => $filterCat,
    'filterTag'       => $filterTag,
];

Hook::fire('index.before_render', $vars);
Hook::fire('index.before_render.' . ($filterCat ? 'cat' : ($filterTag ? 'tag' : 'all')), $vars);

Theme::render('index', $vars);
