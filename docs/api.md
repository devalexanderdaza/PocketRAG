# API

Base endpoint: **`index.php`** (repo root).

## Method and headers

| Item | Value |
|---|---|
| Method | `POST` for RAG API / `GET` serves Web UI (`public/index.html` & static assets) |
| `OPTIONS` | `204` + CORS headers |
| Content-Type (request) | `application/json` (for POST API) |
| Content-Type (response) | `application/json; charset=utf-8` (API) / `text/html`, `text/css`, `application/javascript` (UI) |
| CORS | Value of `allowed_origin` in `config.php` (default: `*`). Set a specific origin to restrict cross-origin access. |

## Web UI

Browsing to `/` or `index.php` via `GET` serves `public/index.html`. The page loads CSS/JS from `/assets/css/chat.css` and `/assets/js/chat.js` (files on disk under `public/assets/`). This requires `/assets/*` requests to be routed through `index.php` (e.g., `php -S localhost:8080 index.php` as a router, or an equivalent web server fallback); a `/public/` URL prefix is stripped so older asset links still resolve.

## Request body

```json
{
  "message": "What is PocketRAG?",
  "history": [
    { "role": "user", "content": "Hello!" },
    { "role": "assistant", "content": "Hi! How can I help you?" }
  ],
  "filter": {
    "slug": "optional-slug-string",
    "tags": ["optional", "tag", "array"]
  }
}
```

| Field | Required | Description |
|---|---|---|
| `message` | Usually | Current user turn. If empty and history ends with `user`, that turn is promoted to `message` and removed from history. |
| `history` | No | Prior turns. Alias: `messages`. |
| `history[].role` | — | Only `user` and `assistant` kept |
| `history[].content` | — | Non-empty string |
| `filter` | No | Optional pre-filter for multi-tenancy. When absent, all chunks are searched. |
| `filter.slug` | No | Exact match on document slug. |
| `filter.tags` | No | Array of tags; matches chunks containing ANY of the given tags (OR logic). |

Empty message after normalization → **`400`** `{ "error": "Message is required" }`.

## Success response (`200`)

```json
{
  "reply": "PocketRAG is a zero-dependency...",
  "search_query": "What is PocketRAG?",
  "sources": [
    {
      "id": "base",
      "label": "PocketRAG Base Knowledge System",
      "snippet": "PocketRAG (PHP Proof of Concept…",
      "score": 0.895,
      "file": "base.md",
      "heading": "Architecture",
      "line": 14
    }
  ],
  "mode": "hybrid",
  "fallback_occurred": false,
  "duration_ms": 1250,
  "error": null
}
```

| Field | Type | Description |
|---|---|---|
| `reply` | string | Model answer, mock text, or generic error string |
| `search_query` | string | Query used for retrieval (may be reformulated) |
| `sources` | array | Up to 3 citation objects (`id` = document slug; also `file`, `heading`, `line`) |
| `mode` | string | `hybrid` \| `bm25_fallback` |
| `fallback_occurred` | bool | True when vector path contributed no scores |
| `duration_ms` | number | Wall time for the request handling path |
| `error` | string\|null | Groq exception message if generation threw; HTTP status still `200` |

## Sync Webhook Endpoint

**`POST /?action=sync`**

Triggers a knowledge sync (same as `php scripts/sync.php`). Requires authentication via `sync_webhook_secret` in `config.php`.

### Request

- **Method:** `POST`
- **Query:** `action=sync`
- **Headers:** `Content-Type: application/json`
- **Body:** Any JSON (e.g., `{}` or `{"action":"sync"}`)

### Authentication

| Method | Header | Format |
|--------|--------|--------|
| HMAC-SHA256 | `X-Hub-Signature-256` | `sha256=<hmac_hex>` |
| Bearer | `Authorization` | `Bearer <token>` |

### Success Response (`200`)

```json
{
  "ok": true,
  "chunks": 5,
  "skipped": 12,
  "deleted": 1,
  "duration_ms": 2340
}
```

| Field | Type | Description |
|---|---|---|
| `ok` | bool | Always `true` on success |
| `chunks` | int | Number of chunks processed/embedded |
| `skipped` | int | Number of chunks skipped (already up-to-date) |
| `deleted` | int | Number of orphaned chunks removed |
| `duration_ms` | number | Wall time for the sync operation |

### Error Response (`401`)

```json
{
  "error": "Unauthorized"
}
```

Returned when `sync_webhook_secret` is not configured or authentication headers are missing/invalid.

## Error responses

| Status | Body | When |
|---|---|---|
| `400` | `{ "error": "Message is required" }` | No usable message |
| `401` | `{ "error": "Unauthorized" }` | Invalid or missing webhook secret |
| `405` | `{ "error": "Method not allowed" }` | Not POST/OPTIONS |

## Telemetry Endpoint

```
GET /?action=telemetry
```

Retrieve recent telemetry logs from the RAG engine.

### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `limit` | int | 50 | Maximum number of logs to return |
| `since` | int | 0 | Unix timestamp; only return logs created at or after this time (optional) |

### Example Request

```bash
curl "http://localhost:8080/?action=telemetry&limit=10&since=1699900000"
```

### Success Response (`200`)

```json
{
  "logs": [
    {
      "id": 42,
      "user_query": "What is PocketRAG?",
      "search_query": "What is PocketRAG?",
      "mode": "hybrid",
      "fallback_occurred": 0,
      "fallback_reason": null,
      "sources_count": 3,
      "duration_ms": 1250.5,
      "created_at": 1699900100
    }
  ]
}
```

### Notes

- Logs are returned in descending order by `id` (newest first).
- Pruning is stochastic (1 in 20 chance after each `telemetry_log` call) and removes logs older than 30 days by default.

## Example (local)

```bash
php -S localhost:8080
```

```bash
curl -s http://localhost:8080/index.php \
  -H 'Content-Type: application/json' \
  -d '{"message":"What is hybrid search in PocketRAG?"}'
```

With history:

```bash
curl -s http://localhost:8080/index.php \
  -H 'Content-Type: application/json' \
  -d '{
    "message": "And how are vectors stored?",
    "history": [
      {"role":"user","content":"Explain PocketRAG retrieval"},
      {"role":"assistant","content":"It combines BM25 and embeddings."}
    ]
  }'
```

## Client expectations

- Treat `sources` as UI citations, not as a guarantee that every context chunk is listed. Each source may include `file` (markdown basename), `heading` (nearest `#` title, or `null`), and `line` (1-indexed start in the body after frontmatter).
- Prefer `search_query` when debugging retrieval mismatches vs the user-visible `message`.
- Mock mode (placeholder Groq key) still returns retrieval metadata so you can validate search without LLM spend.
