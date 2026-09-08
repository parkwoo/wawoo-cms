<?php
/**
 * Tiny file cache used by Posts to avoid re-parsing on every request.
 * Cache files live in cache/ and are invalidated by clearCache().
 */

namespace Wawoo\Core;

final class Cache
{
    public static function get(string $key)
    {
        if (!defined('CACHE_ENABLED') || !CACHE_ENABLED) {
            return null;
        }
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        $ttl = defined('CACHE_DURATION') ? CACHE_DURATION : 3600;
        if ((time() - filemtime($file)) > $ttl) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return $data === null ? null : $data;
    }

    public static function set(string $key, $value): void
    {
        if (!defined('CACHE_ENABLED') || !CACHE_ENABLED) {
            return;
        }
        if (!is_dir(\CACHE_DIR)) {
            @mkdir(\CACHE_DIR, 0755, true);
        }
        file_put_contents(self::path($key), json_encode($value));
    }

    public static function forget(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function flush(): void
    {
        if (!is_dir(\CACHE_DIR)) {
            return;
        }
        foreach (glob(\CACHE_DIR . '*') as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private static function path(string $key): string
    {
        return \CACHE_DIR . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    }
}
