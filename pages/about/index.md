---
title: About
excerpt: What wawoo-cms is, the principles behind it, and how far the kernel goes before you reach for a plugin.
order: 10
cover: cover.svg
cover_alt: Concentric rings forming a minimal site mark
cover_caption: A small kernel, extended outward by themes and plugins.
author: wawoo
---

## A CMS you can read

wawoo-cms is a **file-based CMS designed to be driven by AI agents and plain-text
lovers**. Content is markdown on disk, the runtime is plain PHP 8.3, and the
extension surface is a small, documented set of hooks.

This site is its own dogfood: every article and page is a folder in this
repository, reviewed like code.

## The three layers

| Layer | Lives in | Responsibility |
| --- | --- | --- |
| Content | `posts/`, `pages/` | Markdown bundles + assets |
| Kernel | `includes/core/` | Routing, frontmatter, markdown, cache, hooks |
| Extensions | `themes/`, `plugins/` | Look and optional features |

## Principles

- **Small core.** If a feature is optional, it is a plugin — not the kernel.
- **Plain files.** No database, no build step; everything is diffable.
- **Agent-friendly.** Clear contracts, machine-readable manifests, CLI tooling.
- **Opt-in complexity.** Start with markdown; add search, feeds or comments
  only when the site needs them.

## Extend it

Themes and plugins are repositories with a JSON manifest. A plugin declares its
dependencies, binds to hooks, and keeps runtime config in `cache/settings/`.
That is the whole contract.

Want to see it in practice? Read [the architecture note](/post/hello-meta), or
browse the archive.
