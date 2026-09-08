<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Cache;
use Wawoo\Core\Posts;

final class PostsLangTest extends TestCase
{
    private string $slug = 'zzz-mlang-posts';

    protected function setUp(): void
    {
        Cache::flush();
        $dir = POSTS_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.md', "---\ntitle: MLang Post\ndate: 2026-05-05\ncategory: Meta\nstatus: published\n---\n\nDefault language body.\n");
        file_put_contents($dir . '/index.ja.md', "---\ntitle: 日本語ポスト\ndate: 2026-05-05\ncategory: Meta\nstatus: published\n---\n\n日本語の本文。\n");
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

    public function testMdFilePrefersSuffixedTranslation(): void
    {
        $this->assertSame(POSTS_DIR . $this->slug . '/index.ja.md', Posts::mdFile($this->slug, 'ja'));
    }

    public function testMdFileFallsBackToDefaultBundleWhenTranslationMissing(): void
    {
        $this->assertSame(POSTS_DIR . $this->slug . '/index.md', Posts::mdFile($this->slug, 'zh'));
    }

    public function testMdFileNeverUsesFlatFileForTranslated(): void
    {
        $flat = POSTS_DIR . 'zzz-mlang-flat.md';
        file_put_contents($flat, "---\ntitle: Flat MLang\nstatus: published\n---\n\nHi\n");
        try {
            $this->assertSame($flat, Posts::mdFile('zzz-mlang-flat'));
            $this->assertNull(Posts::mdFile('zzz-mlang-flat', 'ja'));
        } finally {
            @unlink($flat);
        }
    }

    public function testBySlugServesTranslation(): void
    {
        $p = Posts::bySlug($this->slug, false, 'ja');
        $this->assertNotNull($p);
        $this->assertSame('日本語ポスト', $p['title']);
        $this->assertSame('ja', $p['lang']);
        $this->assertStringContainsString('日本語の本文。', $p['content']);
    }

    public function testBySlugFallsBackToDefaultContentWhenTranslationMissing(): void
    {
        $p = Posts::bySlug($this->slug, false, 'zh');
        $this->assertNotNull($p);
        $this->assertSame('MLang Post', $p['title']);
        $this->assertSame('', $p['lang']);
        $this->assertStringContainsString('Default language body.', $p['content']);
    }

    public function testBySlugWithoutLangIsUnchanged(): void
    {
        $p = Posts::bySlug($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('MLang Post', $p['title']);
        $this->assertSame('', $p['lang']);
        $this->assertSame(POSTS_DIR . $this->slug . '/index.md', Posts::mdFile($this->slug));
    }

    public function testTranslationsDoNotAddListEntries(): void
    {
        $found = array_values(array_filter(Posts::all(), function ($p) {
            return $p['slug'] === $this->slug;
        }));
        $this->assertCount(1, $found);
        $this->assertSame('MLang Post', $found[0]['title']);
    }
}
