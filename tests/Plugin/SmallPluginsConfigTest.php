<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\SitemapHtml\Config as SitemapConfig;
use Wawoo\Plugin\TagCloud\Config as TagCloudConfig;

final class SmallPluginsConfigTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([SitemapConfig::class, TagCloudConfig::class] as $class) {
            $r = new ReflectionClass($class);
            if (!$r->hasProperty('cache')) {
                continue;
            }
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['WAWOO_SITEMAP_HTML'], $GLOBALS['WAWOO_TAG_CLOUD']);
    }

    public function testSitemapDefaultsComeFromPluginJson(): void
    {
        $cfg = SitemapConfig::all();
        $this->assertSame(50, $cfg['per_page']);
        $this->assertSame('Sitemap', $cfg['title']);
    }

    public function testSitemapGlobalsOverridePluginJson(): void
    {
        $GLOBALS['WAWOO_SITEMAP_HTML'] = ['per_page' => 25, 'title' => 'Index'];
        $cfg = SitemapConfig::all();
        $this->assertSame(25, $cfg['per_page']);
        $this->assertSame('Index', $cfg['title']);
        $this->assertSame(25, SitemapConfig::perPage());
    }

    public function testSitemapPerPageClampsZero(): void
    {
        $GLOBALS['WAWOO_SITEMAP_HTML'] = ['per_page' => 0];
        $this->assertSame(1, SitemapConfig::perPage());
    }

    public function testSitemapPerPageClampsNegative(): void
    {
        $GLOBALS['WAWOO_SITEMAP_HTML'] = ['per_page' => -10];
        $this->assertSame(1, SitemapConfig::perPage());
    }

    public function testSitemapPerPageKeepsSaneValue(): void
    {
        $GLOBALS['WAWOO_SITEMAP_HTML'] = ['per_page' => 7];
        $this->assertSame(7, SitemapConfig::perPage());
    }

    public function testTagCloudDefaultsComeFromPluginJson(): void
    {
        $cfg = TagCloudConfig::all();
        $this->assertSame(12, $cfg['min_font_px']);
        $this->assertSame(28, $cfg['max_font_px']);
        $this->assertSame('Tags', $cfg['title']);
    }

    public function testTagCloudGlobalsOverridePluginJson(): void
    {
        $GLOBALS['WAWOO_TAG_CLOUD'] = ['min_font_px' => 18];
        $cfg = TagCloudConfig::all();
        $this->assertSame(18, $cfg['min_font_px']);
        $this->assertSame(28, $cfg['max_font_px']);
        $this->assertSame('Tags', $cfg['title']);
    }

    public function testTagCloudSwapsInvertedFontRange(): void
    {
        $GLOBALS['WAWOO_TAG_CLOUD'] = ['min_font_px' => 40, 'max_font_px' => 12];
        $cfg = TagCloudConfig::all();
        $this->assertSame(12, $cfg['min_font_px']);
        $this->assertSame(40, $cfg['max_font_px']);
    }
}
