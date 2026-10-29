<?php
/**
 * Plugin/Theme state manager.
 *
 * Persists enabled plugins and active theme in cache/plugins.json
 * and cache/themes.json so the admin UI can toggle them without
 * editing config.local.php by hand.
 */

namespace Wawoo\Core;

final class PluginManager
{
    public static function enabledPlugins(): array
    {
        $f = \CACHE_DIR . 'plugins.json';
        if (!is_file($f)) {
            return defined('ENABLED_PLUGINS') ? ENABLED_PLUGINS : [];
        }
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data['enabled'] ?? null) ? $data['enabled'] : [];
    }

    public static function activeTheme(): ?string
    {
        $f = \CACHE_DIR . 'themes.json';
        if (!is_file($f)) {
            return defined('ENABLED_THEMES') && !empty(ENABLED_THEMES) ? ENABLED_THEMES[0] : null;
        }
        $data = json_decode((string)file_get_contents($f), true);
        return $data['active'] ?? null;
    }

    public static function discoverPlugins(string $root): array
    {
        $out = [];
        foreach (glob($root . '/plugins/*/plugin.json') as $file) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (!is_array($raw) || empty($raw['name'])) {
                continue;
            }
            $raw['_path'] = dirname($file);
            $out[] = $raw;
        }
        return $out;
    }

    public static function discoverThemes(string $root): array
    {
        $out = [];
        foreach (glob($root . '/themes/*/theme.json') as $file) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (!is_array($raw) || empty($raw['name'])) {
                continue;
            }
            $raw['_path'] = dirname($file);
            $out[] = $raw;
        }
        return $out;
    }

    public static function setEnabledPlugins(array $enabled): void
    {
        $f = \CACHE_DIR . 'plugins.json';
        if (!is_dir(\CACHE_DIR) && !@mkdir(\CACHE_DIR, 0755, true) && !is_dir(\CACHE_DIR)) {
            error_log('[wawoo-cms] cannot create cache dir: ' . \CACHE_DIR);
            return;
        }
        if (file_put_contents($f, json_encode(['enabled' => array_values($enabled)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            error_log('[wawoo-cms] cannot write ' . $f);
        }
    }

    public static function setActiveTheme(string $theme): void
    {
        $f = \CACHE_DIR . 'themes.json';
        if (!is_dir(\CACHE_DIR) && !@mkdir(\CACHE_DIR, 0755, true) && !is_dir(\CACHE_DIR)) {
            error_log('[wawoo-cms] cannot create cache dir: ' . \CACHE_DIR);
            return;
        }
        if (file_put_contents($f, json_encode(['active' => $theme], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            error_log('[wawoo-cms] cannot write ' . $f);
        }
    }
}
