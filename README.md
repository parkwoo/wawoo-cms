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

**Version:** 2.0.0 · **Tests:** 187 PHPUnit · **PHP files:** 84+ · **Lines:** ~9,400

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
│   └── default/               GitHub-flavoured light/dark theme
├── plugins/
│   ├── seo/                   meta + /sitemap.xml + /robots.txt         [core]
│   ├── menu/                  navigation menu editor                    [core]
│   └── contact/               /contact inquiry form (CSRF + inbox)      [core]
│                              (admin, rss, search, code, mermaid,
│                               translation, ... live in
│                               wawoo-cms-marketplace and are linked in
│                               with bin/wawoo plugin:link)
├── tests/                     PHPUnit suite (187 tests)
├── Dockerfile                 PHP 8.3 Apache, opcache tuned
├── docker-compose.yml         development compose
├── docker-compose.dev.yml     writable dev compose (admin-in-container)
├── docker-compose.prod.yml    production compose
├── .htaccess                  Apache rewrite + security headers
└── cache/                     generated cache
```

This repo ships the core only. Optional plugins and the `clay` theme are
separate repositories (`wawoo-plugin-*`, `wawoo-theme-clay`) that are
installed into `plugins/` / `themes/` with
`php bin/wawoo plugin:link` / `theme:link` — see the
[Plugins](#plugins) section.

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

Multi-language support ships as optional plugins (see
[Optional plugins](#optional-plugins)), both off by default:

- **Content** — `wawoo-plugin-mlang` serves per-language bundles
  (`posts/<slug>/index.ja.md`, …) at `/ja/post/<slug>` (fixed pages at
  `/ja/<slug>`), falling back to the default language when a translation
  is missing.
- **Interface** — `wawoo-plugin-i18n` translates theme nav, buttons and
  the admin console into `en`/`ja`/`zh`; templates call `t('nav.home',
  'Home')`, which returns the English default when the plugin is off.

Install via `php bin/wawoo plugin:link` and enable in `config.local.php`
or `/admin → Plugins`.

## Publishing workflow

Posts have a three-state lifecycle. The `publish` plugin (optional —
ships as `wawoo-plugin-publish`, enabled in `/admin → Plugins`) makes the
states usable from the admin console and adds time-based publishing; the
states themselves live in the frontmatter and always apply:

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

The `s3` plugin (optional, ships as `wawoo-plugin-s3`) moves post/page
media to an S3-compatible bucket and serves it from there. It works with
AWS S3, Cloudflare R2, MinIO and any S3 API implementation — the SigV4
client is pure PHP (stream sockets), so no SDK, composer or curl
extension is needed.
Requests are signed with your Access Key / Secret Key and connect to the
**verified public IP** with TLS certificate/SNI verification against the
configured hostname, so DNS rebinding cannot redirect traffic to an
internal address.

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
| `/rss`               | `rss` plugin (optional)          |
| `/sitemap.xml`       | `seo` plugin (XML sitemap)       |
| `/robots.txt`        | `seo` plugin                     |
| `/admin`             | `admin` plugin (optional)        |
| `/themes/...`        | static asset                     |
| `/posts/...`         | static asset                     |
| `/pages/...`         | static asset (page bundle images)|
| anything else        | `dispatch.php` → 404             |

Plugins whose pages are not enabled simply don't answer — the URL then
falls through to the 404 template.

Nginx example:

```nginx
server {
    # ...
    location /blog {
        root /var/www;
        # deny non-public data: cache/includes/plugins, markdown, json
        location ~ ^/blog/(cache|includes|plugins)/ { return 404; }
        location ~* \.(md|markdown|json|log|lock|sql|sh)$ { return 404; }
        location ~ ^/blog/(\.env|config\.local\.php|composer\.(json|lock)|README\.md|CHANGELOG\.md|VERSION)$ { return 404; }
        try_files $uri $uri/ /blog/router.php?$args;
        location ~ \.php$ { fastcgi_pass unix:/run/php/php-fpm.sock; ... }
    }
}
```

Serving static files safely: the web server must never serve `cache/`,
`includes/`, `plugins/`, `*.md` or `*.json` verbatim — cache files hold
draft lists, form/comment inboxes and plugin settings, and markdown
includes unpublished drafts. The shipped `router.php` already restricts
static passthrough to theme assets, uploads and post/page bundle media
(via `includes/core/PublicAssets.php`), and the shipped `.htaccess`
enforces the same denies for Apache. Any other existing file is left to
dispatch, which returns 404.

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

| Theme | Description | Repository |
|-------|-------------|------------|
| `default` | GitHub-flavoured light/dark mode styling | Ships in this repo |
| `clay` | Claymorphism style ported from hirameki-loto's blog module | [parkwoo/wawoo-theme-clay](https://github.com/parkwoo/wawoo-theme-clay) |

The `clay` theme lives in its own repository; install it with
`php bin/wawoo theme:link /path/to/wawoo-theme-clay` (add `--copy` for
Docker builds) before enabling it in `ENABLED_THEMES`.

### Theme hooks

- `theme.head`       — pass-by-ref array `['title','description','type','canonical']`
- `theme.head.after` — emit extra `<head>` content (analytics scripts, i18n lang switcher, …)
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

### Core plugins (shipped in this repo)

Three plugins ship under `plugins/` and are enabled by default:

| Plugin    | Description                                                                  |
|-----------|------------------------------------------------------------------------------|
| `seo`     | Meta trimming + `/sitemap.xml` + `/robots.txt`                               |
| `menu`    | Navigation menu editor. Configure via `bin/wawoo settings:set menu` (or the admin Settings page when the admin plugin is installed); the menu renders via the `theme.nav.items` filter and replaces the theme's default nav only when a menu is saved. |
| `contact` | `/contact` inquiry form: CSRF, session + IP rate limit, honeypot, configurable labels, inbox archive under `cache/contact/`, optional `mail()` delivery. |

`admin`, `rss`, `search`, `code`, `mermaid`, and `translation` are optional
items in **`wawoo-cms-marketplace`** (the admin console ships its own theme).
Backwards-compat note: `admin`/`rss`/`search` used to ship in core.

### Optional plugins

Everything else is optional: `admin`, `rss`, `search`, `code`, `mermaid`, and
`translation` live in **`wawoo-cms-marketplace`**; the rest are standalone
`wawoo-plugin-<name>` repos. Install from the catalog with
`bin/wawoo plugin:install <name>` (or `plugin:link` a checkout), then enable
with `bin/wawoo enable <name>` (or add it to `ENABLED_PLUGINS`):

| Plugin | Description | Repository |
|--------|-------------|------------|
| `wawoo-plugin-pagination` | Numbered pagination (1 2 3 … with ellipsis) | [parkwoo/wawoo-plugin-pagination](https://github.com/parkwoo/wawoo-plugin-pagination) |
| `wawoo-plugin-analytics` | Google Analytics injector | [parkwoo/wawoo-plugin-analytics](https://github.com/parkwoo/wawoo-plugin-analytics) |
| `wawoo-plugin-password-gate` | Site-wide password gate (rate-limited, HMAC cookie) | [parkwoo/wawoo-plugin-password-gate](https://github.com/parkwoo/wawoo-plugin-password-gate) |
| `wawoo-plugin-maintenance` | Maintenance mode: 503 + `Retry-After` page | [parkwoo/wawoo-plugin-maintenance](https://github.com/parkwoo/wawoo-plugin-maintenance) |
| `wawoo-plugin-comments` | Comment form + admin moderation queue | [parkwoo/wawoo-plugin-comments](https://github.com/parkwoo/wawoo-plugin-comments) |
| `wawoo-plugin-tag-cloud` | Font-sized tag cloud on the index | [parkwoo/wawoo-plugin-tag-cloud](https://github.com/parkwoo/wawoo-plugin-tag-cloud) |
| `wawoo-plugin-sitemap-html` | Human-readable HTML sitemap at `/sitemap-page` | [parkwoo/wawoo-plugin-sitemap-html](https://github.com/parkwoo/wawoo-plugin-sitemap-html) |
| `wawoo-plugin-i18n` | UI translation (en/ja/zh) via a `t()` template helper | [parkwoo/wawoo-plugin-i18n](https://github.com/parkwoo/wawoo-plugin-i18n) |
| `wawoo-plugin-mlang` | Multi-language content (`/ja/post/<slug>`, …) | [parkwoo/wawoo-plugin-mlang](https://github.com/parkwoo/wawoo-plugin-mlang) |
| `wawoo-plugin-admin-totp` | TOTP second factor for `/admin` | [parkwoo/wawoo-plugin-admin-totp](https://github.com/parkwoo/wawoo-plugin-admin-totp) |
| `wawoo-plugin-publish` | Publish workflow + scheduled publishing | [parkwoo/wawoo-plugin-publish](https://github.com/parkwoo/wawoo-plugin-publish) |
| `wawoo-plugin-s3` | S3-compatible object storage sync | [parkwoo/wawoo-plugin-s3](https://github.com/parkwoo/wawoo-plugin-s3) |
| `wawoo-plugin-mermaid` | Renders Mermaid fenced diagrams to SVG | [parkwoo/wawoo-plugin-mermaid](https://github.com/parkwoo/wawoo-plugin-mermaid) |
| `wawoo-plugin-code` | Code-block language labels, copy button & syntax highlighting | [parkwoo/wawoo-plugin-code](https://github.com/parkwoo/wawoo-plugin-code) |

Install any of them from a wawoo-cms checkout:

```bash
cd /path/to
git clone https://github.com/parkwoo/wawoo-cms
git clone https://github.com/parkwoo/wawoo-plugin-comments
git clone https://github.com/parkwoo/wawoo-plugin-s3

