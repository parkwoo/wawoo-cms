<?php
namespace Wawoo\Plugin\Menu;

final class Config
{
    /** @var array<string,mixed>|null */
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $manifest = [];
        $file = dirname(__DIR__) . '/plugin.json';
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw) && isset($raw['config']) && is_array($raw['config'])) {
                $manifest = $raw['config'];
            }
        }
        $override = $GLOBALS['WAWOO_MENU'] ?? [];
        self::$cache = array_replace([
            'home_label' => 'Home',
            'items'      => [],
        ], $manifest, is_array($override) ? $override : []);
        return self::$cache;
    }

    /** Runtime menu items from cache/settings/menu.json (admin-managed). */
    public static function items(): array
    {
        $f = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'menu.json';
        if (!is_file($f)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($f), true);
        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    public static function saveItems(array $items): void
    {
        $dir = rtrim(\CACHE_DIR, '/\\') . DIRECTORY_SEPARATOR . 'settings';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'menu.json';
        $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode(['items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $file);
        } else {
            @unlink($tmp);
        }
    }
}
