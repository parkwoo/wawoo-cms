<?php
/**
 * Bootstrap file loaded by every entry point (index.php, post.php, etc.).
 * Reads config, registers autoloader, discovers themes & plugins,
 * boots them in dependency order.
 */

namespace Wawoo\Core;

final class Bootstrap
{
    public static function init(string $root): void
    {
        if (!defined('POSTS_DIR'))  define('POSTS_DIR',   $root . '/posts/');
        if (!defined('PAGES_DIR'))  define('PAGES_DIR',   $root . '/pages/');
        if (!defined('CACHE_DIR'))  define('CACHE_DIR',   $root . '/cache/');
        if (!defined('PLUGINS_DIR')) define('PLUGINS_DIR', $root . '/plugins/');
        if (!defined('THEMES_DIR'))  define('THEMES_DIR',  $root . '/themes/');

        if (is_file($root . '/config.local.php')) {
            require_once $root . '/config.local.php';
        }
        if (is_file($root . '/config.example.php')) {
            require_once $root . '/config.example.php';
        }

        // BASE_URL must end with '/' because templates concatenate paths onto
        // it (BASE_URL . 'post/<slug>', BASE_URL . 'rss', ...). Warn loudly
        // when a custom config omits the trailing slash.
        if (defined('BASE_URL') && substr(BASE_URL, -1) !== '/') {
            error_log('[wawoo-cms] BASE_URL should end with a trailing slash: ' . BASE_URL);
        }

        if (!is_dir(\CACHE_DIR)) {
            @mkdir(\CACHE_DIR, 0755, true);
        }

        // Load core classes explicitly first; Autoloader only handles
        // plugin/theme namespaces discovered later.
        require_once __DIR__ . '/Hook.php';
        require_once __DIR__ . '/Manifest.php';
        require_once __DIR__ . '/Registry.php';
        require_once __DIR__ . '/Theme.php';
        require_once __DIR__ . '/Frontmatter.php';
        require_once __DIR__ . '/Markdown.php';
        require_once __DIR__ . '/Posts.php';
        require_once __DIR__ . '/Pages.php';
        require_once __DIR__ . '/Cache.php';
        require_once __DIR__ . '/RateLimit.php';
        require_once __DIR__ . '/Url.php';
        require_once __DIR__ . '/Autoloader.php';
        require_once __DIR__ . '/PluginManager.php';

        Autoloader::register();

        $registry = new Registry();
        $registry->discover('plugin', $root . '/plugins/');
        $registry->discover('theme',  $root . '/themes/');

        // Plugin/theme selection honours runtime state written by the admin
        // plugin (cache/plugins.json, cache/themes.json); the ENABLED_*
        // config constants act as the fallback defaults.
        $enabledPlugins = PluginManager::enabledPlugins();
        $enabledThemes  = PluginManager::activeTheme() !== null
            ? [PluginManager::activeTheme()]
            : (defined('ENABLED_THEMES') ? ENABLED_THEMES : ['default']);

        $registry->boot($enabledPlugins, 'plugin');
        $registry->boot($enabledThemes,  'theme');

        $active = $registry->activeTheme($enabledThemes);
        if ($active !== null) {
            Theme::activate($active->name, $active->path);
        }

        Hook::fire('core.boot', $registry);

        // Allow plugins to process the incoming request (form handlers,
        // rate limiters, etc.) before any template renders.
        Hook::fire('core.request', $root);

        // Surface boot-time errors as a PHP log warning but do not crash.
        foreach ($registry->errors() as $key => $msg) {
            error_log("[wawoo-cms] {$key}: {$msg}");
        }
    }
}
