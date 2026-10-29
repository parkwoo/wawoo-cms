---
title: "One Markdown file, everything the page needs"
date: 2026-09-07
category: Meta
tags: [markdown, writing, guide]
author: wawoo
excerpt: Headings, tables, code, quotes and figures — a quick tour of what a single markdown bundle renders in wawoo-cms, with no plugins required.
status: published
cover: cover.svg
cover_alt: A graph of connected nodes representing plugin hooks
cover_caption: Plugins attach to named hooks; the kernel decides the order.
---

This whole article is **one file**: `posts/hello-meta/index.md`. What you see
below is what the built-in markdown renderer produces — no page builder, no
shortcodes, no plugins.

## Text is text

Plain paragraphs, *emphasis*, **strong emphasis**, `inline code`, and
[links](/about) all work the way you expect. Try opening the source and editing
it; the page updates on the next request.

## Lists keep structure

- Unordered lists for related points
- Nested lists when you need them
  - like this
  - and this
- Ordered lists for steps

1. Write markdown
2. Save the file
3. Refresh the page

## Tables are just pipes

| Element | Markdown | Rendered |
| --- | --- | --- |
| Heading | `## Title` | A section heading |
| Quote | `> text` | A pull quote |
| Code | fenced block | Syntax-ready `<pre>` |

## Quotes stand apart

> Good tools get out of the way. A markdown bundle is the smallest possible
> content model that still supports images, metadata and reviews.

## Code is first-class

```php
<?php
// Frontmatter becomes structured data; the body is rendered by the kernel.
$post = Posts::bySlug('hello-meta');
echo htmlspecialchars($post['title']);
```

## Figures get captions

Give an image a `title` and it becomes a figure with a caption — useful for
diagrams and screenshots.

![A network of hooks connecting plugins to the kernel](cover.svg "Hooks are the extension points between the kernel and plugins")

---

That is the whole toolkit. Everything else — search, feeds, comments,
syntax highlighting — is an opt-in plugin you add when you need it.
