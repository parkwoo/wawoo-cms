<?php
/**
 * Post loader: reads posts/ directory, parses frontmatter + body,
 * caches the index, and provides filtered accessors.
 */

namespace Wawoo\Core;

final class Posts
{
    /**
     * Resolve the markdown file backing a slug. Page bundles live in
     * posts/<slug>/index.md; the legacy flat form posts/<slug>.md is still
     * supported. Bundle wins when both exist.
     *
     * With a non-empty $lang the suffixed posts/<slug>/index.<lang>.md is
     * preferred, falling back to the bundle's index.md when the translation
     * is missing. The flat posts/<slug>.md form is only ever used for the
     * default language.
     */
    public static function mdFile(string $slug, string $lang = ''): ?string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        $langSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', $lang);
        if ($safe === '') {
            return null;
        }
        $dir = \POSTS_DIR;
        $bundle = $dir . $safe . DIRECTORY_SEPARATOR . 'index.md';
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

    /** Web base for a post's own assets (its bundle folder), or ''. */
    public static function assetBase(string $slug): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        $dir = \POSTS_DIR;
        if ($safe !== '' && is_dir($dir . $safe)) {
            return BASE_PATH . 'posts/' . $safe . '/';
        }
        return '';
    }

    /**
     * Permanently delete a post: removes the whole page-bundle directory
     * (md + images) when present, otherwise the legacy flat .md file.
     */
    public static function deleteBySlug(string $slug): bool
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        if ($safe === '') {
            return false;
        }
        $dir = \POSTS_DIR;
        $bundleDir = $dir . $safe;
        if (is_dir($bundleDir)) {
            if (!is_file($bundleDir . DIRECTORY_SEPARATOR . 'index.md')) {
                return false; // not a post bundle, refuse to nuke arbitrary dir
            }
            self::rmDir($bundleDir);
            return !is_dir($bundleDir);
        }
        $flat = $dir . $safe . '.md';
        if (is_file($flat)) {
            @unlink($flat);
            return !is_file($flat);
        }
        return false;
    }

    private static function rmDir(string $dir): void
    {
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $e;
            if (is_dir($p) && !is_link($p)) {
                self::rmDir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    /**
     * Atomically set a post's publish state by rewriting its frontmatter.
     *
     * Statuses: published | draft | scheduled. For scheduled posts a
     * publish_at timestamp (unix int or 'YYYY-MM-DD[ HH:MM[:SS]]') is
     * stored in frontmatter; due() promotes them once time passes.
     * Returns the new status or null on failure.
     */
    public static function setStatus(string $slug, string $status, string $publishAt = ''): ?string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['published', 'draft', 'scheduled'], true)) {
            return null;
        }
        $file = self::mdFile($slug);
        if ($file === null) {
            return null;
        }
        $raw = (string)file_get_contents($file);
        if (!preg_match('/^---\s*\n(.*?)\n---/s', $raw, $m)) {
            return null;
        }
        $fm   = $m[1];
        $meta = Frontmatter::parse($raw)['meta'];
        if (strpos($fm, "\nstatus:") === false && strpos($fm, "\nstatus: ") === false && !preg_match('/^status:/m', $fm)) {
            $fm .= "\nstatus: " . $status;
        } else {
            $fm = preg_replace('/^status:.*$/m', 'status: ' . $status, $fm, 1);
        }
        if ($status === 'scheduled') {
            $ts = self::normalizeTs($publishAt);
            if ($ts === null) {
                return null;
            }
            if (preg_match('/^publish_at:.*$/m', $fm)) {
                $fm = preg_replace('/^publish_at:.*$/m', 'publish_at: ' . $ts, $fm, 1);
            } else {
                $fm .= "\npublish_at: " . $ts;
            }
        } else {
            $fm = preg_replace('/^publish_at:.*$/m', '', $fm);
        }
        $body = substr($raw, strlen($m[0]));
        $new  = "---\n" . $fm . "\n---\n" . ltrim($body, "\r\n");
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $new) === false) {
            return null;
        }
        @rename($tmp, $file);
        Cache::flush();
        return $status;
    }

    /** Normalize a publish_at value to a unix timestamp string. */
    public static function normalizeTs(string $value): ?int
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        $ts = is_numeric($v) ? (int)$v : strtotime($v);
        return $ts !== false && $ts > 0 ? $ts : null;
    }

    /**
     * Promote scheduled posts whose publish_at has passed. Returns the
     * slugs that were published. Intended to be called once per request
     * (or from cron) so timed publishing needs no background daemon.
     */
    public static function due(): array
    {
        $dir = \POSTS_DIR;
        $published = [];
        $now = time();
        foreach (glob($dir . '*/index.md') ?: [] as $file) {
            $slug = basename(dirname($file));
            $parsed = Frontmatter::parse((string)file_get_contents($file));
            if (($parsed['meta']['status'] ?? '') !== 'scheduled') {
                continue;
            }
            $at = self::normalizeTs((string)($parsed['meta']['publish_at'] ?? ''));
            if ($at !== null && $at <= $now) {
                if (self::setStatus($slug, 'published') !== null) {
                    $published[] = $slug;
                }
            }
        }
        foreach (glob($dir . '*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            if (is_dir($dir . $slug)) {
                continue; // bundle covers this slug
            }
            $parsed = Frontmatter::parse((string)file_get_contents($file));
            if (($parsed['meta']['status'] ?? '') !== 'scheduled') {
                continue;
            }
            $at = self::normalizeTs((string)($parsed['meta']['publish_at'] ?? ''));
            if ($at !== null && $at <= $now) {
                if (self::setStatus($slug, 'published') !== null) {
                    $published[] = $slug;
                }
            }
        }
        return array_values(array_unique($published));
    }

    /** Get all posts (optionally including drafts), sorted newest first. */
    public static function all(bool $includeDrafts = false): array
    {
        $cacheKey = 'posts-' . ($includeDrafts ? 'all' : 'pub');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $dir = \POSTS_DIR;
        $out = [];
        $seen = [];
        foreach (glob($dir . '*/index.md') as $file) {
            $slug = basename(dirname($file));
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $post = self::bySlug($slug, $includeDrafts);
            if ($post !== null) {
                $out[] = $post;
            }
        }
        foreach (glob($dir . '*.md') as $file) {
            $slug = basename($file, '.md');
            if (isset($seen[$slug])) {
                continue; // bundle already covered this slug
            }
            $seen[$slug] = true;
            $post = self::bySlug($slug, $includeDrafts);
            if ($post !== null) {
                $out[] = $post;
            }
        }
        usort($out, function ($a, $b) {
            $ta = strtotime($a['date'] ?? '1970-01-01') ?: 0;
            $tb = strtotime($b['date'] ?? '1970-01-01') ?: 0;
            return $tb - $ta;
        });
        Cache::set($cacheKey, $out);
        return $out;
    }

    /**
     * Get a single post by slug. With a non-empty $lang the translated
     * index.<lang>.md is served when present (see mdFile). The returned
     * array always carries a 'lang' key: the language actually served, or ''
     * for the default-language file.
     */
    public static function bySlug(string $slug, bool $includeDrafts = false, string $lang = ''): ?array
    {
        $file = self::mdFile($slug, $lang);
        if ($file === null) {
            return null;
        }
        $parsed = Frontmatter::parse(file_get_contents($file));
        $status = $parsed['meta']['status'] ?? 'published';
        if ($status !== 'published' && !$includeDrafts) {
            return null;
        }
        $assetBase = self::assetBase($slug);
        $cover = (string)($parsed['meta']['cover'] ?? '');
        if ($cover !== '') {
            if (preg_match('#^https?://#i', $cover) || str_starts_with($cover, '//') || str_starts_with($cover, '/')) {
                // absolute http(s) or root-relative: keep
            } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $cover)) {
                $cover = ''; // reject unsafe schemes (javascript:, data:, ...)
            } elseif ($assetBase !== '') {
                $cover = $assetBase . $cover;
            }
        }
        $langUsed = '';
        if (preg_match('/^index\.([a-zA-Z0-9_-]+)\.md$/', basename($file), $m)) {
            $langUsed = $m[1];
        }
        $post = [
            'slug'    => $slug,
            'lang'    => $langUsed,
            'title'   => (string)($parsed['meta']['title'] ?? $slug),
            'date'    => (string)($parsed['meta']['date'] ?? date('Y-m-d')),
            'excerpt' => (string)($parsed['meta']['excerpt'] ?? ''),
            'category'=> (string)($parsed['meta']['category'] ?? 'General'),
            'tags'    => (array)($parsed['meta']['tags'] ?? []),
            'author'  => (string)($parsed['meta']['author'] ?? ''),
            'cover'   => $cover,
            'status'  => $status,
            'meta'    => $parsed['meta'],
            'content' => $parsed['content'],
            'html'    => Markdown::render($parsed['content'], true, true, $assetBase),
        ];
        Hook::fire('post.load', $post);
        Hook::fire('post.load.' . $slug, $post);
        return $post;
    }

    /** Filter by category (case-insensitive). */
    public static function byCategory(string $category, bool $includeDrafts = false): array
    {
        $cat = strtolower($category);
        return array_values(array_filter(self::all($includeDrafts), function ($p) use ($cat) {
            return strtolower($p['category']) === $cat;
        }));
    }

    /** Filter by tag. */
    public static function byTag(string $tag, bool $includeDrafts = false): array
    {
        $needle = strtolower($tag);
        return array_values(array_filter(self::all($includeDrafts), function ($p) use ($needle) {
            foreach ((array)$p['tags'] as $t) {
                if (strtolower($t) === $needle) {
                    return true;
                }
            }
            return false;
        }));
    }

    /** Full-text search over title, excerpt, content. */
    public static function search(string $query, bool $includeDrafts = false): array
    {
        $needle = strtolower($query);
        if ($needle === '') {
            return [];
        }
        return array_values(array_filter(self::all($includeDrafts), function ($p) use ($needle) {
            $hay = strtolower($p['title'] . ' ' . $p['excerpt'] . ' ' . strip_tags($p['content']));
            return strpos($hay, $needle) !== false;
        }));
    }

    /** Distinct categories (sorted). */
    public static function categories(bool $includeDrafts = false): array
    {
        $cats = [];
        foreach (self::all($includeDrafts) as $p) {
            if (!in_array($p['category'], $cats, true)) {
                $cats[] = $p['category'];
            }
        }
        sort($cats);
        return $cats;
    }

    /** Distinct tags (sorted). */
    public static function tags(bool $includeDrafts = false): array
    {
        $tags = [];
        foreach (self::all($includeDrafts) as $p) {
            foreach ((array)$p['tags'] as $t) {
                if (!in_array($t, $tags, true)) {
                    $tags[] = $t;
                }
            }
        }
        sort($tags);
        return $tags;
    }

    /** Stats summary used by theme sidebars. Cached under its own key. */
    public static function stats(bool $includeDrafts = false): array
    {
        $cacheKey = 'stats-' . ($includeDrafts ? 'all' : 'pub');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $all  = self::all($includeDrafts);
        $cats = [];
        $tags = [];
        foreach ($all as $p) {
            if (!in_array($p['category'], $cats, true)) {
                $cats[] = $p['category'];
            }
            foreach ((array)$p['tags'] as $t) {
                if (!in_array($t, $tags, true)) {
                    $tags[] = $t;
                }
            }
        }
        sort($cats);
        sort($tags);
        $stats = [
            'post_count'     => count($all),
            'category_count' => count($cats),
            'tag_count'      => count($tags),
            'categories'     => $cats,
            'tags'           => $tags,
        ];
        Cache::set($cacheKey, $stats);
        return $stats;
    }
}
