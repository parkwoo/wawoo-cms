<?php
/**
 * PHPUnit bootstrap.
 *
 * Sets up a clean test environment under tests/_tmp/ and loads the
 * wawoo-cms core classes from the repo root.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tmp  = $root . '/tests/_tmp';

// Clean & recreate the temp dir.
if (is_dir($tmp)) {
    foreach (glob($tmp . '/*') as $f) {
        if (is_file($f)) {
            @unlink($f);
        } elseif (is_dir($f)) {
            foreach (glob($f . '/*') as $g) {
                @unlink($g);
            }
            @rmdir($f);
        }
    }
} else {
    mkdir($tmp, 0755, true);
}

// Define core constants the same way config.example.php would.
define('POSTS_DIR',  $root . '/posts/');
define('PAGES_DIR',  $root . '/pages/');
define('CACHE_DIR',  $tmp . '/cache/');
define('PLUGINS_DIR',$root . '/plugins/');
define('THEMES_DIR', $root . '/themes/');
define('BASE_PATH',  '/');
define('BASE_URL',   'http://localhost/');
define('SITE_NAME',  'Test Site');
define('SITE_TITLE', 'Test Site');
define('SITE_DESCRIPTION', 'A site used by PHPUnit.');
define('SITE_KEYWORDS', 'test');
define('AUTHOR_NAME', 'Tester');
define('AUTHOR_EMAIL', 'tester@example.com');
define('DEFAULT_LANGUAGE', 'en');
define('DEFAULT_LOCALE', 'en_US');
define('SOCIAL_TWITTER', '');
define('SOCIAL_GITHUB', '');
define('SOCIAL_LINKEDIN', '');
define('TWITTER_USERNAME', '');
define('GA_TRACKING_ID', '');
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600);
define('POSTS_PER_PAGE', 6);
define('DEBUG', false);
define('ENABLED_PLUGINS', ['seo', 'analytics', 'comments', 'tag-cloud', 'sitemap-html', 'i18n', 'admin-totp', 'password-gate']);
define('ENABLED_THEMES', ['default']);

@mkdir(CACHE_DIR, 0755, true);
@mkdir($tmp . '/uploads', 0755, true);

// Load core classes directly (no Bootstrap to avoid plugin side effects).
require $root . '/includes/Parsedown.php';
require $root . '/includes/core/Hook.php';
require $root . '/includes/core/Manifest.php';
require $root . '/includes/core/Autoloader.php';
require $root . '/includes/core/Registry.php';
require $root . '/includes/core/Theme.php';
require $root . '/includes/core/Frontmatter.php';
require $root . '/includes/core/Markdown.php';
require $root . '/includes/core/Posts.php';
require $root . '/includes/core/Pages.php';
require $root . '/includes/core/Cache.php';
require $root . '/includes/core/RateLimit.php';
require $root . '/includes/core/Crypto.php';
require $root . '/includes/core/Csp.php';
require $root . '/includes/core/Langs.php';
require $root . '/includes/core/Url.php';
require $root . '/includes/core/PluginManager.php';
require $root . '/includes/core/Bootstrap.php';

// Register autoloader so plugin classes can be loaded by namespace.
\Wawoo\Core\Autoloader::register();

// Discover and register plugin namespaces from each shipped plugin.
foreach (glob($root . '/plugins/*/plugin.json') as $manifestFile) {
    $raw = json_decode((string)file_get_contents($manifestFile), true);
    if (!is_array($raw) || empty($raw['namespace'])) {
        continue;
    }
    $src = dirname($manifestFile) . '/src';
    if (is_dir($src)) {
        \Wawoo\Core\Autoloader::add($raw['namespace'], $src);
    }
}
