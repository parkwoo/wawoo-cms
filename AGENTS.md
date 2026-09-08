# AGENTS.md — wawoo-cms for AI agents

wawoo-cms is a **file-based CMS designed to be driven by AI agents**. It is
plain PHP 8.3 with markdown content, no database, no framework, and a
small hook/plugin system. Everything beyond the minimal core ships as a
plugin and is optional. This document is the on-ramp: read it before
touching the codebase.

## 30-second mental model

- Content = **markdown bundles**: `posts/<slug>/index.md` (blog posts),
  `pages/<slug>/index.md` (fixed pages like /about). Images live next to
  the markdown in the same folder.
- Code = **plugins & themes**: each is a directory with a `plugin.json` /
  `theme.json` manifest and `src/` classes. Plugins attach behaviour by
  declaring hooks in the manifest.
- Config = one PHP file: `config.local.php` overrides
  `config.example.php` (constants + `$GLOBALS`).
- Everything runs on the file system: no DB migrations, no build step.

## Directory map

```
bin/wawoo          CLI (create posts/pages/plugins, clear cache, doctor)
index.php          home / list / filter        (core)
post.php           single post                 (core)
page.php           fixed page at /<slug>       (core)
router.php         core routing + static passthrough
dispatch.php       plugin-page fallback (fires core.request → 404)
includes/core/     Bootstrap, Hook, Registry, Manifest, Theme, Autoloader,
                   Frontmatter, Markdown, Posts, Pages, Cache, Url, PluginManager
includes/Parsedown.php   vendored markdown parser
posts/  pages/  uploads/  cache/  tests/
themes/            only `default/` ships here. Other themes (e.g. `clay` =
                  `wawoo-theme-clay`) are standalone repos linked via
                  `bin/wawoo theme:link`
plugins/           the six core plugins ship here (`admin`, `rss`, `search`,
                  `seo`, `menu`, `contact`). Optional plugins
                  (`wawoo-plugin-*`, e.g. `/path/to/wawoo-plugin-comments`)
                  are standalone repos linked via `bin/wawoo plugin:link`
                  (`--copy` instead of symlink for Docker image builds)
```

## Optional plugins & themes

This repo ships the core only. Optional plugins and the `clay` theme live
in their own repositories and are linked in before use:

```bash
cd /path/to/wawoo-cms
php bin/wawoo plugin:link /path/to/wawoo-plugin-comments   # symlink
php bin/wawoo plugin:link /path/to/wawoo-plugin-s3 --copy # copy (Docker)
php bin/wawoo theme:link /path/to/wawoo-theme-clay        # themes too
```

Once linked they behave exactly like shipped plugins/themes; enable them
via `ENABLED_PLUGINS` / `ENABLED_THEMES` in `config.local.php` (or
`/admin → Plugins`).

## Quick start (as an agent)

```bash
php bin/wawoo doctor              # confirm environment
php -S 127.0.0.1:8000 router.php  # run the site
php bin/wawoo list                # see existing posts
```

Production note: route all requests through `router.php` (nginx
`try_files … /router.php` or the shipped `.htaccess`).

## Creating content

```bash
php bin/wawoo post:new hello-world --title "Hello" --category Meta --tags intro,guide
php bin/wawoo page:new about --title "About"
```

Frontmatter (YAML between `---`): `title`, `date`, `category` (posts),
`tags` (posts), `excerpt`, `status` (`published`|`draft`), `cover`,
`cover_caption`, `author`, `order` (pages). Relative image paths resolve
against the bundle folder (`posts/<slug>/`).

After editing markdown, clear the file cache so changes appear
immediately: `php bin/wawoo cache:clear`.

## URL → handler map

- `/` `/post/<slug>` `/cat/x` `/tag/x` → core (`index.php` / `post.php`)
- `/<slug>` → fixed page if `pages/<slug>` exists
- everything else → `dispatch.php` → plugins claim paths via
  `core.request`, else theme 404 template

## Plugin architecture

A plugin is a folder `plugins/<name>/` with:

```json
// plugin.json
{
  "name": "my-plugin",
  "namespace": "Wawoo\\Plugin\\MyPlugin",
  "entry": "Plugin",
  "hooks": { "theme.footer": "Greeter" },
  "settings": true,            // optional: admin Settings link + src/Settings.php
  "config": { "key": "value" } // defaults; runtime overrides allowed
}
```

