<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Frontmatter;

final class FrontmatterTest extends TestCase
{
    public function testNoFrontmatter(): void
    {
        $r = Frontmatter::parse('hello world');
        $this->assertSame([], $r['meta']);
        $this->assertSame('hello world', $r['content']);
    }

    public function testBasicKeyValue(): void
    {
        $r = Frontmatter::parse("---\ntitle: Hello\ndate: 2025-01-01\n---\nBody");
        $this->assertSame('Hello', $r['meta']['title']);
        $this->assertSame('2025-01-01', $r['meta']['date']);
        $this->assertSame('Body', $r['content']);
    }

    public function testBooleans(): void
    {
        $r = Frontmatter::parse("---\npub: true\ndraft: false\n---\nx");
        $this->assertTrue($r['meta']['pub']);
        $this->assertFalse($r['meta']['draft']);
    }

    public function testNumbers(): void
    {
        $r = Frontmatter::parse("---\ni: 42\nf: 3.14\n---\nx");
        $this->assertSame(42, $r['meta']['i']);
        $this->assertSame(3.14, $r['meta']['f']);
    }

    public function testArrays(): void
    {
        $r = Frontmatter::parse("---\ntags: [foo, bar, baz]\n---\nx");
        $this->assertSame(['foo', 'bar', 'baz'], $r['meta']['tags']);
    }

    public function testQuotedStrings(): void
    {
        $r = Frontmatter::parse("---\ntitle: \"Hello: World\"\n---\nx");
        $this->assertSame('Hello: World', $r['meta']['title']);
    }
}
