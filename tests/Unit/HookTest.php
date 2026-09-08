<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Hook;

final class HookTest extends TestCase
{
    protected function setUp(): void
    {
        Hook::reset();
    }

    public function testOnAndFireRunsCallbacks(): void
    {
        $called = 0;
        Hook::on('ping', function () use (&$called) { $called++; });
        Hook::on('ping', function () use (&$called) { $called++; });
        Hook::fire('ping');
        $this->assertSame(2, $called);
    }

    public function testPriorityOrdering(): void
    {
        $order = [];
        Hook::on('p', function () use (&$order) { $order[] = 'low'; }, 20);
        Hook::on('p', function () use (&$order) { $order[] = 'high'; }, 5);
        Hook::on('p', function () use (&$order) { $order[] = 'mid'; }, 10);
        Hook::fire('p');
        $this->assertSame(['high', 'mid', 'low'], $order);
    }

    public function testPayloadPassedByReference(): void
    {
        Hook::on('transform', function (&$v) { $v['x'] = 1; });
        Hook::on('transform', function (&$v) { $v['y'] = 2; }, 5);
        $payload = [];
        Hook::fire('transform', $payload);
        $this->assertSame(['y' => 2, 'x' => 1], $payload);
    }

    public function testFilterReturnsModifiedValue(): void
    {
        Hook::on('wrap', fn($v) => '[' . $v . ']');
        Hook::on('wrap', fn($v) => strtoupper($v));
        $this->assertSame('[HELLO]', Hook::filter('wrap', 'hello'));
    }

    public function testNonExistentHookIsNoop(): void
    {
        $this->expectNotToPerformAssertions();
        Hook::fire('does.not.exist');
        Hook::filter('does.not.exist', 42);
    }

    public function testResetClearsListeners(): void
    {
        Hook::on('a', fn() => 1);
        $this->assertArrayHasKey('a', (new ReflectionClass(Hook::class))->getStaticPropertyValue('listeners'));
        Hook::reset();
        $this->assertSame([], (new ReflectionClass(Hook::class))->getStaticPropertyValue('listeners'));
    }
}
