# wawoo-cms

A **file-based CMS built for AI agents**. No database, no build step, no
framework — plain PHP 8.3, markdown content, and a small hook/plugin
system. Every feature beyond the minimal core (list, post, fixed pages,
theme, plugin loader) ships as an optional plugin, so the surface area an
agent has to learn is tiny.

What makes it agent-friendly:

- **One file per concept**: posts and pages are markdown bundles
  (`posts/<slug>/index.md`), plugins/themes declare themselves in
  `plugin.json`, config is a single PHP file with `config.example.php`
  defaults.
- **Deterministic and scriptable**: `bin/wawoo` CLI for creating posts,
  pages and plugins, flushing cache and running diagnostics; everything
  also works over plain HTTP.
- **Readable by construction**: the whole architecture fits in the
  `## Layout` tree below; `AGENTS.md` is the on-ramp doc that describes
  exactly how to build with (and extend) wawoo-cms.
- **Everything optional**: admin, RSS, search, sitemaps, contact,
  password gates, maintenance mode, menus … are plugins. Disable what you
  don't need, or read the enabled list in one glance from
  `config.local.php`.

**Version:** 1.0.0 · **Tests:** 256 PHPUnit · **PHP files:** 84+ · **Lines:** ~7,000

## Layout

```
wawoo-cms/
├── config.example.php         shipped defaults (committed)
├── config.local.example.php   copy to config.local.php and edit
├── index.php                  home / list / filter (core)
├── post.php                   single post (core)
├── page.php                   fixed page at its own URL (core)
├── router.php                 core routing: / /post/x /cat/x /tag/x /<page> + static
├── dispatch.php               plugin pages (/rss /search /sitemap.xml /admin …)
├── posts/                     post bundles: one folder per post
│   └── <slug>/index.md        post content + images in the same folder
├── pages/                     fixed pages (about, privacy, …)
│   └── <slug>/index.md        page content, served at /<slug>
├── includes/
│   ├── Parsedown.php          markdown parser (vendored)
│   └── core/                  bootstrap, hooks, manifests, autoloader, cache
├── themes/
│   ├── default/               GitHub-flavoured light/dark theme
│   └── clay/                  Claymorphism style theme
├── plugins/
│   ├── admin/                 /admin console (posts, plugins, themes)  [core]
│   ├── rss/                   /rss feed + nav/head links               [core]
│   ├── search/                /search page                              [core]
│   ├── seo/                   meta + /sitemap.xml + /robots.txt         [core]
│   ├── menu/                  navigation menu editor                    [core]
│   ├── contact/               /contact inquiry form (CSRF + inbox)      [core]
│   ├── analytics/             Google Analytics injector               [optional]
│   ├── pagination/            numbered pagination (1 2 3 …)           [optional]
│   ├── password-gate/         site-wide password gate                 [optional]
│   ├── maintenance/           maintenance-mode page (503+Retry-After) [optional]
│   ├── comments/              comment form + moderation                 [optional]
│   ├── tag-cloud/             font-sized tag cloud                      [optional]
│   ├── sitemap-html/          human-readable /sitemap-page             [optional]
│   ├── i18n/                  UI translation (en/ja/zh) + Settings      [optional]
│   ├── mlang/                 multi-language content (/ja/post/x, …)    [optional]
│   ├── publish/               publish workflow + scheduled publishing   [optional]
│   ├── s3/                    S3-compatible object storage sync         [optional]
│   └── admin-totp/            TOTP second factor for /admin             [optional]
├── tests/                     PHPUnit suite (256 tests)
├── Dockerfile                 PHP 8.3 Apache, opcache tuned
├── docker-compose.yml         development compose
├── docker-compose.prod.yml    production compose
├── .htaccess                  Apache rewrite + security headers
└── cache/                     generated cache
```

## Quick start

1. Copy `config.local.example.php` to `config.local.php` and edit.
2. Create a post bundle in `posts/` (one folder per post):

   ```
   posts/
   └── hello-world/
       ├── index.md        ← the post (frontmatter + markdown)
       ├── hero.png        ← its images, in the same folder
       └── diagram.svg
   ```

