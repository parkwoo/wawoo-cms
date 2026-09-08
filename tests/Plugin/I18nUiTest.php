<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\I18n\Config;
use Wawoo\Plugin\I18n\Settings;

final class I18nUiTest extends TestCase
{
    private string $settingsFile;

    protected function setUp(): void
    {
        $this->settingsFile = rtrim(CACHE_DIR, '/\\') . '/settings/i18n.json';
        $dir = dirname($this->settingsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        foreach (glob($this->settingsFile . '*') as $f) {
            @unlink($f);
        }
        unset($GLOBALS['WAWOO_I18N'], $GLOBALS['__WAWOO_LANG'], $GLOBALS['__WAWOO_T']);
        $this->resetConfigCache();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->settingsFile . '*') as $f) {
            @unlink($f);
        }
        unset($GLOBALS['WAWOO_I18N'], $GLOBALS['__WAWOO_LANG'], $GLOBALS['__WAWOO_T']);
        $this->resetConfigCache();
    }

    private function resetConfigCache(): void
    {
        $r = new ReflectionClass(Config::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private function writeSettings(array $data): void
    {
        file_put_contents($this->settingsFile, json_encode($data));
    }

    private function runInSubprocess(string $code): string
    {
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $file = tempnam(sys_get_temp_dir(), 'i18nui') . '.php';
        file_put_contents($file, $code);
        try {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg($bootstrap);
            return (string)shell_exec($cmd . ' 2>&1');
        } finally {
            @unlink($file);
        }
    }

    public function testThemeRendersJapaneseWhenCookieSelectsJa(): void
    {
        $out = $this->runInSubprocess(
            "<?php\nrequire \$argv[1];\n"
            . "\\Wawoo\\Core\\Theme::activate('default', THEMES_DIR . 'default');\n"
            . "\$_COOKIE['wawoo_lang'] = 'ja';\n"
            . "\\Wawoo\\Plugin\\I18n\\Loader::init();\n"
            . "\$vars = [];\n"
            . "extract(\$vars, EXTR_SKIP);\n"
            . "ob_start();\n"
            . "include THEMES_DIR . '/default/templates/404.php';\n"
            . "\$o = ob_get_clean();\n"
            . "fwrite(STDOUT, \$o);\n"
        );
        $this->assertStringContainsString('ページが見つかりません', $out);
        $this->assertStringContainsString('お探しのページは存在しません。', $out);
        $this->assertStringNotContainsString('Not found', $out);
    }

    public function testClayThemeRendersChineseWhenUrlParamSelectsZh(): void
    {
        $out = $this->runInSubprocess(
            "<?php\nrequire \$argv[1];\n"
            . "\\Wawoo\\Core\\Theme::activate('clay', THEMES_DIR . 'clay');\n"
            . "\$_GET['lang'] = 'zh';\n"
            . "\\Wawoo\\Plugin\\I18n\\Loader::init();\n"
            . "\$vars = [];\n"
            . "extract(\$vars, EXTR_SKIP);\n"
            . "ob_start();\n"
            . "include THEMES_DIR . '/clay/templates/404.php';\n"
            . "\$o = ob_get_clean();\n"
            . "fwrite(STDOUT, \$o);\n"
        );
        $this->assertStringContainsString('页面未找到', $out);
        $this->assertStringContainsString('您请求的页面不存在。', $out);
        $this->assertStringNotContainsString('Not found', $out);
    }

    public function testTemplateFallbackReturnsEnglishDefaultWithoutPlugin(): void
    {
        $out = $this->runInSubprocess(
            "<?php\nrequire \$argv[1];\n"
            . "\\Wawoo\\Core\\Theme::activate('default', THEMES_DIR . 'default');\n"
            . "\$vars = [];\n"
            . "extract(\$vars, EXTR_SKIP);\n"
            . "ob_start();\n"
            . "include THEMES_DIR . '/default/templates/404.php';\n"
            . "\$o = ob_get_clean();\n"
            . "fwrite(STDOUT, \$o);\n"
        );
        $this->assertStringContainsString('404 — Not found', $out);
        $this->assertStringContainsString('The page you requested does not exist.', $out);
        $this->assertStringContainsString('Back to home', $out);
    }

    public function testConfigDefaultsFromManifest(): void
    {
        $cfg = Config::all();
        $this->assertSame('en', $cfg['default']);
        $this->assertSame(['en', 'ja', 'zh'], $cfg['supported']);
    }

    public function testRuntimeSettingsFileOverridesGlobals(): void
    {
        $this->writeSettings(['default' => 'ja', 'supported' => ['ja']]);
        $GLOBALS['WAWOO_I18N'] = ['default' => 'zh', 'supported' => ['en', 'zh']];
        $this->resetConfigCache();

        $cfg = Config::all();
        $this->assertSame('ja', $cfg['default']);
        $this->assertSame(['ja'], $cfg['supported']);
    }

    public function testGlobalsWinWithoutRuntimeFile(): void
    {
        $GLOBALS['WAWOO_I18N'] = ['default' => 'zh'];
        $this->resetConfigCache();

        $cfg = Config::all();
        $this->assertSame('zh', $cfg['default']);
        $this->assertSame(['en', 'ja', 'zh'], $cfg['supported']);
    }

    public function testSaveFromPostPersistsAndConfigPicksItUp(): void
    {
        $result = Settings::saveFromPost([
            'default'         => 'ja',
            'supported'       => ['en', 'ja'],
            'detect_browser'  => '1',
        ]);

        $this->assertSame('ja', $result['default']);
        $this->assertSame(['en', 'ja'], $result['supported']);
        $this->assertTrue($result['detect_browser']);
        $this->assertFileExists($this->settingsFile);

        $saved = json_decode((string)file_get_contents($this->settingsFile), true);
        $this->assertSame('ja', $saved['default']);
        $this->assertSame(['en', 'ja'], $saved['supported']);
        $this->assertTrue($saved['detect_browser']);

        $this->resetConfigCache();
        $cfg = Config::all();
        $this->assertSame('ja', $cfg['default']);
        $this->assertSame(['en', 'ja'], $cfg['supported']);
        $this->assertTrue($cfg['detect_browser']);
    }

    public function testSaveFromPostFallsBackToSupportedDefault(): void
    {
        $result = Settings::saveFromPost([
            'default'         => 'en',
            'supported'       => ['ja'],
            'detect_browser'  => '',
        ]);
        $this->assertSame('ja', $result['default']);
        $this->assertFalse($result['detect_browser']);
    }

    public function testLanguageFilesShareTheSameKeys(): void
    {
        $dir = dirname(__DIR__, 2) . '/plugins/i18n/lang';
        $en = require $dir . '/en.php';
        $ja = require $dir . '/ja.php';
        $zh = require $dir . '/zh.php';

        $this->assertSame(
            array_keys($en),
            array_keys($ja),
            'ja.php must define the same keys as en.php'
        );
        $this->assertSame(
            array_keys($en),
            array_keys($zh),
            'zh.php must define the same keys as en.php'
        );

        $required = [
            'nav.home', 'nav.search', 'nav.rss', 'nav.admin', 'nav.logout', 'nav.back_home',
            'common.no_posts', 'common.no_results', 'common.search', 'common.submit',
            'common.loading', 'common.tags', 'common.categories', 'common.by', 'common.author',
            'common.prev', 'common.next', 'common.page',
            'e404.title', 'e404.body', 'e404.home',
            'search.title', 'search.placeholder', 'search.results', 'search.empty',
            'admin.dashboard', 'admin.plugins', 'admin.themes', 'admin.enable', 'admin.disable',
            'admin.settings', 'admin.toggle', 'admin.delete', 'admin.active', 'admin.title',
            'admin.posts', 'admin.categories', 'admin.tags', 'admin.no_posts', 'admin.login_title',
            'admin.password', 'admin.sign_in', 'admin.disabled', 'admin.disabled_hint',
            'admin.confirm_delete', 'admin.name', 'admin.version', 'admin.description',
            'admin.status', 'admin.actions', 'admin.date', 'admin.logout',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $en, "en.php missing $key");
            $this->assertArrayHasKey($key, $ja, "ja.php missing $key");
            $this->assertArrayHasKey($key, $zh, "zh.php missing $key");
        }
    }
}
