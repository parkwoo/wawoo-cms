<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Cache;

final class CacheTest extends TestCase
{
    public function testSetAndGet(): void
    {
        Cache::set('k', ['a' => 1]);
        $this->assertSame(['a' => 1], Cache::get('k'));
    }

    public function testForget(): void
    {
        Cache::set('k2', 'v');
        $this->assertSame('v', Cache::get('k2'));
        Cache::forget('k2');
        $this->assertNull(Cache::get('k2'));
    }

    public function testFlush(): void
    {
        Cache::set('a', 1);
        Cache::set('b', 2);
        Cache::flush();
        $this->assertNull(Cache::get('a'));
        $this->assertNull(Cache::get('b'));
    }
}