cd wawoo-cms
php bin/wawoo plugin:link ../wawoo-plugin-comments           # symlink
php bin/wawoo plugin:link ../wawoo-plugin-s3 --copy          # copy (Docker)
```

Themes work the same way: `default` ships in this repo, and the `clay`
theme is installed with
`php bin/wawoo theme:link /path/to/wawoo-theme-clay` (add `--copy` for
Docker), then enabled via `ENABLED_THEMES`.

### Version pinning & updates

Each optional plugin repo (`wawoo-plugin-*`) and the `clay` theme repo
(`wawoo-theme-clay`) is versioned independently (each tagged at its own
version), and its manifest `requires` states the core version it is built
against (`core >= 1.0.0`).

To pin a deployment, choose a wawoo-cms tag and a matching tag for each
optional plugin you use, and record the combination. The currently
published versions are:

| Repo | Tag |
|------|-----|
| wawoo-cms | v1.0.0 |
| wawoo-plugin-admin-totp | v1.0.0 |
| wawoo-plugin-analytics | v1.0.0 |
| wawoo-plugin-code | v1.3.1 |
| wawoo-plugin-comments | v1.0.0 |
| wawoo-plugin-i18n | v1.0.0 |
| wawoo-plugin-maintenance | v1.0.0 |
| wawoo-plugin-mermaid | v1.1.3 |
| wawoo-plugin-mlang | v1.0.0 |
| wawoo-plugin-pagination | v1.0.0 |
| wawoo-plugin-password-gate | v1.0.0 |
| wawoo-plugin-publish | v1.0.0 |
| wawoo-plugin-s3 | v1.0.0 |
| wawoo-plugin-sitemap-html | v1.0.0 |
| wawoo-plugin-tag-cloud | v1.0.0 |
| wawoo-theme-clay | v1.0.0 |

When building Docker images, install with `--copy` inside the build so
the artifact is self-contained — symlinks would dangle once the source
checkout is gone:

```bash
php bin/wawoo plugin:link /path/to/wawoo-plugin-<name> --copy
```

#### Complete pinned install example (core + comments + i18n + s3 + clay)

A fully tag-pinned install looks like this:

```bash
git clone --branch v1.0.0 https://github.com/parkwoo/wawoo-cms     wawoo-cms
git clone --branch v1.0.0 https://github.com/parkwoo/wawoo-plugin-comments wawoo-plugin-comments
git clone --branch v1.0.0 https://github.com/parkwoo/wawoo-plugin-i18n wawoo-plugin-i18n
git clone --branch v1.0.0 https://github.com/parkwoo/wawoo-plugin-s3 wawoo-plugin-s3
git clone --branch v1.0.0 https://github.com/parkwoo/wawoo-theme-clay wawoo-theme-clay

