---
title: "Welcome to wawoo-cms"
date: 2026-09-08
category: News
tags: [intro, wawoo]
author: wawoo
excerpt: A file-based CMS for people who think in plain text. No database, no build step — just markdown bundles, a tiny kernel, and plugins that opt in.
status: published
cover: cover.svg
cover_alt: Layered paper sheets representing markdown content, the kernel and extensions
cover_caption: Content is just folders and files — the kernel stays small, and everything else is a plugin.
---

You are reading a site powered by **wawoo-cms** — a lightweight, file-based CMS
built for people who like to *think in plain text*. There is no database and no
build step: a post is a folder, an image lives next to it, and the whole thing
runs on PHP 8.3.

## Why file-based?

Because content should outlive the tooling. Markdown bundles are portable,
diffable, and reviewable in a pull request — the same workflow you already use
for code.

> “The best content model is one you can read in a text editor and review like a
> code change.”

| You get | You avoid |
| --- | --- |
| Markdown + frontmatter on disk | A database to migrate |
| A tiny hook/plugin kernel | A monolithic framework |
| Themes and plugins as repos | A vendor lock-in |

## It runs on a very small kernel

Everything beyond routing, frontmatter, markdown and caching is opt-in. That
keeps the surface tiny — and easy for both humans and agents to reason about.

```php
// A post is literally a folder with an index.md
posts/
└── welcome/
    ├── index.md      ← frontmatter + markdown
    └── cover.svg     ← assets live beside the content
```

## Themes and plugins are first-class

Each theme and plugin ships a small JSON manifest with a name, version and
namespace. The kernel resolves dependencies, boots plugins first, then the
theme — so extensions can shape what the theme renders.

1. **Themes** own the look (`themes/<name>/`).
2. **Plugins** own the features (`plugins/<name>/`).
3. **Content** stays plain markdown (`posts/`, `pages/`).

![The layered architecture: content on top, kernel in the middle, extensions below](cover.svg "Content → kernel → themes & plugins")

## Where to start

- Read [the architecture note](/post/hello-meta) for the plugin contract.
- Browse the archive, or try a search.
- Everything here is markdown — open the source and edit it.