3. Serve the directory with PHP's built-in server for a smoke test:

   ```bash
   php -S 127.0.0.1:8000 router.php
   ```

4. For production, point nginx/apache at the directory and route
   everything through `router.php`, or use Docker:

   ```bash
   docker compose up -d
   ```

Legacy flat posts (`posts/<slug>.md`) still load; bundles take precedence
when both exist.

## Fixed pages

Fixed pages are for content that is not part of the blog stream — About,
Privacy, Contact info, etc. They render at their own top-level URL and are
never listed in the post index, categories, tags, search or feeds.

```
pages/
└── about/
    ├── index.md        ← the page (frontmatter + markdown)
    └── team.jpg        ← its images, in the same folder
```

Visiting `/about` renders it. Frontmatter supports `title`, `excerpt`,
`cover`/`cover_caption` (same rules as posts), and `order` (used by nav /
menu helpers). Flat `pages/<slug>.md` files are also supported.

A page that does not exist returns the theme's 404. A fixed page whose
slug collides with a plugin path (e.g. `/contact`) wins over the plugin;
rename the page or disable the plugin to avoid surprises.

## Multi-language

Two optional plugins work together for bilingual/multilingual sites:

- **`i18n`** translates the **interface** (theme nav, buttons, admin
  console) into `en`/`ja`/`zh`. Templates call `t('nav.home', 'Home')` —
  when the plugin is off the English default is used, when it is on the
  active language's value is returned. Language is chosen via the nav
  `?lang=` switcher, a cookie, or the browser's `Accept-Language`
  (configurable under `/admin → Plugins → i18n → Settings`).
- **`mlang`** translates the **content**. Keep the default-language
  bundle as usual and add per-language files in the same folder:

  ```
  posts/hello-world/
      index.md          ← default language (e.g. English)
      index.ja.md       ← Japanese
      index.zh.md       ← Chinese
  ```

  Japanese is then served at `/ja/post/hello-world`, Chinese at
  `/zh/post/hello-world` (fixed pages: `/ja/about`, …). Missing
  translations fall back to the default-language content. The blog list,
  feeds and sitemap only ever show the default-language entries — never
  duplicates.

Both plugins are optional and off by default; enable them in
`config.local.php` or `/admin → Plugins`.

## Publishing workflow

Posts have a three-state lifecycle. The `publish` plugin (optional,
enabled in `/admin → Plugins`) makes the states usable from the admin
console and adds time-based publishing; the states themselves live in the
frontmatter and always apply:

- `status: draft` — private. An AI or author writes the post as a draft;
  it is not listed, linked or served publicly. This is the "awaiting
  human review" state.
- `status: published` — public. The admin **Publish** button (or
  `php bin/wawoo status <slug> published`) promotes a draft after a human
  confirms it.
- `status: scheduled` + `publish_at: 1750000000` (unix timestamp or any
  `strtotime` string) — becomes public automatically once the time
  passes. Wawoo checks due posts on every request, so no cron daemon is
  required; for exact timing run `php bin/wawoo publish:due` from cron.
  From the admin console choose **Schedule** on a post row and pick a
  datetime.

Shortcuts:

```bash
php bin/wawoo status welcome draft                 # take private
php bin/wawoo status welcome published             # publish now
php bin/wawoo status welcome scheduled --at "2026-09-10 09:00"
php bin/wawoo publish:due                          # promote due posts now
```

## Object storage (S3)

The `s3` plugin (optional) moves post/page media to an S3-compatible
bucket and serves it from there. It works with AWS S3, Cloudflare R2,
MinIO and any S3 API implementation — the SigV4 client is pure PHP
(stream contexts), so no SDK, composer or curl extension is needed.

Setup in `/admin → Plugins → s3 → Settings`:

- `endpoint` — e.g. `https://s3.amazonaws.com`, `https://<account>.r2.cloudflarestorage.com` or `http://minio:9000`
- `region`, `bucket`, `access_key`, `secret_key`
- `public_base` — public URL prefix used in rendered pages, e.g.
  `https://media.example.com/media`
