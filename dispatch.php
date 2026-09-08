<?php
/**
 * Plugin page dispatcher.
 *
 * Any URL not handled by the core router (index/post/cat/tag/static files)
 * ends up here. Boot fires the `core.request` hook so feature plugins
 * (rss, search, admin, sitemap, ...) can claim the path: each handler
 * inspects REQUEST_URI, renders its own page and exits. If no plugin
 * claims the request we render the theme's 404 template.
 */

declare(strict_types=1);

require __DIR__ . '/includes/core/Bootstrap.php';
\Wawoo\Core\Bootstrap::init(__DIR__);

use Wawoo\Core\Theme;

http_response_code(404);
Theme::render('404', [
    'pageTitle'       => '404 - ' . SITE_TITLE,
    'pageDescription' => 'Page not found',
    'structuredType'  => 'WebPage',
    'canonical'       => BASE_URL,
]);
