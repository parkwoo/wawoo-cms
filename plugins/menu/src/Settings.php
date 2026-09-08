<?php
namespace Wawoo\Plugin\Menu;

use Wawoo\Core\Pages;

/**
 * Admin menu editor. Persists to cache/settings/menu.json via Config.
 *
 * Contract: the admin Gate calls handle(?string $root) after requiring this
 * file; the method renders its own HTML page and exits.
 */
final class Settings
{
    /** @var string */
    private static $notice = '';
    /** @var string */
    private static $error = '';

    public function handle(?string $root = null): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!\Wawoo\Plugin\Admin\Csrf::verify(\Wawoo\Plugin\Admin\Csrf::posted())) {
                self::$notice = '';
                self::$error  = 'Invalid or missing CSRF token.';
            } else {
                self::saveFromPost($_POST);
            }
        }
        $current = Config::items();
        $pages   = Pages::all();
        $cats    = self::availableCategories();

        echo self::html($current, $pages, $cats, self::$error, self::$notice);
        exit;
    }

    /**
     * Build a menu from the form:
     *   rows[]      = slot ids that are checked
     *   order[<id>] = sort number per slot
     *   custom_label[<id>], custom_url[<id>] for custom slots
     * A custom slot is enabled by checking it AND providing label+url.
     */
    public static function saveFromPost(array $post): void
    {
        self::$notice = '';
        self::$error  = '';
        $rows  = (array)($post['rows'] ?? []);
        $order = (array)($post['order'] ?? []);
        $ids   = [];
        foreach ($rows as $id) {
            $ids[(string)$id] = (int)($order[(string)$id] ?? 1000);
        }
        asort($ids);

        $items = [];
        foreach (array_keys($ids) as $id) {
            if (strpos($id, 'custom_') === 0) {
                $cId = substr($id, 7);
                $label = trim((string)($post['custom_label'][$cId] ?? ''));
                $url   = trim((string)($post['custom_url'][$cId] ?? ''));
                if ($label === '' || $url === '') {
                    continue; // silently skip incomplete custom rows
                }
                $items[] = ['type' => 'custom', 'label' => $label, 'url' => $url];
                continue;
            }
            if ($id === 'home') {
                $items[] = ['type' => 'home'];
                continue;
            }
            if (strpos($id, 'page_') === 0) {
                $items[] = ['type' => 'page', 'page' => substr($id, 5)];
                continue;
            }
            if (strpos($id, 'cat_') === 0) {
                $cat = base64_decode(substr($id, 4), true);
                if ($cat !== false && $cat !== '') {
                    $items[] = ['type' => 'category', 'cat' => $cat, 'label' => $cat];
                }
            }
        }

        Config::saveItems($items);
        self::$notice = 'Menu saved.';
    }

    private static function availableCategories(): array
    {
        $cats = [];
        foreach (\Wawoo\Core\Posts::all() as $p) {
            $c = (string)$p['category'];
            if ($c !== '' && !in_array($c, $cats, true)) {
                $cats[] = $c;
            }
        }
        sort($cats);
        return $cats;
    }

    private static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private static function html(array $current, array $pages, array $cats, string $error, string $notice): string
    {
        $row = function (string $id, string $typeLabel, string $detail, bool $checked): string {
            $cb = '<input type="checkbox" name="rows[]" value="' . self::e($id) . '"' . ($checked ? ' checked' : '') . '>';
            return '<tr><td>' . $cb . '</td><td><input type="number" name="order[' . self::e($id) . ']" value="0" style="width:4.5em"></td>'
                 . '<td>' . self::e($typeLabel) . '</td><td>' . $detail . '</td></tr>';
        };

        $order = 1;
        $rowsHtml = '';
        // home
        $checked = false;
        foreach ($current as $i) {
            if (($i['type'] ?? '') === 'home') {
                $checked = true;
                break;
            }
        }
        $rowsHtml .= $row('home', 'Home', '&nbsp;', $checked);

        // pages
        foreach ($pages as $p) {
            $checked = false;
            foreach ($current as $i) {
                if (($i['type'] ?? '') === 'page' && ($i['page'] ?? '') === $p['slug']) {
                    $checked = true;
                    break;
                }
            }
            $rowsHtml .= $row('page_' . $p['slug'], 'Page', '<code>/' . self::e($p['slug']) . '</code> — ' . self::e($p['title']), $checked);
        }

        // categories
        foreach ($cats as $c) {
            $checked = false;
            foreach ($current as $i) {
                if (($i['type'] ?? '') === 'category' && strcasecmp((string)($i['cat'] ?? ''), $c) === 0) {
                    $checked = true;
                    break;
                }
            }
            $rowsHtml .= $row('cat_' . base64_encode($c), 'Category', self::e($c), $checked);
        }

        // custom (persisted)
        $customIndex = 0;
        foreach ($current as $i) {
            if (($i['type'] ?? '') !== 'custom') {
                continue;
            }
            $cid = (string)$customIndex;
            $customIndex++;
            $detail = '<input type="text" name="custom_label[' . $cid . ']" value="' . self::e($i['label'] ?? '') . '" placeholder="Label" style="width:8em"> '
                    . '<input type="text" name="custom_url[' . $cid . ']" value="' . self::e($i['url'] ?? '') . '" placeholder="/about" style="width:12em">';
            $rowsHtml .= $row('custom_' . $cid, 'Custom', $detail, true);
        }

        // a free custom row for adding
        $cid = 'new';
        $detail = '<input type="text" name="custom_label[' . $cid . ']" value="" placeholder="Label" style="width:8em"> '
                . '<input type="text" name="custom_url[' . $cid . ']" value="" placeholder="/url" style="width:12em">';
        $rowsHtml .= $row('custom_' . $cid, 'Custom (new)', $detail, true);

        $noticeHtml = $notice !== '' ? '<p style="color:#0a7d32;background:#e7f7ee;padding:.6rem;border-radius:8px">' . self::e($notice) . '</p>' : '';
        $errorHtml  = $error !== '' ? '<p style="color:#b00020;background:#ffe9e9;padding:.6rem;border-radius:8px">' . self::e($error) . '</p>' : '';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Menu - Admin</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#f5f5f7;margin:0;padding:2rem;color:#1d1d1f}'
            . '.card{background:#fff;border-radius:14px;padding:1.5rem;max-width:820px;margin:0 auto 1rem;box-shadow:0 10px 30px rgba(0,0,0,.06)}'
            . 'table{width:100%;border-collapse:collapse}th,td{padding:.45rem .5rem;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}'
            . 'input,select{padding:.4rem;border:1px solid #d2d2d7;border-radius:8px;font-size:.9rem}'
            . 'button{background:#1d1d1f;color:#fff;border:0;border-radius:8px;padding:.6rem 1.1rem;cursor:pointer;margin-top:1rem}'
            . 'code{background:#f0f0f2;padding:.1rem .3rem;border-radius:4px}'
            . '.hint{color:#6e6e73;font-size:.85rem}</style></head><body>'
            . '<div class="card"><h1>Menu</h1>'
            . '<p><a href="/admin/?action=plugins">&larr; Back to plugins</a></p>'
            . $errorHtml . $noticeHtml
            . '<form method="post" action="/admin/?action=settings&plugin=menu">'
            . \Wawoo\Plugin\Admin\Csrf::field()
            . '<p class="hint">Check the items you want in the navigation, then set their order (lowest first). '
            . 'Custom rows need a label and URL. Leaving every row unchecked removes the plugin menu and restores the theme default.</p>'
            . '<table><tr><th>Show</th><th>Order</th><th>Type</th><th>Item</th></tr>' . $rowsHtml . '</table>'
            . '<button type="submit">Save menu</button>'
            . '</form></div></body></html>';
    }
}
