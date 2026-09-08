<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Menu\Config;
use Wawoo\Plugin\Menu\Items;
use Wawoo\Plugin\Menu\Settings;

final class MenuPluginTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['WAWOO_MENU']);
        $this->resetConfig();
        $this->cleanSettings();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['WAWOO_MENU']);
        $this->resetConfig();
        $this->cleanSettings();
    }

    private function resetConfig(): void
    {
        $r = new ReflectionProperty(Config::class, 'cache');
        $r->setAccessible(true);
        $r->setValue(null, null);
    }

    private function cleanSettings(): void
    {
        @unlink(CACHE_DIR . 'settings/menu.json');
        @unlink(CACHE_DIR . 'settings/menu.json.*');
    }

    public function testEmptyMenuReturnsNullSoThemeDefaultShows(): void
    {
        ob_start();
        $res = (new Items())->handle(null, ['stats' => []]);
        ob_end_clean();
        $this->assertNull($res);
    }

    public function testSaveAndRenderHomePageCustom(): void
    {
        // Save home + about page + a custom link via the form shape.
        Settings::saveFromPost([
            'rows'          => ['home', 'page_about', 'custom_0'],
            'order'         => ['home' => 1, 'page_about' => 2, 'custom_0' => 3],
            'custom_label'  => ['0' => 'Docs'],
            'custom_url'    => ['0' => 'https://example.com/docs'],
        ]);
        $this->assertFileExists(CACHE_DIR . 'settings/menu.json');

        $out = (new Items())->handle(null, ['stats' => ['categories' => ['Meta']]]);
        $this->assertNotNull($out);
        $this->assertStringContainsString('href="' . BASE_URL . '"', $out);       // home
        $this->assertStringContainsString('href="' . BASE_URL . 'about"', $out);  // page /about
        $this->assertStringContainsString('href="https://example.com/docs"', $out);
        $this->assertStringContainsString('>Docs</a>', $out);
        $this->assertStringContainsString('>About</a>', $out);                    // page title label
    }

    public function testCategoryItemRendered(): void
    {
        Settings::saveFromPost([
            'rows'  => ['cat_' . base64_encode('Tech')],
            'order' => ['cat_' . base64_encode('Tech') => 1],
        ]);
        $out = (new Items())->handle(null, []);
        $this->assertStringContainsString('href="' . BASE_URL . '?cat=tech"', $out);
        $this->assertStringContainsString('>Tech</a>', $out);
    }

    public function testIncompleteCustomRowIsSkipped(): void
    {
        Settings::saveFromPost([
            'rows'         => ['home', 'custom_0'],
            'order'        => ['home' => 1, 'custom_0' => 2],
            'custom_label' => ['0' => ''],
            'custom_url'   => ['0' => '/x'],
        ]);
        $out = (new Items())->handle(null, []);
        $this->assertStringNotContainsString('/x', (string)$out);
    }

    public function testConfigDefaults(): void
    {
        $cfg = Config::all();
        $this->assertSame('Home', $cfg['home_label']);
        $this->assertSame([], $cfg['items']);
    }

    public function testConfigHonorsGlobals(): void
    {
        $GLOBALS['WAWOO_MENU'] = ['home_label' => 'Top'];
        $this->assertSame('Top', Config::all()['home_label']);
    }
}
