---
title: 欢迎使用 wawoo-cms
date: 2026-09-07
category: Meta
tags: [intro, wawoo]
author: wawoo
excerpt: 说明 wawoo-cms 是什么、初次访问者能获得什么，以及内容、主题与插件如何组合。
status: published
cover: sample-diagram.svg
cover_alt: wawoo-cms 结构：上方为 markdown 文章，中间为核心内核，下方为主题与插件
cover_caption: wawoo-cms 结构 — posts → core → themes & plugins
---

## 本文面向谁

如果你正在评估 wawoo-cms，本文将告诉你它是什么、擅长什么，以及内容、
内核、扩展这三层如何组合。阅读前不需要任何背景知识。

## 一句话说明

wawoo-cms 是一个小巧的**文件型 CMS**：markdown 文章、一个启用中的主题、
可选的插件 API。没有数据库、没有构建步骤、没有框架。把 markdown bundle
放进 `posts/`，在 `config.local.php` 里填几项，站点即可运行。

![架构一览](sample-diagram.svg "wawoo-cms: posts → core → themes & plugins")

## 包含什么

- `includes/core/` — 引导、hook、manifest、自动加载、frontmatter、markdown、缓存
- `themes/<name>/` — 主题自带 CSS 与模板
- `plugins/<name>/` — 插件在 `plugin.json` 中声明 hook
- `posts/*.md` — 你的内容

## 边界在哪里

- 面向文本优先的博客与小站；不是页面构建器。
- 运行时需要 PHP（8.3+）；内容本身是纯 markdown。
- 较大的媒体库建议交给对象存储（见 `s3` 插件），而非存入仓库。

## 主题与插件

主题与插件是一等公民。各自携带 JSON manifest，声明 name、version、
namespace、入口类与依赖约束。内核按序解析依赖，先启动插件再启动主题。

## Hooks

- `core.boot` — 插件/主题加载完成后触发
- `post.load` — 从磁盘读取文章时触发
- `post.html` — 对文章渲染后 HTML 的过滤器
- `theme.head` / `theme.head.after` / `theme.footer` — 主题层 hook
- `index.before_render` / `post.before_render` / `search.before_render`

## 下一步

- 运行 `php bin/wawoo doctor` 检查环境
- 用 `php bin/wawoo post:new hello-world` 添加文章
- 用 `php bin/wawoo plugin:link` 启用可选插件
