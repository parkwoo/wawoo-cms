<?php
namespace Wawoo\Plugin\Menu;

use Wawoo\Core\Pages;

/**
 * Theme nav items filter.
 *
 * Registered on `theme.nav.items`. Themes call
 *   Hook::filter('theme.nav.items', null, $vars)
 * and fall back to their built-in Home + categories nav when the result is
 * not a non-empty string. When this plugin is enabled and a menu has been
 * saved, it returns the rendered menu HTML instead.
 */
final class Items
{
    public function handle($html = null, ?array $vars = null)
    {
        $items = Config::items();
        if ($items === []) {
            return null; // let the theme render its default nav
        }
        $out = '';
        foreach ($items as $item) {
            $href  = self::href($item, $vars);
            $label = self::label($item, $vars);
            if ($href === '' || $label === '') {
                continue;
            }
            $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                 . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        return $out === '' ? null : $out;
    }

    private static function href(array $item, ?array $vars): string
    {
        $type = (string)($item['type'] ?? '');
        switch ($type) {
            case 'home':
                return BASE_URL;
            case 'custom':
                $url = trim((string)($item['url'] ?? ''));
                return $url === '' ? '' : (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 ? $url : BASE_URL . ltrim($url, '/'));
            case 'page':
                $slug = (string)($item['page'] ?? '');
                return $slug === '' ? '' : BASE_URL . preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
            case 'category':
                $cat = (string)($item['cat'] ?? '');
                return $cat === '' ? '' : BASE_URL . '?cat=' . urlencode(strtolower($cat));
        }
        return '';
    }

    private static function label(array $item, ?array $vars): string
    {
        $type = (string)($item['type'] ?? '');
        if ($type === 'custom' || $type === 'category') {
            return (string)($item['label'] ?? '');
        }
        if ($type === 'home') {
            $cfg = Config::all();
            return trim((string)($item['label'] ?? '')) !== ''
                ? (string)$item['label']
                : (string)($cfg['home_label'] ?? 'Home');
        }
        if ($type === 'page') {
            $slug = (string)($item['page'] ?? '');
            $page = Pages::get($slug);
            return $page['title'] ?? ucfirst($slug);
        }
        return '';
    }
}