- `prefix` — object key prefix inside the bucket (default `media`)
- `enabled` — when on, rendered post/page HTML and `cover` URLs that point
  at `/posts/…`, `/pages/…`, `/uploads/…` are rewritten to the public base
- press **Save & sync** to upload all media files now

Sync is idempotent: files already present are skipped unless `force_path`
is enabled. Local files remain the source of truth.

## Images

Each post lives in its own folder, so a post and its images travel
together — deleting a post in the admin removes the whole folder, leaving
no orphan images.

```markdown
![Alt text](hero.jpg "Optional caption")     <!-- relative to this post -->
![Shared](/uploads/logo.png)                  <!-- absolute path, shared -->
```

Standalone image paragraphs are rendered as `<figure>` with a caption
(title, falling back to alt) and a zoom link. Clicking opens a lightbox
(native `<dialog>`, no dependencies). Nested images (inside a link) are
left untouched.

Optional frontmatter for a hero image:

```yaml
cover: hero.png            # relative to the post folder (resolved to /posts/<slug>/hero.png)
cover_alt: A descriptive alternative text
cover_caption: Optional visible caption under the hero
```

The cover renders above the post title as a figure and is also emitted
as `og:image` for social shares.

Path rules:

- Relative image paths (`hero.jpg`, `./hero.jpg`) resolve against the
  post's own folder at `/posts/<slug>/…`.
- Absolute paths (`/uploads/…`, `https://…`) are used as-is — put shared
  assets under `uploads/`, which is bind-mounted into Docker.
- After adding/editing a post, flush the file cache (`rm -f cache/*.json`
  or run the toggle action in admin) if you don't want to wait for the
  cache TTL.


## Pretty URLs

The shipped `router.php` handles core routes only; every other URL is
handed to `dispatch.php`, which fires `core.request` so feature plugins
can claim their paths (a plugin page that is disabled returns 404).

| URL                  | Handler                          |
|----------------------|----------------------------------|
| `/`                  | `index.php` (core)               |
| `/post/<slug>`       | `post.php?slug=<slug>` (core)    |
| `/<slug>`            | `page.php` if `pages/<slug>` exists (core) |
| `/cat/<name>`        | `index.php?cat=<name>` (core)    |
| `/tag/<name>`        | `index.php?tag=<name>` (core)    |
| `/search`            | `search` plugin                  |
| `/contact`           | `contact` plugin                 |
| `/rss`               | `rss` plugin                     |
| `/sitemap.xml`       | `seo` plugin (XML sitemap)       |
| `/robots.txt`        | `seo` plugin                     |
| `/sitemap-page`      | `sitemap-html` plugin            |
| `/password`          | `password-gate` plugin           |
| `/admin`             | `admin` plugin                   |
| `/themes/...`        | static asset                     |
| `/posts/...`         | static asset                     |
| `/pages/...`         | static asset (page bundle images)|
| anything else        | `dispatch.php` → 404             |

Plugins whose pages are not enabled simply don't answer — the URL then
falls through to the 404 template.

Nginx example:

```nginx
location /blog {
    root /var/www;
    try_files $uri $uri/ /blog/router.php?$args;
    location ~ \.php$ { fastcgi_pass unix:/run/php/php-fpm.sock; ... }
}
```

Apache `.htaccess` is shipped for pretty URL rewrites, security headers,
compression, and caching rules.

## Themes

A theme is a directory under `themes/` containing:

- `theme.json` — `{name, version, namespace, entry, requires, hooks, config}`
- `templates/` — `_header.php`, `_footer.php`, `index.php`, `post.php`, `404.php`, `admin/dashboard.php`, ...
- `assets/`    — CSS/JS/images, exposed via `Theme::asset('css/style.css')`

Activate a theme by adding its name to `ENABLED_THEMES` in
`config.local.php`. The first enabled theme is the active one.

### Shipped themes

- **default** — GitHub-flavoured light/dark mode styling.
- **clay** — Claymorphism style ported from hirameki-loto's blog module.

### Theme hooks

