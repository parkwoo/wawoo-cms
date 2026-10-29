<?php
/**
 * Fixed pages (about, contact, privacy, ...).
 *
 * A page is content rendered at its own top-level URL (/about), as opposed
 * to posts which live under /post/<slug> and appear in feeds/lists.
 *
 * Layout: pages/<slug>/index.md (page bundle, images next to the markdown)
 * or the legacy flat pages/<slug>.md.
 */

namespace Wawoo\Core;

final class Pages
{
    public static function dir(): string
    {
        return defined('PAGES_DIR') ? rtrim(\PAGES_DIR, '/\\') . DIRECTORY_SEPARATOR : '';
    }

    /**
     * Resolve the markdown file backing a page slug, or null.
     * With a non-empty $lang the suffixed pages/<slug>/index.<lang>.md is
     * preferred, falling back to the bundle's index.md when the translation
     * is missing; the flat pages/<slug>.md form is only used for the default
     * language.
     */
    public static function mdFile(string $slug, string $lang = ''): ?string
    {
        $dir = self::dir();
        if ($dir === '' || !is_dir($dir)) {
            return null;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        if ($safe === '') {
            return null;
        }
        $bundle = $dir . $safe . DIRECTORY_SEPARATOR . 'index.md';
        $langSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', $lang);
        if ($langSafe !== '') {
            $translated = $dir . $safe . DIRECTORY_SEPARATOR . 'index.' . $langSafe . '.md';
            if (is_file($translated) && is_readable($translated)) {
                return $translated;
            }
            return is_file($bundle) && is_readable($bundle) ? $bundle : null;
        }
        if (is_file($bundle) && is_readable($bundle)) {
            return $bundle;
        }
        $flat = $dir . $safe . '.md';
        return is_file($flat) && is_readable($flat) ? $flat : null;
    }

    /** Web base for a page's own assets (its bundle folder), or ''. */
    public static function assetBase(string $slug): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        $dir  = self::dir();
        if ($safe !== '' && $dir !== '' && is_dir($dir . $safe)) {
            return BASE_PATH . 'pages/' . $safe . '/';
        }
        return '';
    }

    /** List all pages (flat slug list), sorted by frontmatter order then slug. */
    public static function all(): array
    {
        $cached = Cache::get('pages-all');
        if ($cached !== null) {
            return $cached;
        }
        $dir = self::dir();
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $slugs = [];
        foreach (glob($dir . '*/index.md') as $f) {
            $slugs[basename(dirname($f))] = true;
        }
        foreach (glob($dir . '*.md') as $f) {
            $slugs[basename($f, '.md')] = true;
        }
        $pages = [];
        foreach (array_keys($slugs) as $slug) {
            $p = self::get($slug);
            if ($p !== null) {
                $pages[] = $p;
            }
        }
        usort($pages, function ($a, $b) {
            $oa = (int)($a['meta']['order'] ?? 100);
            $ob = (int)($b['meta']['order'] ?? 100);
            if ($oa !== $ob) {
                return $oa - $ob;
            }
            return strcmp($a['slug'], $b['slug']);
        });
        Cache::set('pages-all', $pages);
        return $pages;
    }

    /**
     * Get one page, or null when it does not exist. With a non-empty $lang
     * the translated index.<lang>.md is served when present (see mdFile).
     * The returned array carries a 'lang' key: the language actually served,
     * or '' for the default-language file.
     */
    public static function get(string $slug, string $lang = ''): ?array
    {
        $file = self::mdFile($slug, $lang);
        if ($file === null) {
            return null;
        }
        $parsed = Frontmatter::parse((string)file_get_contents($file));
        $base   = self::assetBase($slug);
        $cover  = (string)($parsed['meta']['cover'] ?? '');
        if ($cover !== '') {
            if (preg_match('#^https?://#i', $cover) || str_starts_with($cover, '//') || str_starts_with($cover, '/')) {
                // absolute http(s) or root-relative: keep
            } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $cover)) {
                $cover = ''; // reject unsafe schemes (javascript:, data:, ...)
            } elseif ($base !== '') {
                $cover = $base . $cover;
            }
        }
        $langUsed = '';
        if (preg_match('/^index\.([a-zA-Z0-9_-]+)\.md$/', basename($file), $m)) {
            $langUsed = $m[1];
        }
        $page = [
            'slug'    => $slug,
            'lang'    => $langUsed,
            'title'   => (string)($parsed['meta']['title'] ?? ucfirst($slug)),
            'excerpt' => (string)($parsed['meta']['excerpt'] ?? ''),
            'cover'   => $cover,
            'meta'    => $parsed['meta'],
            'content' => $parsed['content'],
            'html'    => Markdown::render($parsed['content'], true, true, $base),
        ];
        Hook::fire('page.load', $page);
        Hook::fire('page.load.' . $slug, $page);
        return $page;
    }
}
