# Knowledge base

Source of truth for RAG content is Markdown under `data/knowledge/`.

## Layout

```text
data/knowledge/
  base.md          # tracked seed document
  *.md             # additional docs (gitignored by default except base.md)
```

Only `*.md` files directly in that directory are loaded (`glob …/*.md`). Subdirectories are not scanned.

## Frontmatter

Optional YAML-like header between `---` fences. Parser is intentionally simple (not a full YAML library):

```markdown
---
slug: my-document
title: System Architecture
tags: architecture, backend, php
priority: 8
---

Body paragraphs go here...
```

| Key | Default | Effect |
|---|---|---|
| `slug` | filename without `.md` | Document id; chunk ids are `{slug}#{n}` |
| `title` | Title-cased slug | Shown in context headers and `sources[].label` |
| `tags` | `""` | String or `[a, b]` list → joined; folded into first chunk for BM25 |
| `priority` | `5` | Ranking boost: `score * (1 + (priority-5)*0.05)` |

Other keys (e.g. `author`, `type`) are parsed into meta but unused by retrieval today.

## Chunking rules

Implemented in `knowledge_split_body`:

1. Split on blank lines into paragraphs.
2. Merge bare short label lines ending in `:` with the following paragraph.
3. Merge undersized paragraphs (`< 320` chars) into the previous chunk when the result stays `≤ 900` chars.
4. Merge a trailing undersized last chunk into the previous one when possible.
5. Oversized chunks are split on sentence boundaries (`.!?`).

Chunk id example: `base#0`, `base#1`, …

## Authoring tips

- Prefer short, self-contained sections; the engine retrieves **chunks**, not whole files.
- Put critical keywords in `title` / `tags` — they boost the first chunk of that slug lexically.
- Use `priority` > 5 for canonical facts you want favored; < 5 to de-emphasize.
- Extend synonym coverage in `lib/synonyms.php` when domain jargon fails BM25 (that file currently carries portfolio-oriented maps — customize for your domain).
- After editing Markdown, run `php scripts/sync.php`. If vectors look stale despite sync, see [How it works — Sync behavior notes](how-it-works.md#sync-behavior-notes).

## Git policy

`.gitignore` tracks only `data/knowledge/base.md` under that folder. Personal or proprietary knowledge stays local. Never commit `config.php` or `data/knowledge.sqlite*`.