- `theme.head`       — pass-by-ref array `['title','description','type','canonical']`
- `theme.head.after` — emit extra `<head>` content (analytics, i18n lang switcher etc.)
- `theme.footer`     — emit extra markup before `</body>`
- `theme.index.after`, `theme.post.after`, `theme.search.after`, `theme.admin.after`

## Plugins

A plugin is a directory under `plugins/` containing:

- `plugin.json` — `{name, version, namespace, entry, requires, hooks, config}`
- `src/`        — PSR-4 classes matching `namespace`

Manifest fields:

| Field      | Meaning                                          |
|------------|--------------------------------------------------|
| name       | unique slug                                      |
| version    | semver-ish string                                |
| namespace  | PSR-4 root, must end in `\\` if present          |
| entry      | class name to instantiate and call `boot()`      |
| requires   | map of `name => version constraint`              |
| hooks      | map of `hookName => classShortName`              |
| config     | arbitrary data passed to the plugin              |

Version constraints support: `>=`, `>`, `<=`, `<`, `=`, `!=`, `*`, bare.

### Shipped plugins

| Plugin        | Description                                                  |
|---------------|--------------------------------------------------------------|
| `admin`       | `/admin` console: session login, post toggle/delete, plugin/theme management, per-plugin Settings |
| `rss`         | `/rss` feed (RSS 2.0) + nav/head links                       |
| `search`      | `/search` full-text page                                     |
| `seo`         | Meta trimming + `/sitemap.xml` + `/robots.txt`               |
| `analytics`   | Google Analytics injector                                    |
| `pagination`  | Numbered pagination (1 2 3 … with ellipsis) on list, category, tag and search pages; falls back to plain Prev/Next when disabled |
| `contact`     | `/contact` inquiry form: CSRF, session + IP rate limit, honeypot, configurable labels, inbox archive under `cache/contact/`, optional `mail()` delivery; Settings page under `/admin → Plugins → contact → Settings` |
| `menu`        | Navigation menu editor. `/admin → Plugins → menu → Settings` lets you order Home, fixed pages and categories and add custom links; the menu renders via the `theme.nav.items` filter and replaces the theme's default nav only when a menu is saved. |
| `password-gate`| Optional site-wide password gate (OFF by default). Enable + set a password in `/admin → Plugins → password-gate → Settings`. IP rate-limited, HMAC cookie. |
| `maintenance` | Optional maintenance mode (OFF by default). Serve a maintenance page with HTTP 503 + `Retry-After` (SEO friendly) and enter via password. Configure in `/admin → Plugins → maintenance → Settings`. |
| `comments`    | Comment form + admin moderation queue, rate-limited          |
| `tag-cloud`   | Font-sized tag cloud rendered on the index                   |
| `sitemap-html`| Paginated human-readable HTML sitemap at `/sitemap-page`     |
| `i18n`        | UI translation for themes & admin console (en/ja/zh): template `t()` helper with English fallback, nav language switcher, language detection, Settings page under `/admin → Plugins → i18n → Settings` |
| `mlang`       | Multi-language content: per-bundle translations `posts/<slug>/index.ja.md`, served at `/ja/post/<slug>` (pages at `/ja/<slug>`); missing translations fall back to the default language; "other languages" links rendered on posts/pages. Enable alongside `i18n` for translated UI text. |
| `publish`     | Publish workflow + scheduled publishing. A post in `draft` is private; `published` is public; `scheduled` + `publish_at` becomes public automatically once the time passes (checked on every request, so no cron needed — or run `php bin/wawoo publish:due` from cron). Admin console offers Publish / Schedule / Toggle per post. |
| `s3`          | S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, …). Syncs post/page media (`posts/<slug>/…`, `pages/<slug>/…`, `uploads/…`) to a bucket with a pure-PHP SigV4 client (no SDK/curl), then rewrites content URLs to the object-storage public base at render time. Configure credentials + endpoint in `/admin → Plugins → s3 → Settings` and press **Save & sync**. |
| `admin-totp`  | Password + RFC 6238 TOTP (Google Authenticator compatible) second factor for `/admin`, backup codes |

### Available hooks

