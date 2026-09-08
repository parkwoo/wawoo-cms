<?php
/**
 * Default config. Override by copying to config.local.php.
 *
 * SECURITY: do NOT commit secrets. config.local.php should be .gitignored.
 */

// Base paths are defined by Bootstrap::init() using $root.
// Override here only if you need a different layout.

// URL paths. Override in config.local.php if installed under a subpath.
// BASE_URL always ends with a single trailing slash.
//
// Production: set BASE_URL to the canonical origin AND list allowed host
// names in ALLOWED_HOSTS, otherwise the base URL is derived from the Host
// header and is vulnerable to Host-header poisoning (canonical/SEO abuse).
// An empty ALLOWED_HOSTS disables the check (development convenience).
if (!defined('BASE_PATH')) define('BASE_PATH', '/');
if (!defined('ALLOWED_HOSTS')) define('ALLOWED_HOSTS', []);
if (!defined('BASE_URL')) {
    $__proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $__host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__hostNoPort = preg_replace('/:\d+$/', '', (string)$__host);
    if (defined('ALLOWED_HOSTS') && is_array(ALLOWED_HOSTS) && ALLOWED_HOSTS !== []) {
        $__ok = in_array($__hostNoPort, ALLOWED_HOSTS, true) || in_array($__host, ALLOWED_HOSTS, true);
        if (!$__ok) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Host not allowed.');
        }
    }
    define('BASE_URL', $__proto . $__host . '/');
}

// Site identity
if (!defined('SITE_NAME'))         define('SITE_NAME',         'wawoo-cms');
if (!defined('SITE_TITLE'))        define('SITE_TITLE',        'wawoo-cms');
if (!defined('SITE_DESCRIPTION'))  define('SITE_DESCRIPTION',  'A lightweight, themeable, plugin-extensible blog in PHP.');
if (!defined('SITE_KEYWORDS'))     define('SITE_KEYWORDS',     'blog, php, markdown');
if (!defined('AUTHOR_NAME'))       define('AUTHOR_NAME',       'wawoo');
if (!defined('AUTHOR_EMAIL'))      define('AUTHOR_EMAIL',      '');
if (!defined('DEFAULT_LANGUAGE'))  define('DEFAULT_LANGUAGE',  'en');
if (!defined('DEFAULT_LOCALE'))    define('DEFAULT_LOCALE',    'en_US');

// Social
if (!defined('SOCIAL_TWITTER'))    define('SOCIAL_TWITTER',    '');
if (!defined('SOCIAL_GITHUB'))     define('SOCIAL_GITHUB',     '');
if (!defined('SOCIAL_LINKEDIN'))   define('SOCIAL_LINKEDIN',   '');
if (!defined('TWITTER_USERNAME'))  define('TWITTER_USERNAME',  '');

// Analytics (only used by the analytics plugin if enabled)
if (!defined('GA_TRACKING_ID'))    define('GA_TRACKING_ID',    '');

// Cache
if (!defined('CACHE_ENABLED'))     define('CACHE_ENABLED',     true);
if (!defined('CACHE_DURATION'))    define('CACHE_DURATION',    3600);

// Listing
if (!defined('POSTS_PER_PAGE'))    define('POSTS_PER_PAGE',    6);

// Security (set to false ONLY for local debugging)
if (!defined('DEBUG'))             define('DEBUG',             false);

// Enabled plugins and themes. Edit in config.local.php to change.
//
// CORE plugins — enabled by default; they provide the base CMS experience:
//   admin          – /admin console (post & plugin/theme management)
//   rss            – /rss feed + nav/head links
//   search         – /search page
//   seo            – meta trimming + /sitemap.xml + /robots.txt
//   menu           – navigation menu editor (pages/categories/custom links)
//   contact        – /contact inquiry form (CSRF, rate-limited, inbox + mail)
//
// OPTIONAL plugins — disabled by default; enable per site in /admin or here:
//   pagination     – numbered pagination (1 2 3 …); core list already has
//                    Prev/Next, so this is an optional enhancement
//   analytics      – GA snippet (no-op without GA_TRACKING_ID)
//   password-gate  – site-wide password gate (set a password in its Settings)
//   maintenance    – maintenance-mode page (503 + Retry-After, password entry)
//   comments       – comment forms/moderation
//   tag-cloud      – font-sized tag cloud on the index
//   sitemap-html   – human-readable /sitemap-page
//   i18n           – language switcher (en, ja, zh)
//   admin-totp     – RFC 6238 TOTP second factor for /admin
if (!defined('ENABLED_PLUGINS'))   define('ENABLED_PLUGINS',   ['admin', 'rss', 'search', 'seo', 'menu', 'contact']);
if (!defined('ENABLED_THEMES'))    define('ENABLED_THEMES',    ['default']);

// Security headers (light defaults; tighten in config.local.php for production)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
