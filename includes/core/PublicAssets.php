<?php
/**
 * Public static asset allow-list.
 *
 * Only files that are safe to serve verbatim over HTTP count as "public":
 * theme assets, uploads and post/page bundle media. Everything else (cache
 * files, includes, plugin data, markdown, JSON, dotfiles, config secrets)
 * is rejected so the router never short-circuits to a raw file passthrough.
 *
 * The decision is pure URL-path logic and does not require the file to
 * exist; router.php additionally resolves realpath() and requires the file
 * to be a real file under the site root.
 */

namespace Wawoo\Core;

final class PublicAssets
{
    /** Extensions never served verbatim, wherever they appear. */
    private const DENY_EXT = [
        'php', 'phtml', 'md', 'markdown', 'json', 'log', 'lock',
        'env', 'sh', 'sql', 'bak', 'old', 'tmp',
    ];

    /** URL prefixes that are never public regardless of extension. */
    private const DENY_PREFIX = ['/cache/', '/includes/', '/plugins/', '/config'];

    /** /themes/<name>/assets/**: images, fonts, CSS/JS and media. */
    private const THEME_EXT = [
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico',
        'css', 'js', 'mjs', 'map',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'mp3', 'ogg', 'wav',
    ];

    /** /uploads/**, /posts/<slug>/*, /pages/<slug>/*: media plus documents. */
    private const PUBLIC_EXT = [
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico',
        'css', 'js', 'mjs', 'map',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'mp3', 'ogg', 'wav',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip',
    ];

    public static function allowed(string $root, string $urlPath): bool
    {
        $path = '/' . trim($urlPath, '/');
        if ($path === '/') {
            return false;
        }

        // Sensitive directories and config secrets are never served.
        foreach (self::DENY_PREFIX as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        // No dotfiles, hidden directories or '..' traversal segments.
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if (str_starts_with($segment, '.')) {
                return false;
            }
        }

        // Root leaf files: only favicon.ico is statically public; robots.txt
        // and sitemap.xml are handled by the seo plugin via dispatch.
        if ($path === '/favicon.ico') {
            return true;
        }

        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::DENY_EXT, true)) {
            return false;
        }

        if (preg_match('#^/themes/[a-zA-Z0-9_-]+/assets/.+$#', $path) === 1) {
            return in_array($ext, self::THEME_EXT, true);
        }
        if (preg_match('#^/uploads/.+$#', $path) === 1) {
            // User-uploaded SVG can carry script; serve it as a download only
            // (theme-provided and author-authored bundle SVGs stay public).
            return in_array($ext, self::PUBLIC_EXT, true) && $ext !== 'svg';
        }
        if (preg_match('#^/(posts|pages)/([a-zA-Z0-9_-]+)/[^/]+$#', $path, $m) === 1) {
            if (!in_array($ext, self::PUBLIC_EXT, true)) {
                return false;
            }
            return !self::bundleIsDraft($root, $m[1], $m[2]);
        }
        return false;
    }

    /** True when a post/page bundle's markdown marks it non-published. */
    private static function bundleIsDraft(string $root, string $dir, string $slug): bool
    {
        $files = glob(rtrim($root, '/') . '/' . $dir . '/' . $slug . '/index*.md') ?: [];
        foreach ($files as $file) {
            $raw = (string)@file_get_contents($file);
            if (preg_match('/^---\s*\n(.*?)\n---/s', $raw, $fm)
                && preg_match('/^\s*status\s*:\s*["\']?([A-Za-z_-]+)/mi', $fm[1], $sm)
                && strtolower($sm[1]) !== 'published') {
                return true;
            }
        }
        return false;
    }
}
