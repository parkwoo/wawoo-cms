<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\PublicAssets;

/**
 * Static passthrough allow-list: only theme assets, uploads and post/page
 * bundle media may be served verbatim. Everything else (cache JSON, raw
 * markdown incl. drafts, plugin data, includes, config secrets, dotfiles)
 * must be rejected so router.php never leaks it over HTTP.
 */
final class PublicAssetTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__, 2);
        require_once self::$root . '/includes/core/PublicAssets.php';
    }

    /**
     * @dataProvider denyProvider
     */
    public function testDeniesSensitivePaths(string $urlPath): void
    {
        $this->assertFalse(
            PublicAssets::allowed(self::$root, $urlPath),
            "$urlPath must never be served statically"
        );
    }

    /**
     * @dataProvider allowProvider
     */
    public function testAllowsPublicAssets(string $urlPath): void
    {
        $this->assertTrue(
            PublicAssets::allowed(self::$root, $urlPath),
            "$urlPath must be a safe public asset"
        );
    }

    public static function denyProvider(): array
    {
        return [
            'draft list cache'       => ['/cache/posts-all.json'],
            'contact PII inbox'      => ['/cache/contact/inbox-1.json'],
            'plugin data'            => ['/plugins/comments/data/x.json'],
            'post markdown bundle'   => ['/posts/welcome/index.md'],
            'page markdown bundle'   => ['/pages/about/index.md'],
            'core include'           => ['/includes/core/Bootstrap.php'],
            'root env'               => ['/.env'],
            'local config'           => ['/config.local.php'],
            'example config'         => ['/config.example.php'],
            'theme template'         => ['/themes/default/templates/post.php'],
            'hidden dir in bundle'   => ['/posts/.hidden/secret.png'],
            'uploaded php'           => ['/uploads/shell.php'],
            'uploaded markdown'      => ['/uploads/notes.md'],
            'theme json'             => ['/themes/default/theme.json'],
            'theme data json'        => ['/themes/default/assets/data.json'],
            'composer manifest'      => ['/composer.json'],
            'root readme'            => ['/README.md'],
            'dotfile in theme assets'=> ['/themes/default/assets/.secret.css'],
            'backup markdown'        => ['/posts/welcome/index.md~'],
            'nested post file'       => ['/posts/welcome/sub/hero.svg'],
        ];
    }

    public static function allowProvider(): array
    {
        return [
            'theme css'      => ['/themes/default/assets/css/style.css'],
            'theme js'       => ['/themes/default/assets/js/post-images.js'],
            'theme font'     => ['/themes/clay/assets/fonts/x.woff2'],
            'uploaded png'   => ['/uploads/photo.png'],
            'uploaded pdf'   => ['/uploads/manual.pdf'],
            'post media'     => ['/posts/welcome/hero.svg'],
            'page media'     => ['/pages/about/team.jpg'],
            'favicon'        => ['/favicon.ico'],
        ];
    }

    public function testExistingPublicAssetPassesRouterGate(): void
    {
        $rootDir = realpath(self::$root);
        $this->assertNotFalse($rootDir);
        foreach ([
            '/themes/default/assets/css/style.css',
            '/themes/default/assets/js/post-images.js',
            '/posts/welcome/sample-diagram.svg',
            '/pages/about/sample.svg',
        ] as $urlPath) {
            $this->assertTrue(PublicAssets::allowed(self::$root, $urlPath));
            $file = realpath(self::$root . $urlPath);
            $this->assertNotFalse($file, "realpath failed for $urlPath");
            $this->assertTrue(is_file($file), "$urlPath is not a file");
            $this->assertStringStartsWith($rootDir . DIRECTORY_SEPARATOR, $file);
        }
    }

    public function testAbsentFileNeverPassesThrough(): void
    {
        // A safe-looking but absent asset: the location is allowed, yet
        // realpath() fails so the router cannot serve it.
        $this->assertTrue(PublicAssets::allowed(self::$root, '/themes/default/assets/css/missing.css'));
        $this->assertFalse(realpath(self::$root . '/themes/default/assets/css/missing.css'));

        // A sensitive path is rejected even before any file check.
        $this->assertFalse(PublicAssets::allowed(self::$root, '/cache/not-there.json'));
    }
}