cd wawoo-cms
php bin/wawoo plugin:link ../wawoo-plugin-comments --copy
php bin/wawoo plugin:link ../wawoo-plugin-i18n --copy
php bin/wawoo plugin:link ../wawoo-plugin-s3 --copy
php bin/wawoo theme:link ../wawoo-theme-clay --copy
phpunit
```

Every repo above is cloned on its `v1.0.0` tag, so the combination is
fully repeatable. `--copy` keeps the core checkout self-contained (also
what a Docker build should do). `phpunit` runs the core suite plus the
linked plugin tests (`tests/Plugin/Comments*Test.php`, `I18n*Test.php`,
`S3*Test.php`). Note `I18n*Test.php` includes a clay-theme rendering case,
so install `wawoo-theme-clay` too when running the i18n tests.

Update & rollback (recommended manual workflow until a lockfile/manager
exists): before updating a plugin, back up the cache state volume — it
holds plugin settings, comments/contact inbox and rate-limit state. After
linking the new tag, run `phpunit tests/Plugin/<Name>*Test.php` from a
wawoo-cms checkout to verify. To roll back, check out the previous tag,
re-link it, and restore the cache-volume backup.

### Available hooks

- `core.boot`           — after plugins/themes are loaded
- `core.request`        — on every request; plugin page handlers inspect the URL and short-circuit
- `post.load`           — fires per post (pass-by-ref `$post`)
- `post.html`           — filter on rendered post HTML
- `index.before_render` — fires before `index.php` template renders
- `post.before_render`  — fires before `post.php` template renders
- `search.before_render`— fires before `search` plugin renders
- `admin.before_render` — fires before admin dashboard renders
- `admin.authed`        — fires once admin is authenticated
- `theme.head`          — pass-by-ref meta array
- `theme.nav`           — emit nav links (rss plugin; i18n switcher when installed)
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
define('ENABLED_PLUGINS', ['rss', 'seo', 'hello']);
```

