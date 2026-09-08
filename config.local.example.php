<?php
/**
 * Local config template. Copy to config.local.php and fill in.
 * config.local.php is .gitignored.
 */

// Site identity
define('SITE_NAME',        'My wawoo-cms');
define('SITE_TITLE',       'My wawoo-cms');
define('SITE_DESCRIPTION', 'Personal notes on software, AI, and things I build.');
define('AUTHOR_NAME',      'Your Name');
define('AUTHOR_EMAIL',     'you@example.com');

// Base URL (must end with a single trailing slash). e.g. https://blog.example.com/
define('BASE_URL',  'https://blog.example.com/');
define('BASE_PATH', '/');

// Cache
define('CACHE_ENABLED',  true);
define('CACHE_DURATION', 3600);

// Listing
define('POSTS_PER_PAGE', 6);

// Plugins & themes to enable. Manifests live under plugins/<name>/plugin.json
// and themes/<name>/theme.json. Names are matched against manifest "name".
define('ENABLED_PLUGINS', ['seo', 'analytics']);
define('ENABLED_THEMES',  ['default']);

// Optional: Google Analytics for the analytics plugin
define('GA_TRACKING_ID', '');

// Social
define('SOCIAL_TWITTER',  '@you');
define('TWITTER_USERNAME','@you');
