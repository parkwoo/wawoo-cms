<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Pages;

final class PagesLangTest extends TestCase
{
    private string $slug = 'zzz-mlang-pages';

    protected function setUp(): void
    {
        $dir = PAGES_DIR . $this->slug;
        if (is_dir($dir)) {
            $this->rm($dir);
        }
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.md', "---\ntitle: MLang Page\nexcerpt: A page\norder: 5\n---\n\nDefault language body.\n");
        file_put_contents($dir . '/index.ja.md', "---\ntitle: 日本語ページ\nexcerpt: ページ\norder: 5\n---\n\n日本語の本文。\n");
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

    public function testMdFilePrefersSuffixedTranslation(): void
    {
        $this->assertSame(PAGES_DIR . $this->slug . '/index.ja.md', Pages::mdFile($this->slug, 'ja'));
    }

    public function testMdFileFallsBackToDefaultBundleWhenTranslationMissing(): void
    {
        $this->assertSame(PAGES_DIR . $this->slug . '/index.md', Pages::mdFile($this->slug, 'zh'));
    }

    public function testMdFileNeverUsesFlatFileForTranslated(): void
    {
        $flat = PAGES_DIR . 'zzz-mlang-flat-page.md';
        file_put_contents($flat, "---\ntitle: Flat MLang Page\n---\n\nHi\n");
        try {
            $this->assertSame($flat, Pages::mdFile('zzz-mlang-flat-page'));
            $this->assertNull(Pages::mdFile('zzz-mlang-flat-page', 'ja'));
        } finally {
            @unlink($flat);
        }
    }

    public function testGetServesTranslation(): void
    {
        $p = Pages::get($this->slug, 'ja');
        $this->assertNotNull($p);
        $this->assertSame('日本語ページ', $p['title']);
        $this->assertSame('ja', $p['lang']);
        $this->assertStringContainsString('日本語の本文。', $p['content']);
    }

    public function testGetFallsBackToDefaultContentWhenTranslationMissing(): void
    {
        $p = Pages::get($this->slug, 'zh');
        $this->assertNotNull($p);
        $this->assertSame('MLang Page', $p['title']);
        $this->assertSame('', $p['lang']);
        $this->assertStringContainsString('Default language body.', $p['content']);
    }

    public function testGetWithoutLangIsUnchanged(): void
    {
        $p = Pages::get($this->slug);
        $this->assertNotNull($p);
        $this->assertSame('MLang Page', $p['title']);
        $this->assertSame('', $p['lang']);
        $this->assertSame(PAGES_DIR . $this->slug . '/index.md', Pages::mdFile($this->slug));
    }
}
