---
title: "为什么 wawoo-cms 把插件依赖、hooks 和配置分开"
date: 2026-09-07
category: Meta
tags: [meta, architecture, plugins]
author: wawoo
excerpt: 看看 wawoo-cms 三层插件契约的设计思路：依赖放在 plugin.json、hooks 作为扩展点、运行时配置放在 cache/settings/。
status: published
cover: sample-diagram.svg
cover_alt: wawoo-cms 结构：上方为 markdown 文章，中间为核心内核，下方为主题与插件
cover_caption: wawoo-cms 架构 — posts → core → themes & plugins
---

每个 wawoo-cms 插件都将三类职责分散在不同文件中。
表面看这像是多余的仪式感。实际上它让系统保持可预测、可审计、容易
覆盖。

## 依赖声明写在 `plugin.json`

插件在 `requires` 块里声明它需要什么：

```json
{
  "name": "search",
  "requires": {
    "core": ">=1.0.0"
  }
}
```

内核在启动前解析这些约束并按序加载插件。这避免了循环依赖，给你一个
确定的启动顺序 — 不会在运行时冒出隐藏的 `require_once` 惊喜。

## Hooks 是扩展点，不是配置

Hooks（`core.boot`、`post.load`、`theme.footer`、…）是插件 *响应*
系统事件的方式。它们不是存放行为开关的地方。把 hooks 和配置分开意味着：

- hook 类只关注单一事件
- 配置文件可以在不碰事件逻辑的情况下改变行为
- 测试可以隔离地触发 hooks

## 运行时配置放在 `cache/settings/`

默认值属于 `plugin.json` 里的 `config`。站点级覆盖在运行时写入
`cache/settings/<plugin>.json`。这给你带来：

- **零代码修改**即可调整站点行为
- **不泄露密钥** — `cache/` 不会被提交
- **原子写入**：先写临时文件再 rename。需要协调读-改-写操作的插件
  额外使用 `flock`。

## 为什么这很重要

三个文件，三个关注点。manifest 声明身份和约束。hook 类响应事件。
settings 文件持有可变状态。你可以审计其中一个而不必读另外两个。可以
覆盖其中一个而不必 fork 其余。

这就是全部技巧。
