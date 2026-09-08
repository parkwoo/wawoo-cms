<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Pages;

final class PagesTest extends TestCase
{
    private string $slug = 'zzz-pages-test';

    protected function setUp(): void
    {
        $dir = PAGES_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.md', "---\ntitle: Test Page\nexcerpt: A page\norder: 5\ncover: cover.png\n---\n\nHello ![img](pic.png \"Pic\") world.\n");
        file_put_contents($dir . '/pic.png', 'x');
        file_put_contents($dir . '/cover.png', 'x');
    }

    protected function tearDown(): void
    {
        $dir = PAGES_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
    }

    private function rm(string $d): void
    {
        foreach (scandir($d) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $d . '/' . $e;
            if (is_dir($p) && !is_link($p)) {
                $this->rm($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($d);
    }

    public function testGetParsesPage(): void
    {
        $p = Pages::get($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('Test Page', $p['title']);
        $this->assertSame('A page', $p['excerpt']);
        $this->assertSame(PAGES_DIR . $this->slug . '/index.md', Pages::mdFile($this->slug));
    }

    public function testRelativeCoverResolvedToBundleBase(): void
    {
        $p = Pages::get($this->slug);
        $this->assertSame('/pages/' . $this->slug . '/cover.png', $p['cover']);
    }

    public function testRelativeImageResolvedInHtml(): void
    {
        $p = Pages::get($this->slug);
        $this->assertStringContainsString('src="/pages/' . $this->slug . '/pic.png"', $p['html']);
    }

    public function testMissingPageReturnsNull(): void
    {
        $this->assertNull(Pages::get('no-such-page'));
    }

    public function testAllListsAndOrders(): void
    {
        $all = Pages::all();
        $this->assertNotEmpty($all);
        $this->assertSame('Test Page', $all[0]['title']); // order 5 sorts first
    }

    public function testFlatMdSupported(): void
    {
        file_put_contents(PAGES_DIR . 'flatpage.md', "---\ntitle: Flat Page\n---\n\nHi\n");
        try {
            $p = Pages::get('flatpage');
            $this->assertNotNull($p);
            $this->assertSame('Flat Page', $p['title']);
            $this->assertSame(PAGES_DIR . 'flatpage.md', Pages::mdFile('flatpage'));
        } finally {
            @unlink(PAGES_DIR . 'flatpage.md');
        }
    }
}
