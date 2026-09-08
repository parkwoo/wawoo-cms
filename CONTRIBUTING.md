# Contributing

Thanks for considering a contribution to wawoo-cms. The project is designed
to be small and predictable; please keep changes in that spirit.

## Ground rules

- PHP 8.3+, no new runtime dependencies, no composer required.
- UTF-8, LF line endings, trailing newline. Minimal comments; code reads
  itself. No emojis in code or docs.
- `php -l` must be clean on every touched PHP file.
- The core does only three things: list posts, render a post, serve fixed
  pages. New *features* belong in plugins, not in core.
- Plugins and themes live in their own repositories
  (`wawoo-plugin-*`, `wawoo-theme-*`). Changes to those belong there; this
  repo only contains the core and its shipped plugins.

## Getting started

```bash
php bin/wawoo doctor          # confirm environment
php -S 127.0.0.1:8000 router.php  # run the site locally
php bin/wawoo list            # see existing posts
```

Read `AGENTS.md` first — it is the on-ramp that describes how content,
plugins, themes and hooks fit together.

## Testing

Run the whole suite before committing:

```bash
phpunit
```

or a single file:

```bash
phpunit tests/Unit/MarkdownTest.php
```

Guidelines:

- Tests live under `tests/` (Unit / Plugin / Integration).
- Optional-plugin tests run from this checkout after the plugin is linked
  via `php bin/wawoo plugin:link` (see AGENTS.md).
- Keep tests random-order safe: reset static caches via reflection and clean
  temp files.
- Handlers that `exit()` are exercised via subprocess
  (`PHP_BINARY` + `shell_exec`).

## Commit conventions

Write concise, conventional-style commit messages:

- `feat(scope): …` — new capability
- `fix(scope): …` — bug fix
- `content: …` — post/page content changes
- `docs: …` — documentation
- `theme(scope): …` — theme changes

Scope examples: `core`, `plugin`, `content`, `theme`, `docs`.

## Plugin compatibility

Optional plugins declare their core requirement in `plugin.json`
(`requires.core`). A release of a plugin that changes that requirement bumps
its manifest version and tag together. Keep plugins backwards compatible
with the latest core tag wherever possible.

## Release process

Releases are git tags (`vX.Y.Z`) plus a GitHub Release for each tag. The
release notes state the compatible core version and any upgrade or rollback
steps. See the main `CHANGELOG.md` for core history.

## Reporting issues

Bugs and feature requests go in the GitHub issue tracker. Security issues
must be reported privately — see `SECURITY.md`.
