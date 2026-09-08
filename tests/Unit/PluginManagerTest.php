<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\PluginManager;

final class PluginManagerTest extends TestCase
{
    protected function setUp(): void
    {
        @unlink(CACHE_DIR . 'plugins.json');
        @unlink(CACHE_DIR . 'themes.json');
    }

    protected function tearDown(): void
    {
        @unlink(CACHE_DIR . 'plugins.json');
        @unlink(CACHE_DIR . 'themes.json');
    }

    public function testDiscoverPluginsFindsShippedPlugins(): void
    {
        $root = dirname(__DIR__, 2);
        $plugins = PluginManager::discoverPlugins($root);
        $names = array_column($plugins, 'name');
        foreach (['seo', 'rss', 'search', 'menu', 'contact', 'admin'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function testDiscoverThemesFindsShippedThemes(): void
    {
        $root = dirname(__DIR__, 2);
        $themes = PluginManager::discoverThemes($root);
        $names = array_column($themes, 'name');
        $this->assertContains('default', $names);
    }

    public function testEnabledPluginsPersistAcrossInstances(): void
    {
        PluginManager::setEnabledPlugins(['seo', 'comments']);
        $this->assertSame(['seo', 'comments'], PluginManager::enabledPlugins());
    }

    public function testEnabledPluginsFallsBackToConfigConstant(): void
    {
        @unlink(CACHE_DIR . 'plugins.json');
        $this->assertSame(ENABLED_PLUGINS, PluginManager::enabledPlugins());
    }

    public function testActiveThemePersists(): void
    {
        PluginManager::setActiveTheme('clay');
        $this->assertSame('clay', PluginManager::activeTheme());
    }

    public function testActiveThemeFallsBackToConfigConstant(): void
    {
        @unlink(CACHE_DIR . 'themes.json');
        $this->assertSame(ENABLED_THEMES[0], PluginManager::activeTheme());
    }
}
