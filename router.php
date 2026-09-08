<?php
/**
 * Core router. Pretty URLs for the CMS itself: home, single posts and
 * category/tag filters.
 *
 * Static passthrough is strictly allow-listed (see PublicAssets): only
 * theme assets, uploads, post/page bundle media and /favicon.ico may be
 * served verbatim. Everything else — cache JSON, raw markdown (including
 * drafts), plugin data, includes, config secrets, dotfiles — falls through
 * to dispatch.php, which 404s.
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

require __DIR__ . '/includes/core/PublicAssets.php';

$root = __DIR__;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');

// Static passthrough only for allow-listed public assets. realpath() guards
// against traversal/symlink escapes: the resolved file must be a real file
// inside the site root. Any other existing file is NOT served here and is
// left to dispatch.php (404) below.
if ($path !== '/' && \Wawoo\Core\PublicAssets::allowed($root, $path)) {
    $file    = realpath($root . $path);
    $rootDir = realpath($root);
    if ($file !== false
        && $rootDir !== false
        && str_starts_with($file, $rootDir . DIRECTORY_SEPARATOR)
        && is_file($file)) {
        return false;
    }
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

// Anything not served statically and not a core route goes to dispatch.php
// (fires core.request), then 404s if unclaimed.
require $root . '/dispatch.php';
return true;
