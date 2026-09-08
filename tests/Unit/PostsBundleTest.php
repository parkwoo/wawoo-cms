<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Cache;
use Wawoo\Core\Posts;

final class PostsBundleTest extends TestCase
{
    private string $slug = 'zzz-bundle-test';

    protected function setUp(): void
    {
        Cache::flush();
        $dir = POSTS_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.md', "---\ntitle: Bundle Post\ndate: 2026-05-05\ncategory: Meta\nstatus: published\ncover: cover.png\n---\n\nHello ![fig](pic.jpg \"Pic\") world.\n");
        file_put_contents($dir . '/pic.jpg', 'img');
        file_put_contents($dir . '/cover.png', 'img');
    }

    protected function tearDown(): void
    {
        Cache::flush();
        $dir = POSTS_DIR . $this->slug;
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

    public function testBundlePostIsDiscoveredAndParsed(): void
    {
        $all = Posts::all();
        $this->assertNotEmpty($all);
        $p = Posts::bySlug($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('Bundle Post', $p['title']);
        $this->assertSame(POSTS_DIR . $this->slug . '/index.md', Posts::mdFile($this->slug));
    }

    public function testRelativeCoverResolvedToBundleBase(): void
    {
        $p = Posts::bySlug($this->slug);
        $this->assertSame('/posts/' . $this->slug . '/cover.png', $p['cover']);
    }

    public function testRelativeImageResolvedInHtml(): void
    {
        $p = Posts::bySlug($this->slug);
        $this->assertStringContainsString('src="/posts/' . $this->slug . '/pic.jpg"', $p['html']);
        $this->assertStringNotContainsString('src="pic.jpg"', $p['html']);
    }

    public function testDeleteRemovesWholeBundleDirectory(): void
    {
        $this->assertTrue(Posts::deleteBySlug($this->slug));
        $this->assertFalse(is_dir(POSTS_DIR . $this->slug));
        $this->assertNull(Posts::bySlug($this->slug));
    }

    public function testDeleteRefusesDirectoryWithoutIndexMd(): void
    {
        $dir = POSTS_DIR . 'not-a-post-dir';
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/keep.txt', 'x');
        try {
            $this->assertFalse(Posts::deleteBySlug('not-a-post-dir'));
            $this->assertTrue(is_dir($dir));
        } finally {
            $this->rm($dir);
        }
    }
}
