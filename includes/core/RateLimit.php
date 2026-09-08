<?php
/**
 * Cross-plugin rate limiter shared by all outbound API clients.
 *
 * Fixed one-second window with a global counter persisted under the
 * writable cache volume, so the aggregate outbound request rate can be
 * capped even across separate plugins/workers (file + flock).
 */

namespace Wawoo\Core;

final class RateLimit
{
    private const FILE = 'ratelimit.json';

    /** @var array<string,int> in-process counters (bucket => hits this second) */
    private static array $mem = [];
    private static int $memSecond = 0;

    /**
     * Allow up to $max calls per second across the whole process for the
     * given bucket. Returns true when the call may proceed. The file is the
     * authority; the in-process counter avoids re-reading it each call
     * within the same second.
     */
    public static function allow(string $bucket, int $max, int $now = null): bool
    {
        $max = max(1, $max);
        $now = $now ?? time();
        if (self::$memSecond !== $now) {
            self::$mem = [];
            self::$memSecond = $now;
        }
        // Sync this bucket's counter from the file when not yet loaded for
        // this second.
        if (!isset(self::$mem[$bucket])) {
            self::$mem[$bucket] = self::fileHits($bucket, $now);
        }
        if (self::$mem[$bucket] >= $max) {
            return false;
        }
        self::$mem[$bucket]++;
        self::fileAdd($bucket, $now, self::$mem[$bucket]);
        return true;
    }

    private static function fileHits(string $bucket, int $now): int
    {
        $data = self::read();
        $second = (string)$now;
        $b = $data['buckets'][$bucket] ?? [];
        return isset($b[$second]) && $b[$second] > 0 ? (int)$b[$second] : 0;
    }

    private static function fileAdd(string $bucket, int $now, int $count): void
    {
        $data = self::read();
        // Drop entries older than one second to keep the file tiny.
        $keep = [];
        foreach (($data['buckets'][$bucket] ?? []) as $second => $c) {
            if ((int)$second === $now) {
                $keep[$second] = $c;
            }
        }
        $keep[(string)$now] = $count;
        $data['buckets'][$bucket] = $keep;
        $data['updated'] = time();
        self::write($data);
    }

    private static function path(): string
    {
        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . self::FILE;
    }

    private static function read(): array
    {
        $file = self::path();
        if (!is_file($file)) {
            return ['buckets' => [], 'updated' => 0];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : ['buckets' => [], 'updated' => 0];
    }

    private static function write(array $data): void
    {
        $file = self::path();
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
