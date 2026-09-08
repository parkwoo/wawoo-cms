<?php
namespace Wawoo\Plugin\Seo;

use Wawoo\Core\Posts;

/**
 * Serves /sitemap.xml (XML sitemap) via core.request.
 * Also serves /robots.txt pointing search engines at it.
 */
final class Sitemap
{
    public function handle(?string $root = null): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($path === '/robots.txt') {
            $this->robots();
            return;
        }
        if ($path !== '/sitemap.xml') {
            return;
        }
        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '<url><loc>' . htmlspecialchars(\Wawoo\Core\Url::absolute('/')) . '</loc></url>' . "\n";
        foreach (Posts::all() as $p) {
            $url = \Wawoo\Core\Url::absolute('post/' . urlencode($p['slug']));
            echo '<url>';
            echo '<loc>' . htmlspecialchars($url) . '</loc>';
            echo '<lastmod>' . date('Y-m-d', strtotime($p['date'])) . '</lastmod>';
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    private function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo 'Sitemap: ' . \Wawoo\Core\Url::absolute('sitemap.xml') . "\n";
        exit;
    }
}
