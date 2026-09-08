<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Cache;
use Wawoo\Core\Posts;

final class PostsStatusTest extends TestCase
{
    private string $slug = 'zzz-status-test';

    protected function setUp(): void
    {
        Cache::flush();
        $dir = POSTS_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.md', "---\ntitle: Status Test\ndate: 2026-01-01\ncategory: Meta\nstatus: draft\n---\n\nbody\n");
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

    public function testDraftIsNotListed(): void
    {
        $this->assertNull(Posts::bySlug($this->slug));
        $this->assertNotNull(Posts::bySlug($this->slug, true));
    }

    public function testSetPublished(): void
    {
        $this->assertSame('published', Posts::setStatus($this->slug, 'published'));
        $p = Posts::bySlug($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('published', $p['status']);
    }

    public function testSetScheduledFutureNotListed(): void
    {
        $this->assertSame('scheduled', Posts::setStatus($this->slug, 'scheduled', '2099-01-01 09:00'));
        $this->assertNull(Posts::bySlug($this->slug)); // not public yet
        $raw = (string)file_get_contents(POSTS_DIR . $this->slug . '/index.md');
        $this->assertStringContainsString('status: scheduled', $raw);
        $this->assertStringContainsString('publish_at:', $raw);
    }

    public function testDuePromotesOverdueScheduled(): void
    {
        $this->assertSame('scheduled', Posts::setStatus($this->slug, 'scheduled', '2020-01-01 09:00'));
        $due = Posts::due();
        $this->assertContains($this->slug, $due);
        $p = Posts::bySlug($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('published', $p['status']);
        $raw = (string)file_get_contents(POSTS_DIR . $this->slug . '/index.md');
        $this->assertStringNotContainsString('publish_at:', $raw);
    }

    public function testDueIgnoresFutureScheduled(): void
    {
        $this->assertSame('scheduled', Posts::setStatus($this->slug, 'scheduled', '2099-01-01 09:00'));
        $this->assertSame([], Posts::due());
        $this->assertNull(Posts::bySlug($this->slug));
    }

    public function testInvalidStatusRejected(): void
    {
        $this->assertNull(Posts::setStatus($this->slug, 'bogus'));
        $this->assertNull(Posts::setStatus($this->slug, 'scheduled', ''));
    }

    public function testFrontmatterOrderPreserved(): void
    {
        $this->assertSame('published', Posts::setStatus($this->slug, 'published'));
        $raw = (string)file_get_contents(POSTS_DIR . $this->slug . '/index.md');
        $this->assertStringContainsString("title: Status Test", $raw);
        $this->assertMatchesRegularExpression('/^---\s*\n/m', $raw);
    }
}
