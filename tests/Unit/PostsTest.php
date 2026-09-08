<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Posts;

final class PostsTest extends TestCase
{
    public function testAllReturnsAllShippedPosts(): void
    {
        $all = Posts::all();
        $this->assertNotEmpty($all);
        $this->assertSame('welcome', $all[0]['slug']);
        $this->assertSame('Welcome to wawoo-cms', $all[0]['title']);
    }

    public function testBySlugReturnsParsedPost(): void
    {
        $p = Posts::bySlug('welcome');
        $this->assertNotNull($p);
        $this->assertSame('welcome', $p['slug']);
        $this->assertSame('Meta', $p['category']);
        $this->assertArrayHasKey('html', $p);
        $this->assertStringContainsString('<p>', $p['html']);
    }

    public function testBySlugReturnsNullForUnknown(): void
    {
        $this->assertNull(Posts::bySlug('does-not-exist'));
    }

    public function testByCategory(): void
    {
        $posts = Posts::byCategory('Meta');
        $this->assertCount(1, $posts);
        $this->assertSame('welcome', $posts[0]['slug']);
    }

    public function testByTag(): void
    {
        $posts = Posts::byTag('wawoo');
        $this->assertCount(1, $posts);
        $this->assertSame('welcome', $posts[0]['slug']);
    }

    public function testSearch(): void
    {
        $this->assertCount(1, Posts::search('wawoo'));
        $this->assertCount(0, Posts::search('nothing-here'));
        $this->assertCount(0, Posts::search(''));
    }

    public function testCategoriesAndTags(): void
    {
        $this->assertContains('Meta', Posts::categories());
        $this->assertContains('intro', Posts::tags());
    }

    public function testStats(): void
    {
        $s = Posts::stats();
        $this->assertGreaterThanOrEqual(1, $s['post_count']);
        $this->assertGreaterThanOrEqual(1, $s['category_count']);
        $this->assertGreaterThanOrEqual(2, $s['tag_count']);
    }
}
