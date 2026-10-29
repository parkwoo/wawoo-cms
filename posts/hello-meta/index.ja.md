---
title: wawoo-cms がプラグインの依存関係、フック、設定を分離する理由
date: 2026-09-07
category: Meta
tags: [meta, architecture, plugins]
author: wawoo
excerpt: wawoo-cms の 3 層プラグイン契約の背景にある設計思想：依存関係は plugin.json、フックは拡張点、ランタイム設定は cache/settings/ に。
status: published
cover: cover.svg
cover_alt: wawoo-cms の構成 — 上に markdown の記事、中央にコア、下にテーマとプラグイン
cover_caption: wawoo-cms アーキテクチャ — posts → core → themes & plugins
---

すべての wawoo-cms プラグインは 3 つの異なる関心事を持ち、別々のファイル
に分かれています。一見すると無駄な儀式のように見えます。実際には、システム
を予測可能に、監査可能に、上書きしやすく保ちます。

## 依存関係は `plugin.json` に置く

プラグインは `requires` ブロックで必要なものを宣言します：

```json
{
  "name": "search",
  "requires": {
    "core": ">=1.0.0"
  }
}
```

コアは起動前に制約を解析し、順序どおりにプラグインを起動します。これにより
循環依存を避け、決定論的な起動順序が得られます — 実行時に隠れた
`require_once` のサプライズはありません。

## フックは拡張点であって設定ではない

フック（`core.boot`、`post.load`、`theme.footer`、…）はプラグインが
システムイベントに *反応* する方法です。動作スイッチを置く場所ではあり
ません。フックと設定を分離することで：

- フッククラスは単一イベントに集中できる
- 設定ファイルはイベントロジックに触れずに振る舞いを変更できる
- テストはフックを単体で発火させて検証できる

## ランタイム設定は `cache/settings/` に置く

デフォルト値は `plugin.json` の `config` に属する。サイト固有の上書きは
実行時に `cache/settings/<plugin>.json` に書き込まれます。これにより：

- **コード編集不要**でサイトの挙動を調整できる
- **秘密情報の漏洩を防ぐ** — `cache/` は git に含まれない
- **原子的書き込み**：一時ファイルへの書き込みと rename を基本とする。
  協調的な読み取り・変更・書き戻し操作が必要なプラグインは追加で `flock` を使用する。

## なぜこれが重要か

3 つのファイル、3 つの関心事。manifest は同一性と制約を宣言する。フック
クラスはイベントに反応する。settings ファイルは可変状態を保持する。一方
を監査するために他方を読む必要はない。一方を上書きするために他方を fork
する必要はない。

それがすべての仕掛けです。
