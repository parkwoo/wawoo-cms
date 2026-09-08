<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\I18n\Config;
use Wawoo\Plugin\I18n\Loader;
use Wawoo\Plugin\I18n\Switcher;

final class I18nSwitcherTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['WAWOO_I18N'], $GLOBALS['__WAWOO_LANG'], $GLOBALS['__WAWOO_T']);
        $_GET = [];
        $_COOKIE = [];
        self::resetConfig();
        self::resetLoader();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['WAWOO_I18N'], $GLOBALS['__WAWOO_LANG'], $GLOBALS['__WAWOO_T']);
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        self::resetConfig();
        self::resetLoader();
    }

    private static function resetConfig(): void
    {
        $p = (new ReflectionClass(Config::class))->getProperty('cache');
        $p->setAccessible(true);
        $p->setValue(null, null);
    }

    private static function resetLoader(): void
    {
        $rc = new ReflectionClass(Loader::class);
        $t = $rc->getProperty('translations');
        $t->setAccessible(true);
        $t->setValue(null, []);
        $c = $rc->getProperty('current');
        $c->setAccessible(true);
        $c->setValue(null, null);
    }

    public function testConfigDefaultSupportedIncludesChinese(): void
    {
        $cfg = Config::all();
        $this->assertSame(['en', 'ja', 'zh'], $cfg['supported']);
        $this->assertContains('zh', $cfg['supported']);
        $this->assertSame('en', $cfg['default']);
        $this->assertSame('wawoo_lang', $cfg['cookie_name']);

        // plugin.json config block is applied verbatim.
        $raw = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/plugins/i18n/plugin.json'), true);
        $this->assertSame($raw['config']['supported'], $cfg['supported']);
        $this->assertSame($raw['config']['default'], $cfg['default']);
    }

    public function testConfigGlobalOverrideWins(): void
    {
        $GLOBALS['WAWOO_I18N'] = ['supported' => ['en'], 'default' => 'ja'];
        self::resetConfig();
        $cfg = Config::all();
        $this->assertSame(['en'], $cfg['supported']);
        $this->assertSame('ja', $cfg['default']);
    }

    public function testGlobalTIsDefinedAndMatchesLoaderT(): void
    {
        Loader::setLanguage('en');
        $this->assertTrue(function_exists('t'));
        $this->assertSame('Home', Loader::t('nav.home'));
        $this->assertSame(Loader::t('nav.home'), t('nav.home'));
        $this->assertSame(Loader::t('not.exists'), t('not.exists'));
        $this->assertSame('Home', \Wawoo\Plugin\I18n\t('nav.home'));
    }

    public function testSwitcherRendersFormForMultipleLanguages(): void
    {
        Loader::setLanguage('en');
        $_SERVER['REQUEST_URI'] = '/search?cat=tech&lang=en';
        $_GET = ['cat' => 'tech', 'lang' => 'en', 'x' => ['a', 'b']];

        ob_start();
        (new Switcher())->handle();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('<form method="get" action="/search"', $html);
        $this->assertStringContainsString('<select name="lang"', $html);
        $this->assertStringContainsString('<option value="en" selected>EN</option>', $html);
        $this->assertStringContainsString('<option value="zh">ZH</option>', $html);
        // Scalar query params preserved as hidden fields.
        $this->assertStringContainsString('name="cat" value="tech"', $html);
        // lang is only the select name, never a hidden input.
        $this->assertStringNotContainsString('<input type="hidden" name="lang"', $html);
        // Non-scalar $_GET values are skipped, not cast to strings.
        $this->assertStringNotContainsString('name="x"', $html);
    }

    public function testSwitcherEmptyForSingleLanguage(): void
    {
        $GLOBALS['WAWOO_I18N'] = ['supported' => ['en']];
        self::resetConfig();
        Loader::setLanguage('en');

        ob_start();
        (new Switcher())->handle();
        $html = (string)ob_get_clean();

        $this->assertSame('', $html);
    }

    public function testSetCookieGuardedForEmptyCookieName(): void
    {
        $before = headers_list();
        $m = (new ReflectionClass(Loader::class))->getMethod('setCookie');
        $m->setAccessible(true);
        $m->invoke(null, ['cookie_name' => ''], 'ja');
        $this->assertSame($before, headers_list());
    }

    public function testSetCookieSkippedWhenLanguageUnchanged(): void
    {
        $_COOKIE['wawoo_lang'] = 'en';
        $before = headers_list();
        $m = (new ReflectionClass(Loader::class))->getMethod('setCookie');
        $m->setAccessible(true);
        $m->invoke(null, ['cookie_name' => 'wawoo_lang'], 'en');
        $this->assertSame($before, headers_list());
    }
}
