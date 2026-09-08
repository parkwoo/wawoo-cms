<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\RateLimit;

final class RateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        @unlink(CACHE_DIR . 'settings/ratelimit.json');
        $r = new ReflectionClass(RateLimit::class);
        $m = $r->getProperty('mem');
        $m->setAccessible(true);
        $m->setValue([]);
        $s = $r->getProperty('memSecond');
        $s->setAccessible(true);
        $s->setValue(0);
    }

    protected function tearDown(): void
    {
        @unlink(CACHE_DIR . 'settings/ratelimit.json');
        $r = new ReflectionClass(RateLimit::class);
        $m = $r->getProperty('mem');
        $m->setAccessible(true);
        $m->setValue([]);
        $s = $r->getProperty('memSecond');
        $s->setAccessible(true);
        $s->setValue(0);
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
}
