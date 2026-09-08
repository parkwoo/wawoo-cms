<?php
/**
 * Optional cross-plugin rate limiter for outbound API clients.
 *
 * Off by default; only enforced when a client opts in (e.g. the s3 plugin's
 * `rate_per_second` > 0). Uses a single file opened with an exclusive lock:
 * read → check → increment → write all happen inside the critical section,
 * so concurrent PHP workers cannot both pass the limit in the same second.
 */

namespace Wawoo\Core;

final class RateLimit
{
    private const FILE = 'ratelimit.json';

    /**
     * Allow up to $max calls in the given one-second window (default now).
     * Returns true when the call may proceed. The whole decision happens
     * under an exclusive flock so concurrent processes stay within the cap.
     */
    public static function allow(string $bucket, int $max, ?int $now = null): bool
    {
        $max = max(1, $max);
        $now = $now ?? time();

        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . self::FILE;
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return true; // cannot persist → do not hard-fail the request
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return true;
        }

        // Read current state.
        rewind($fp);
        $raw = stream_get_contents($fp);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            $data = ['buckets' => []];
        }
        $buckets = $data['buckets'] ?? [];
        $second = (string)$now;
        $bucketCounters = $buckets[$bucket] ?? [];
        // Keep only this second's entry (prune stale ones).
        $hits = isset($bucketCounters[$second]) ? (int)$bucketCounters[$second] : 0;

        if ($hits >= $max) {
            // Leave the file as-is; release and deny.
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        // Increment and write atomically inside the lock.
        $bucketCounters = [$second => $hits + 1];
        $buckets[$bucket] = $bucketCounters;
        $data['buckets'] = $buckets;
        $data['updated'] = time();

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
}
