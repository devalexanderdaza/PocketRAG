# Data model

SQLite database path: `data/knowledge.sqlite` (created on first use). Journal mode: **WAL** (`synchronous=NORMAL`).

Schema is bootstrapped in `db_get_pdo()` (`lib/db.php`).

## Table `knowledge_chunks`

| Column | Type | Notes |
|---|---|---|
| `id` | TEXT PK | `{slug}#{position}` |
| `slug` | TEXT | Document slug |
| `title` | TEXT | From frontmatter |
| `tags` | TEXT | Normalized string |
| `priority` | INTEGER | Default 5 |
| `content` | TEXT | Chunk body |
| `embedding` | BLOB | `f32` via `pack('f*')`, or int8 (`I8Q1` + scale + `pack('c*')`) when `vector_precision=int8` |
| `vector_magnitude` | REAL | Precomputed `‖v‖` for cosine |
| `embedding_model` | TEXT | e.g. `gemini-embedding-001` |
| `dimensions` | INTEGER | e.g. `768` |
| `created_at` | INTEGER | Unix time of last upsert |

Index: `idx_slug` on `slug`.

Upsert key is `id` (`ON CONFLICT(id) DO UPDATE …`).

## Table `rag_telemetry`

| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Auto-increment |
| `user_query` | TEXT | Original user message |
| `search_query` | TEXT | Possibly reformulated |
| `mode` | TEXT | `hybrid` or `bm25_fallback` |
| `fallback_occurred` | INTEGER | 0/1 |
| `fallback_reason` | TEXT | Nullable |
| `sources_count` | INTEGER | Citation count returned |
| `duration_ms` | REAL | Request duration |
| `created_at` | INTEGER | Unix time |

Index: `idx_telemetry_created` on `created_at`.

Helpers: `telemetry_log`, `telemetry_get_recent` in `lib/telemetry.php` (no HTTP admin UI ships yet).

## Table `query_cache`

| Column | Type | Notes |
|---|---|---|
| `query_hash` | TEXT PK | sha256(`model|dims|text`) |
| `embedding` | BLOB | Cached query vector (`f32`) |
| `vector_magnitude` | REAL | Precomputed magnitude |
| `created_at` | INTEGER | Unix time |

TTL default 7 days; stochastic prune (~5% of writes).

## Vector encoding

```php
$blob = pack('f*', ...$floats);   // store
$floats = array_values(unpack('f*', $blob)); // load
```

Query-time cosine uses precomputed magnitudes:

```text
cos(A,B) = (A·B) / (‖A‖ ‖B‖)
```

Then mapped to `[0,1]` as `(cos + 1) / 2` before hybrid mix.

## Lifecycle files

| Path | Purpose |
|---|---|
| `data/knowledge.sqlite` | Main DB |
| `data/knowledge.sqlite-wal` | WAL log |
| `data/knowledge.sqlite-shm` | Shared memory |

Changing `vector_precision` from `f32` to `int8` (or back) requires a full re-embed: delete `data/knowledge.sqlite*` then `php scripts/sync.php`.

Query-time cosine always runs on floats (int8 BLOBs are dequantized first). Magnitudes stored at ingest are computed from the original f32 vector.

To compare Recall@1 locally after switching precision, embed a small fixture corpus twice (f32 vs int8) and check that the top neighbor for a held-out query is unchanged (see `tests/math_test.php`).