- `core.boot`           — after plugins/themes are loaded
- `core.request`        — on every request; plugin page handlers inspect the URL and short-circuit
- `post.load`           — fires per post (pass-by-ref `$post`)
- `post.html`           — filter on rendered post HTML
- `index.before_render` — fires before `index.php` template renders
- `post.before_render`  — fires before `post.php` template renders
- `search.before_render`— fires before `search` plugin renders
- `admin.before_render` — fires before admin dashboard renders
- `admin.authed`        — fires once admin is authenticated (TOTP setup etc.)
- `theme.head`          — pass-by-ref meta array
- `theme.nav`           — emit nav links (rss plugin, i18n switcher)
- `theme.nav.items`     — filter: menu plugin replaces the default Home+categories nav with its own HTML when a menu is saved
- `theme.head.after`    — emit `<head>` content
- `theme.footer`        — emit before `</body>`
- `theme.<page>.after`  — emit at end of named template

### Writing a plugin

```php
// plugins/hello/plugin.json
{
  "name": "hello",
  "type": "plugin",
  "version": "1.0.0",
  "namespace": "Wawoo\\Plugin\\Hello",
  "entry": "Plugin",
  "requires": { "core": ">=1.0.0" },
  "hooks": { "theme.footer": "Greeter" }
}
```

```php
// plugins/hello/src/Plugin.php
namespace Wawoo\Plugin\Hello;
class Plugin { public function boot() {} }
```

```php
// plugins/hello/src/Greeter.php
namespace Wawoo\Plugin\Hello;
class Greeter {
    public function handle(?array $vars) {
        echo '<p style="text-align:center;color:#888">hello from a plugin</p>';
    }
}
```

Then enable it:

```php
// config.local.php
define('ENABLED_PLUGINS', ['seo', 'analytics', 'hello']);
```

## Admin

Admin is a plugin (`admin`). Set `ADMIN_PASSWORD` in `config.local.php`,
then visit `/admin/` (the `admin` plugin must be enabled in
`ENABLED_PLUGINS`, which it is by default). Sessions are PHP-native;
rotate the password to invalidate.

With the `admin-totp` plugin enabled, admin access requires
password + RFC 6238 TOTP (Google Authenticator compatible)
plus backup recovery codes.

Actions:

- Toggle a post's `status` between `published` and `draft`
- Delete a post (removes the whole page-bundle folder)
- View post / category / tag counts
- Enable / disable plugins and switch themes
- Moderate comments (approve / reject)

Plugins that declare `"settings": true` in `plugin.json` and ship a
`src/Settings.php` (class with `handle(?string $root): void`) get a
**Settings** link in the Plugins table; the page is served by the plugin
itself and typically persists runtime config under
`cache/settings/<plugin>.json` (e.g. password-gate and maintenance
store their on/off flag and password there).

## Docker

### Development

```bash
docker compose up -d
# Blog available at http://localhost:8082
```

### Production

```bash
docker compose -f docker-compose.prod.yml up -d
# Binds to 127.0.0.1:8080; put a reverse proxy / TLS terminator in front
```

The Dockerfile uses PHP 8.3 Apache with opcache, intl, zip, and bcmath
extensions enabled. Hardened defaults include `read_only`, `tmpfs`,
`cap_drop`, and `no-new-privileges` in the production override.

## Testing

```bash
./vendor/bin/phpunit
```

256 PHPUnit tests cover:

- **Unit:** `Hook`, `Manifest`, `Registry`, `Frontmatter`, `Cache`, `Posts`,
  `Pages`, `Markdown` (figures), `Theme`, `Autoloader`, `PluginManager`
- **Plugins:** `password-gate`, `i18n`, `admin-totp`, `comments`,
  `seo`/`sitemap-html`/`tag-cloud` configs, `pagination`, `contact`
- **Integration:** end-to-end HTTP flow (home, posts, pages, RSS, sitemap, search, contact, admin)

All tests must pass before committing. See `tests/` for details.

## Encoding

All sources are UTF-8, LF line endings, trailing newline. Verify with:

```bash
php -l includes/core/Bootstrap.php
```

## License

MIT.
