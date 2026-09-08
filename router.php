<?php
/**
 * Core router. Pretty URLs for the CMS itself: home, single posts and
 * category/tag filters. Static files pass through untouched.
 *
 * Every other URL is delegated to dispatch.php, which fires the
 * `core.request` hook so feature plugins (rss, search, admin, sitemap,
 * password gate, ...) can claim their own paths. If nothing claims the
 * request the theme's 404 template is rendered.
 *
 * Place this file at the root of the served sub-path and configure the
 * web server to route all requests to it (see README for nginx/apache).
 */

declare(strict_types=1);

$root = __DIR__;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');

// Direct file passthrough (assets, themes, posts files).
$candidate = $root . $path;
if ($path !== '/' && is_file($candidate)) {
    return false;
}

if ($path === '/' || $path === '') {
    require $root . '/index.php';
    return true;
}

// Multi-language content (mlang plugin): /<lang>/post/<slug> and
// /<lang>/<pageslug> serve posts/<slug>/index.<lang>.md and
// pages/<slug>/index.<lang>.md respectively (falling back to the default
// index.md when a translation is missing). The default language keeps its
// plain URLs. Accepted URL languages are hard-coded here with the default
// language implied; adding a language means extending this list, the same
// whitelist in post.php/page.php, and the $lang suffix resolution in
// Posts::mdFile / Pages::mdFile.
if (preg_match('#^/(ja|zh)/post/([a-zA-Z0-9_-]+)$#', $path, $m)) {
    $_GET['slug']  = $m[2];
    $_GET['mlang'] = $m[1];
    require $root . '/post.php';
    return true;
}

if (preg_match('#^/(ja|zh)/([a-zA-Z0-9_-]+)$#', $path, $m)) {
    $slug = $m[2];
    $bundle = $root . '/pages/' . $slug . '/index.md';
    $flat   = $root . '/pages/' . $slug . '.md';
    if (is_file($bundle) || is_file($flat)) {
        $_GET['slug']  = $slug;
        $_GET['mlang'] = $m[1];
        require $root . '/page.php';
        return true;
    }
    // No matching page: fall through so plugins can still claim the path.
}

if (preg_match('#^/post/([a-zA-Z0-9_-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require $root . '/post.php';
    return true;
}

if (preg_match('#^/cat/([^/]+)$#', $path, $m)) {
    $_GET['cat'] = $m[1];
    require $root . '/index.php';
    return true;
}

if (preg_match('#^/tag/([^/]+)$#', $path, $m)) {
    $_GET['tag'] = $m[1];
    require $root . '/index.php';
    return true;
}

// Fixed pages: /<slug> resolves to pages/<slug>/index.md (page bundle) or
// pages/<slug>.md. Plugins still get a chance for their own paths because
// dispatch.php runs when no page file matches.
if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $path, $m)) {
    $slug = $m[1];
    $bundle = $root . '/pages/' . $slug . '/index.md';
    $flat   = $root . '/pages/' . $slug . '.md';
    if (is_file($bundle) || is_file($flat)) {
        $_GET['slug'] = $slug;
        require $root . '/page.php';
        return true;
    }
}

// Not a core route and not a real file: give plugins a chance via
// dispatch.php (fires core.request), then 404 if unclaimed.
require $root . '/dispatch.php';
return true;
