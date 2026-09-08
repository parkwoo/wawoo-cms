---
title: Welcome to wawoo-cms
date: 2026-09-07
category: Meta
tags: [intro, wawoo]
author: wawoo
excerpt: A short tour of what wawoo-cms is and how it differs from a static-site generator.
status: published
cover: sample-diagram.svg
cover_caption: Layered architecture of a wawoo-cms installation.


---
wawoo-cms is a tiny PHP blog kernel: markdown posts, a single theme,
and a plugin API. Drop a markdown file into `posts/`, edit
`config.local.php`, and you're done.

![The stack at a glance](sample-diagram.svg "wawoo-cms: posts → core → themes & plugins")

## What's in the box

- `includes/core/` — bootstrap, hooks, manifests, autoloader, frontmatter, markdown, cache
- `themes/<name>/` — themes ship their own CSS and templates
- `plugins/<name>/` — plugins declare hooks in `plugin.json`
- `posts/*.md` — your content

## Themes and plugins

Themes and plugins are first-class. Each ships a JSON manifest with
name, version, namespace, entry class, and dependencies. The core
resolves dependencies in topological order and boots plugins before
themes.

## Hooks

- `core.boot` — fires after plugins/themes are loaded
- `post.load` — fires when a post is read from disk
- `post.html` — filter on the rendered HTML of a post
- `theme.head` / `theme.head.after` / `theme.footer` — theme-level hooks
- `index.before_render` / `post.before_render` / `search.before_render`

Write a plugin, declare it in `ENABLED_PLUGINS`, and you're shipping.