Hook classes live in `src/`, autoloaded by PSR-4 prefix from the
namespace. A hook entry maps `hookName → className`; Registry instantiates
the class and calls `->handle(...)`.

Key hooks:

- `core.boot` — after plugins/themes load (`handle()`)
- `core.request` — every request; plugin *pages* inspect the URL and
  `exit` when they claim it
- `theme.head`, `theme.head.after`, `theme.nav`, `theme.nav.items`,
  `theme.footer`, `theme.<page>.after`
- `post.load` / `page.load` (by-ref `$post`/`$page`), `post.html` filter
- `index.before_render`, `post.before_render`, `search.before_render`,
  `admin.before_render`, `admin.authed`

Conventions:

- Filter hooks return a value (e.g. `post.html`, `theme.nav.items`);
  event hooks receive `&$payload`.
- A plugin that owns a URL must render + `exit` from its `core.request`
  handler (see `plugins/contact/src/Page.php` for the pattern).
- Runtime per-site state lives in `cache/settings/<plugin>.json`
  (writable; `cache/*.json` clears on cache:clear but the `settings/`
  subdirectory is preserved). Write via tmp+rename with flock.
- Config merge priority everywhere:
  `plugin.json config` → `$GLOBALS['WAWOO_<NAME>']` → runtime settings file.

Scaffold: `php bin/wawoo make:plugin name`, `php bin/wawoo make:theme name`.

Optional plugins (the `wawoo-plugin-*` repos) follow this exact contract —
the same `plugin.json` manifest fields and hooks — but live outside this
repo until `plugin:link`ed into `plugins/`; after linking they are
indistinguishable from the shipped core plugins. Their PHPUnit tests live
under `tests/Plugin/` and run from within this repo's checkout.

## Theme architecture

`themes/<name>/` with `theme.json` + `templates/`
(`_header.php`, `_footer.php`, `index.php`, `post.php`, `page.php`,
`search.php`, `404.php`, `admin/dashboard.php`) + `assets/`. Themes are
declarative display layers; logic goes in plugins. The shipped `default`
theme and optional themes (e.g. `clay`, linked from `wawoo-theme-clay`
via `theme:link`) all use the same hook points.

## Settings plugin contract

A plugin declaring `"settings": true` must ship `src/Settings.php` with
`public function handle(?string $root): void` that echoes a self-contained
HTML page and exits. The admin console shows a Settings link automatically
(see `plugins/contact/src/Settings.php`).

## Testing

- Tests live under `tests/` (Unit / Plugin / Integration). They use the
  real repo filesystem: `tests/bootstrap.php` defines constants pointing at
  `posts/`, `themes/`, `plugins/` plus an isolated `CACHE_DIR`.
- Handlers that `exit()` must be exercised via subprocess
  (`PHP_BINARY` + `shell_exec`, see `tests/Plugin/AdminPluginTest.php`)
  or by extracting pure static methods and unit-testing those.
- Run everything: `phpunit` — the 176-test core suite; a few E2E/
  subprocess tests skip automatically when sockets or curl are
  unavailable, so assertion totals vary. Or run one file:
  `phpunit tests/Unit/MarkdownTest.php`. Optional-plugin tests run the
  same way from this repo once the plugin is `plugin:link`ed (they land
  in `tests/Plugin/`). Keep random-order safe: reset
  static caches (Hook/Config/etc.) via reflection and clean temp files.

## Docker & ops

README's `## Docker` section is the ops reference: the default
`docker-compose.yml` is a read-only host-edit/preview mode (admin cannot
write), `docker-compose.dev.yml` is the writable variant for running the
admin console, and production needs the `init-volumes` one-shot before
`docker compose up -d` plus snapshotting the four named volumes together
for backup/restore. Plugin & theme version pinning and the manual
update/rollback workflow are documented under "Version pinning & updates"
in the README's `## Plugins` section.

## Conventions (hard rules)

- PHP 8.3, no new runtime dependencies, no composer required.
- UTF-8, LF line endings, trailing newline. Minimal comments; code reads
  itself. No emojis in code/docs.
- `php -l` clean on every touched file.
- Never commit `config.local.php`, `cache/`, `tests/_tmp/`,
  `plugins/*/data/` (see `.gitignore`).
- Core classes are `require_once`d in `Bootstrap::init`; plugin/theme
  namespaces are autoloaded via `Autoloader` prefix map.
- New top-level pages that are "features" belong in **plugins** (see
  `dispatch.php` pattern); only content rendering stays in core.
