<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\RateLimit;

final class RateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        @unlink(CACHE_DIR . 'settings/ratelimit.json');
    }

    protected function tearDown(): void
    {
        @unlink(CACHE_DIR . 'settings/ratelimit.json');
    }

    public function testAllowsUpToMaxPerSecond(): void
    {
        $this->assertTrue(RateLimit::allow('api', 3, 1000));
        $this->assertTrue(RateLimit::allow('api', 3, 1000));
        $this->assertTrue(RateLimit::allow('api', 3, 1000));
        $this->assertFalse(RateLimit::allow('api', 3, 1000)); // 4th in same second
    }

    public function testWindowResetsNextSecond(): void
    {
        $this->assertTrue(RateLimit::allow('win', 1, 2000));
        $this->assertFalse(RateLimit::allow('win', 1, 2000));
        $this->assertTrue(RateLimit::allow('win', 1, 2001)); // new second
    }

    public function testBucketsAreIndependent(): void
    {
        $this->assertTrue(RateLimit::allow('a', 1, 3000));
        $this->assertFalse(RateLimit::allow('a', 1, 3000));
        $this->assertTrue(RateLimit::allow('b', 1, 3000));
    }

    public function testConcurrentWorkersStayWithinLimit(): void
    {
        if (PHP_SAPI !== 'cli' || !function_exists('shell_exec')) {
            $this->markTestSkipped('Concurrent test needs CLI + shell_exec.');
        }
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $outDir = sys_get_temp_dir() . '/rlw_' . bin2hex(random_bytes(3));
        mkdir($outDir, 0755, true);
        $worker = $outDir . '/worker.php';
        file_put_contents($worker, "<?php\nrequire \$argv[1];\n"
            . "file_put_contents(\$argv[2] . '/' . getmypid() . '.out', "
            . "\\Wawoo\\Core\\RateLimit::allow('conc', 2, 9000) ? 'Y' : 'N');\n");
        try {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker)
                 . ' ' . escapeshellarg($bootstrap) . ' ' . escapeshellarg($outDir) . ' > /dev/null 2>&1 &';
            for ($i = 0; $i < 8; $i++) {
                shell_exec($cmd);
            }
            // Wait until all 8 workers have written their result.
            $deadline = microtime(true) + 5.0;
            while (microtime(true) < $deadline) {
                if (count(glob($outDir . '/*.out')) >= 8) {
                    break;
                }
                usleep(30000);
            }
            $allows = 0;
            foreach (glob($outDir . '/*.out') as $f) {
                if (trim((string)file_get_contents($f)) === 'Y') {
                    $allows++;
                }
                @unlink($f);
            }
            // 8 concurrent workers, cap 2 per second → at most 2 allowed.
            $this->assertLessThanOrEqual(2, $allows, "concurrent workers exceeded cap: {$allows} allowed");
            $this->assertGreaterThanOrEqual(1, $allows, 'expected at least one worker to pass');
        } finally {
            foreach (glob($outDir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($outDir);
        }
    }
}
