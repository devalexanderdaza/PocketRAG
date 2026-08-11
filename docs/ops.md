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

Auto-sync also runs inside chat when Markdown is newer than the SQLite file. Prefer CLI sync after large edits so the first user request is not paying ingest latency.

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

Rows land in `rag_telemetry` inside `data/knowledge.sqlite`. Inspect with any SQLite client, or call `telemetry_get_recent($dbPath, 50)` from a one-off PHP snippet. There is no shipped dashboard endpoint.

Useful signals:

- Rising `bm25_fallback` share → Gemini keys, network, or empty DB
- High `duration_ms` → auto-sync on request, Groq latency, or cold embed
- `search_query` ≠ `user_query` → reformulation active

## Logs

Failures in embeddings, sync, reformulation, and telemetry use `error_log`. On shared hosts, check the PHP error log path configured by the provider.

## Backup / reset

| Goal | Action |
|---|---|
| Backup knowledge | Copy `data/knowledge/*.md` |
| Backup vectors + telemetry | Copy `data/knowledge.sqlite*` while idle |
| Full re-embed | Delete `data/knowledge.sqlite*` then `php scripts/sync.php` |
