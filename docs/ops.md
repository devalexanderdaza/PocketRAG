# Operations

## First-time local setup

```bash
git clone <repo-url>
cd PocketRAG   # or PoC checkout directory
cp config.example.php config.php
# edit API keys in config.php
```

Add Markdown under `data/knowledge/` (see [Knowledge base](knowledge.md)).

## Ingest / sync

```bash
php scripts/sync.php
```

Prints processed / skipped / deleted orphan counts. Requires valid Gemini keys for new embeddings.

**Auto-Sync** also runs inside chat when any Markdown file is newer than the SQLite file (`sync_knowledge_if_needed`). Prefer CLI sync after large edits so the first user request is not paying ingest latency. Details, limitations, and reset guidance: [Auto-Sync](auto-sync.md).

## Serve and smoke-test

```bash
php -S localhost:8080
```

```bash
curl -s http://localhost:8080/index.php \
  -H 'Content-Type: application/json' \
  -d '{"message":"What is PocketRAG?"}' | php -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), "\n";'
```

Checks:

- HTTP `200` and non-empty `reply`
- `mode` is `hybrid` when keys + DB are healthy
- `sources` non-empty for in-corpus questions
- `fallback_occurred` false under normal conditions

## No automated PHP test suite

Verification today is manual (CLI sync + curl). CI only runs the **zero-deps guard** (fails if `composer.json`, `composer.lock`, or `vendor/` appear).

## Telemetry

Rows land in `rag_telemetry` inside `data/knowledge.sqlite`. Inspect with any SQLite client, or use the built-in endpoint:

```bash
curl "http://localhost:8080/?action=telemetry&limit=50&since=0"
```

| Parameter | Description |
|-----------|-------------|
| `limit` | Max records to return (default 50, max 500) |
| `since` | Unix timestamp; only returns logs newer than this |

Old records are automatically pruned (~5% of writes, deleting records older than 30 days).

Useful signals:

- Rising `bm25_fallback` share → Gemini keys, network, or empty DB
- High `duration_ms` → auto-sync on request, Groq latency, or cold embed
- `search_query` ≠ `user_query` → reformulation active

## Vector precision

`vector_precision` (`f32` default, `int8` optional) is applied at ingest. After changing it, delete `data/knowledge.sqlite*` and re-run `php scripts/sync.php`. Int8 is not converted in place. `float16` is not implemented in v0.5.0.

## Community notes

When `community_notes_enabled` is true, the model may emit a `<!--NOTE-->…<!--/NOTE-->` JSON block. PocketRAG validates it and appends to `data/knowledge/community_notes.md` (gitignored). This is **not authentication** — any client can try to inject notes if the flag is on. Keep it off unless you trust callers.

## Logs

Failures in embeddings, sync, reformulation, and telemetry use `error_log`. On shared hosts, check the PHP error log path configured by the provider.

## Backup / reset

| Goal | Action |
|---|---|
| Backup knowledge | Copy `data/knowledge/*.md` |
| Backup vectors + telemetry | Copy `data/knowledge.sqlite*` while idle |
| Full re-embed | Delete `data/knowledge.sqlite*` then `php scripts/sync.php` |
