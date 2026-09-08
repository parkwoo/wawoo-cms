---
title: "Why wawoo-cms separates plugin dependencies, hooks, and config"
date: 2026-09-07
category: Meta
tags: [meta, architecture, plugins]
author: wawoo
excerpt: A look at the reasoning behind wawoo-cms's three-layer plugin contract: dependencies in plugin.json, hooks as extension points, and runtime config in cache/settings/.
status: published
cover: sample-diagram.svg
cover_alt: Layers of a wawoo-cms site: markdown posts on top, core kernel in the middle, themes and plugins underneath
cover_caption: wawoo-cms architecture — posts → core → themes & plugins
---

Every wawoo-cms plugin ships with three distinct concerns split across
different files. On the surface that looks like extra ceremony. In practice
it keeps the system predictable, auditable and easy to override.

## Dependencies live in `plugin.json`

A plugin declares what it needs in the `requires` block:

```json
{
  "name": "search",
  "requires": {
    "core": ">=1.0.0"
  }
}
```

The core resolves these constraints before boot and orders plugins accordingly.
This avoids circular dependencies and gives you a deterministic startup
sequence — no hidden `require_once` surprises at runtime.

## Hooks are extension points, not configuration

Hooks (`core.boot`, `post.load`, `theme.footer`, …) are how a plugin
*reacts* to the system. They are not the place to store behaviour toggles.
Keeping hooks separate from config means:

- a hook class stays focused on one event
- a settings file can change behaviour without touching event logic
- tests can fire hooks in isolation

## Runtime config lives in `cache/settings/`

Default values belong in `plugin.json` under `config`. Per-site overrides are
written to `cache/settings/<plugin>.json` at runtime. This gives you:

- **Zero code edits** for site-specific behaviour
- **No secret leakage** — `cache/` is git-ignored
- **Atomic writes** use a temporary file followed by rename. Plugins that
  need coordinated read-modify-write operations additionally use `flock`.

## Why this matters

Three files, three concerns. The manifest declares identity and constraints.
The hook class reacts to events. The settings file holds mutable state.
Audit any one without reading the others. Override any one without forking
the rest.

That is the whole trick.
