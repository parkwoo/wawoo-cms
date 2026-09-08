<?php
namespace Wawoo\Plugin\Contact;

/**
 * Merges plugin.json defaults with the optional $WAWOO_CONTACT override
 * defined in config.local.php, then applies any settings saved by the
 * admin UI (CACHE_DIR/settings/contact.json) on top.
 *
 * Priority: settings file > $WAWOO_CONTACT > manifest defaults.
 */
final class Config
{
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = [];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $defaults = $raw['config'];
            }
        }
        $override = [];
        if (isset($GLOBALS['WAWOO_CONTACT']) && is_array($GLOBALS['WAWOO_CONTACT'])) {
            $override = $GLOBALS['WAWOO_CONTACT'];
        }
        $settings = self::settingsOverrides();
        return self::$cache = array_replace($defaults, $override, $settings);
    }

    public static function labels(): array
    {
        $labels = self::all()['labels'] ?? [];
        return is_array($labels) ? $labels : [];
    }

    private static function settingsOverrides(): array
    {
        $file = rtrim(\CACHE_DIR, '/\\') . '/settings/contact.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = json_decode((string)@file_get_contents($file), true);
        return is_array($raw) ? $raw : [];
    }
}
