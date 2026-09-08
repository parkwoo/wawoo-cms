# Changelog

All notable changes to wawoo-cms are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Fixed pages**: `pages/<slug>/index.md` served at their own top-level
  URL (`/about`), with `page.php` entry point, `Pages` core class,
  `page.php` theme templates in both themes, and a sample `pages/about/`
  bundle. Flat `pages/<slug>.md` also supported; images resolve relative
  to the page bundle.
- **New plugin `pagination`**: numbered pagination (1 2 3 … with
  ellipsis) on the home/category/tag list and search pages via a
  `pagination.render` hook + `search.before_render` slicing; themes fall
  back to the built-in Prev/Next when the plugin is disabled.
- **New plugin `contact`**: `/contact` inquiry form (CSRF token, session +
  IP rate limiting, honeypot, configurable labels), inbox archive under
  `cache/contact/`, optional `mail()` delivery, and an admin Settings page.
- **New plugin `s3`**: S3-compatible object storage. Pure-PHP SigV4
  client (stream contexts, no SDK/curl) uploads post/page media and
  `uploads/` to a bucket (AWS S3 / R2 / MinIO / …); admin Settings page
  configures credentials and syncs on demand; when enabled, rendered
  content and cover URLs are rewritten to the configured public base.
- **New plugin `publish`**: three-state publish workflow (draft → published,
  plus `scheduled` + `publish_at` for time-based publishing). Core gained
  `Posts::setStatus()` and `Posts::due()`; due posts are promoted on every
  request (no cron needed) or via `php bin/wawoo publish:due`. Admin
  console adds Publish / Schedule actions; CLI adds
  `status <slug> <draft|published|scheduled> [--at TIME]`.
- **New plugin `menu`**: navigation menu editor at `/admin → Plugins →
  menu → Settings`. Order Home, fixed pages, categories and custom links;
  saves to `cache/settings/menu.json` and renders through the
  `theme.nav.items` filter (themes fall back to their default Home +
  categories nav when no menu is configured).
- **`i18n` plugin now actually translates the UI**: themes (default &
  clay) and the admin console use a safe `t(key, default)` helper backed
  by `lang/{en,ja,zh}.php`; falls back to English when the plugin is off.
  New admin Settings page (default/supported languages, browser detect)
  persists to `cache/settings/i18n.json`.
- **New plugin `mlang`**: multi-language content. Translations live beside
  the default bundle (`posts/<slug>/index.ja.md`) and are served at
  `/ja/post/<slug>` (fixed pages at `/ja/<slug>`). Missing translations
  fall back to the default language; lists/feeds/sitemaps never duplicate
  entries. Core `Posts`/`Pages` gained an optional language parameter
  (backward compatible) and router/post/page understand the prefixed URLs.

### Changed
- **Gate & maintenance off by default**: `password-gate` is no longer in
  the default `ENABLED_PLUGINS` list. Both `password-gate` and the new
  `maintenance` plugin start disabled and never lock the site until an
  admin enables them and sets a password.
- **Admin per-plugin Settings**: plugins declaring `"settings": true` in
  `plugin.json` with a `src/Settings.php` (`handle(?string $root)`) get a
  **Settings** link in the admin Plugins table. Runtime config is stored
  in `cache/settings/<plugin>.json` (cache volume writable).
- **New plugin `maintenance`**: maintenance-mode page with HTTP 503 +
  `Retry-After: 3600` + robots `noindex` (SEO friendly); visitors enter
  via password; `/admin` stays reachable while active.
- `password-gate`: adds an on/off `enabled` flag and admin Settings page;
  an enabled-but-unconfigured gate no longer bricks the site.

### Architecture
- **Core is now a minimal CMS**: `index.php` (list), `post.php` (single
  post) and `page.php` (fixed pages) are the built-in pages. Every other
  page ships as a plugin and is optional.
