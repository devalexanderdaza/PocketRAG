# API

Base endpoint: **`index.php`** (repo root).

## Method and headers

| Item | Value |
|---|---|
| Method | `POST` (other methods → `405`) |
| `OPTIONS` | `204` + CORS headers |
| Content-Type (request) | `application/json` |
| Content-Type (response) | `application/json; charset=utf-8` |
| CORS | `Access-Control-Allow-Origin: *` (hardcoded in `http_send_cors`; `allowed_origin` in config is not applied yet) |

## Request body

```json
{
  "message": "What is PocketRAG?",
  "history": [
    { "role": "user", "content": "Hello!" },
    { "role": "assistant", "content": "Hi! How can I help you?" }
  ]
}
```

| Field | Required | Description |
|---|---|---|
| `message` | Usually | Current user turn. If empty and history ends with `user`, that turn is promoted to `message` and removed from history. |
| `history` | No | Prior turns. Alias: `messages`. |
| `history[].role` | — | Only `user` and `assistant` kept |
| `history[].content` | — | Non-empty string |

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
      "score": 0.895
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
| `sources` | array | Up to 3 citation objects (`id` = document slug) |
| `mode` | string | `hybrid` \| `bm25_fallback` |
| `fallback_occurred` | bool | True when vector path contributed no scores |
| `duration_ms` | number | Wall time for the request handling path |
| `error` | string\|null | Groq exception message if generation threw; HTTP status still `200` |

## Error responses

| Status | Body | When |
|---|---|---|
| `400` | `{ "error": "Message is required" }` | No usable message |
| `405` | `{ "error": "Method not allowed" }` | Not POST/OPTIONS |

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

- Treat `sources` as UI citations, not as a guarantee that every context chunk is listed.
- Prefer `search_query` when debugging retrieval mismatches vs the user-visible `message`.
- Mock mode (placeholder Groq key) still returns retrieval metadata so you can validate search without LLM spend.