## Admin

Admin is a plugin (`admin`). Recommended: set
`$GLOBALS['WAWOO_ADMIN']['password_hash']` to a bcrypt hash in
`config.local.php` (config.local.example.php shows how to generate one),
then visit `/admin/` (the `admin` plugin must be enabled in
`ENABLED_PLUGINS`, which it is by default). Plaintext `ADMIN_PASSWORD`
remains a legacy fallback. Sessions are PHP-native; rotate the password
to invalidate.

With the optional `wawoo-plugin-admin-totp` linked and enabled, admin
access requires password + RFC 6238 TOTP (Google Authenticator
compatible) plus backup recovery codes.

Actions:

- Toggle a post's `status` between `published` and `draft`
- Delete a post (removes the whole page-bundle folder)
- View post / category / tag counts
- Enable / disable plugins and switch themes
- Moderate comments when the optional `comments` plugin is installed
  (approve / reject; see [Optional plugins](#optional-plugins))

Plugins that declare `"settings": true` in `plugin.json` and ship a
`src/Settings.php` (class with `handle(?string $root): void`) get a
**Settings** link in the Plugins table; the page is served by the plugin
itself and typically persists runtime config under
`cache/settings/<plugin>.json` (e.g. an optional `password-gate` stores
its on/off flag and password there).

## Docker

### Local edit & preview (read-only mounts)

The default `docker compose up -d` serves the blog at
http://localhost:8082. It mounts `posts/`, `pages/`, `themes/`,
`plugins/`, `uploads/` and `config.local.php` read-only (`:ro`) from the
host, keeping only the cache on a named volume — ideal for editing
markdown on the host and previewing it. The admin console cannot write
in this mode: the mounts are `:ro`, so publishing/deleting posts,
changing settings or receiving comments through the container fails.

### Writable dev / admin-in-container

To run the admin console with writable content, use the dev override:

```bash
docker compose -f docker-compose.dev.yml up -d
# Blog + admin console available at http://localhost:8083
```

It builds the same Dockerfile as a `wawoo-dev` service and mounts the
same directories without the `:ro` flags (cache stays on the
`wawoo_dev_cache` named volume), so the admin console can publish/delete
posts, change settings, moderate comments, etc. This mirrors the
production volume layout but without the read-only hardening — never use
it in production.

### Production

```bash
# One-shot chown of the named volumes (once per fresh volume set):
docker compose -f docker-compose.prod.yml run --rm init-volumes
docker compose -f docker-compose.prod.yml up -d
# Binds to 127.0.0.1:8080; put a reverse proxy / TLS terminator in front
```

The production override is hardened (`read_only`, `tmpfs`, `cap_drop`,
`no-new-privileges`). The site's mutable state lives in four named
volumes: `wawoo_posts` (markdown bundles), `wawoo_pages` (fixed pages),
`wawoo_uploads` (uploaded media) and `wawoo_state` (plugin/theme state,
contact inbox, comments, caches). The one-shot `init-volumes` service
chowns those volumes to `www-data` before the main service starts; it
only needs to run once per fresh volume set — re-running it is harmless.

### Backup & restore

The four volumes above are together the site's source of truth (content
bundles, uploads, plugin/theme state, comments/contact inbox, caches), so
snapshot them together. For each volume:

```bash
docker run --rm -v wawoo_posts:/data -v /path/to/backup:/backup alpine tar czf /backup/wawoo_posts.tgz -C /data .
docker run --rm -v wawoo_pages:/data -v /path/to/backup:/backup alpine tar czf /backup/wawoo_pages.tgz -C /data .
docker run --rm -v wawoo_uploads:/data -v /path/to/backup:/backup alpine tar czf /backup/wawoo_uploads.tgz -C /data .
docker run --rm -v wawoo_state:/data -v /path/to/backup:/backup alpine tar czf /backup/wawoo_state.tgz -C /data .
```

To restore, extract each archive back into the same volume before
starting the stack, then run `init-volumes` again.

The Dockerfile uses PHP 8.3 Apache with opcache, intl, zip, and bcmath
extensions enabled.

## Testing

PHPUnit 9.6 is expected on your `PATH` (this repo has no `vendor/`
directory, so there is no `./vendor/bin/phpunit`); CI downloads PHPUnit
9.6 as a phar and runs it the same way. Run the core suite:

```bash
phpunit
```

The suite is 187 tests. A handful of E2E/subprocess tests skip
automatically when sockets or curl are unavailable, so the reported
assertion totals vary between environments. The suite covers:

- **Unit:** `Hook`, `Manifest`, `Registry`, `Frontmatter`, `Cache`,
  `Posts`, `Pages`, `Markdown` (figures), `Theme`, `Autoloader`,
  `PluginManager`, post status workflow
- **Plugins (core):** `admin`, `rss`, `seo` (`/sitemap.xml`), `menu`,
  `contact` (CSRF + rate limiting)
- **Integration/Security:** end-to-end HTTP flow (home, posts, pages,
  RSS, sitemap, search, contact, admin) + admin CSRF/POST enforcement,
  session rotation, login rate limiting

Optional-plugin tests run from within a wawoo-cms checkout after
`plugin:link` has linked the plugin repo, e.g.
`phpunit tests/Plugin/<Name>*Test.php` (they live here once the plugin's
`tests/Plugin/` directory is in place). All tests must pass before
committing. See `tests/` for details.

## Encoding

All sources are UTF-8, LF line endings, trailing newline. Verify with:

```bash
php -l includes/core/Bootstrap.php
```

## License

Released under the [MIT License](LICENSE).

## Contributing & security

- [CONTRIBUTING.md](CONTRIBUTING.md) — development workflow, testing and
  commit conventions.
- [SECURITY.md](SECURITY.md) — how to report a vulnerability privately.
- [CHANGELOG.md](CHANGELOG.md) — version history for the core.