- **Plugin pages via `core.request`**: `router.php` handles only core
  routes and static files; all other URLs go to `dispatch.php`, which
  fires `core.request` so plugins claim their own paths (unclaimed
  requests render the theme's 404).
- **New plugins extracted from core**:
  - `admin` — the former built-in `/admin` console (session login, post
    toggle/delete, plugin & theme management)
  - `rss` — the former `rss.php` (`/rss` + `/feed.xml`), also injects the
    `<link rel="alternate">` head tag and the nav RSS link via
    `theme.head.after` / `theme.nav`
  - `search` — the former `search.php` (`/search`)
  - `seo` — now also serves `/sitemap.xml` and `/robots.txt`
- **Removed core files**: `rss.php`, `search.php`, `sitemap.php`, `admin/`.
- **Bootstrap honours runtime plugin/theme state** (`cache/plugins.json`,
  `cache/themes.json`) written by the admin UI; `ENABLED_PLUGINS` /
  `ENABLED_THEMES` constants act as fallback defaults.
- **Default enabled plugins (core set)**: `admin`, `rss`, `search`,
  `seo`, `menu`, `contact`. Optional plugins (`pagination`, `analytics`,
  `password-gate`, `maintenance`, `comments`, `tag-cloud`,
  `sitemap-html`, `i18n`, `admin-totp`) are disabled by default and can be
  enabled per site in `/admin → Plugins`.

## [1.0.0] - 2026-09-07

### Added
- **Core kernel** (`includes/core/`): bootstrap, autoloader, manifest,
  registry, hook system, frontmatter parser, markdown renderer, post
  loader, file cache, theme loader.
- **Five entry points**: `index.php`, `post.php`, `search.php`,
  `rss.php`, `sitemap.php` and a `router.php` for pretty URLs.
- **Built-in admin dashboard** (toggle status / delete posts).
- **Two themes**:
  - `default` — GitHub-flavoured light/dark
  - `clay` — Claymorphism style ported from hirameki-loto's blog module
- **Eight shipped plugins**:
  - `seo` — meta trimming
  - `analytics` — Google Analytics injector
  - `password-gate` — full-site password gate (Shopify-style), IP
    rate-limited, HMAC-signed cookie
  - `comments` — comment form + admin moderation queue, rate-limited
  - `tag-cloud` — font-sized tag cloud rendered on the index
  - `sitemap-html` — paginated human-readable HTML sitemap at
    `/sitemap-page`
  - `i18n` — language pack loader (en, ja, zh shipped), URL/cookie/
    Accept-Language detection, header switcher
  - `admin-totp` — replaces the built-in admin gate with password +
    RFC 6238 TOTP (Google Authenticator compatible), backup codes
- **Deployment**: `Dockerfile` (PHP 8.3 Apache, opcache tuned, hardened
  defaults), `docker-compose.yml`, `docker-compose.prod.yml`, `.htaccess`
  rewrite rules.
- **PHPUnit test suite** under `tests/` — 52 tests covering Hook,
  Manifest, Registry, Frontmatter, Cache, Posts, password-gate, i18n,
  admin-totp (incl. RFC 6238 test vectors), comments store.
- **Documentation**: `README.md`, `CHANGELOG.md`, `VERSION`,
  per-plugin READMEs.

### Security
- All passwords stored as `password_hash()` (bcrypt), verified with
  `password_verify()`.
- Cookie values for the gate and admin are HMAC-SHA256, derived from
  per-installation secrets. No global signing keys.
- 2FA secret stored locally; never transmitted in plaintext.
- TOTP code comparison uses `hash_equals` (constant time).
- Failed password attempts are rate-limited per IP.
- Comment submissions are rate-limited per IP, validated, and require
  manual approval by default.

[Unreleased]: https://example.com/wawoo-cms/compare/v1.0.0...HEAD
[1.0.0]: https://example.com/wawoo-cms/releases/tag/v1.0.0
