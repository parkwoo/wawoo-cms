<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\I18n\Loader;

final class I18nLoaderTest extends TestCase
{
    public function testLoadEnglishDefaults(): void
    {
        $t = Loader::load('en');
        $this->assertSame('Home', $t['nav.home']);
        $this->assertSame('Search', $t['nav.search']);
    }

    public function testLoadJapanese(): void
    {
        $t = Loader::load('ja');
        $this->assertSame('ホーム', $t['nav.home']);
        $this->assertSame('検索', $t['nav.search']);
    }

    public function testLoadChinese(): void
    {
        $t = Loader::load('zh');
        $this->assertSame('首页', $t['nav.home']);
    }

    public function testFallsBackToEnglishForUnknown(): void
    {
        $t = Loader::load('xx');
        $this->assertSame('Home', $t['nav.home']);
    }

    public function testTFunction(): void
    {
        Loader::setLanguage('en');
        $this->assertSame('Home', \Wawoo\Plugin\I18n\t('nav.home'));
        $this->assertSame('Default value', \Wawoo\Plugin\I18n\t('not.exists', 'Default value'));
        $this->assertSame('not.exists', \Wawoo\Plugin\I18n\t('not.exists'));
    }
}
