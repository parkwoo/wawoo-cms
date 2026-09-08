<?php
namespace Wawoo\Plugin\Contact;

/**
 * File-backed storage for submitted inquiries plus the per-IP rate-limit
 * counters. Inbox messages live as one JSON file per submission in
 * CACHE_DIR/contact/, rate counters as key-scoped JSON files under
 * CACHE_DIR/ named contact_rate_<ip>.json. All writes use flock + atomic
 * rename so concurrent requests never corrupt a file.
 */
final class Store
{
    private const MAX = 200;

    public static function dir(): string
    {
        return rtrim(\CACHE_DIR, '/\\') . '/contact/';
    }

    /**
     * Writes one submission to inbox-<unix-ts>-<rand>.json and returns the
     * file name, or an empty string when the write failed.
     */
    public static function save(array $submission): string
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = 'inbox-' . time() . '-' . bin2hex(random_bytes(4)) . '.json';
        $json = json_encode($submission, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || !self::lockedWrite($dir . $name, $json)) {
            return '';
        }
        return $name;
    }

    /** All submissions, newest first, capped at self::MAX entries. */
    public static function all(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $items = [];
        foreach (glob($dir . 'inbox-*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $data['file'] = basename($file);
            $data['_mtime'] = (int)filemtime($file);
            $items[] = $data;
        }
        usort($items, static fn($a, $b) => $b['_mtime'] <=> $a['_mtime']);
        $items = array_slice($items, 0, self::MAX);
        foreach ($items as $i => $item) {
            unset($items[$i]['_mtime']);
        }
        return array_values($items);
    }

    public static function count(): int
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return 0;
        }
        $files = glob($dir . 'inbox-*.json');
        return $files === false ? 0 : count($files);
    }

    /** Deletes a single inbox file by name; only inbox-*.json names are allowed. */
    public static function delete(string $name): bool
    {
        $name = basename($name);
        if (preg_match('/^inbox-[a-zA-Z0-9._-]+\.json$/', $name) !== 1) {
            return false;
        }
        $file = self::dir() . $name;
        return is_file($file) && @unlink($file);
    }

    public static function rateFile(string $ip): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
        return rtrim(\CACHE_DIR, '/\\') . '/contact_rate_' . $safe . '.json';
    }

    public static function rateGet(string $ip): array
    {
        $file = self::rateFile($ip);
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function rateSet(string $ip, array $value): void
    {
        self::lockedWrite(self::rateFile($ip), (string)json_encode($value));
    }

    private static function lockedWrite(string $file, string $json): bool
    {
        $lock = $file . '.lock';
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh   = @fopen($lock, 'c');
        if ($fh === false) {
            return @file_put_contents($file, $json) !== false;
        }
        flock($fh, LOCK_EX);
        $ok = @file_put_contents($tmp, $json) !== false;
        if ($ok) {
            $ok = @rename($tmp, $file);
        }
        if (!$ok) {
            @unlink($tmp);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($lock);
        return $ok;
    }
}
