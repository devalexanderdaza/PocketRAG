# Configuration

## Setup

```bash
cp config.example.php config.php
```

Edit `config.php` locally. **Never commit it.**

Both `index.php` and sync/retrieval fall back to `config.example.php` if `config.php` is missing (useful for dry runs; not for production).

## Keys

| Key | Type | Default / example | Used by |
|---|---|---|---|
| `allowed_origin` | string | `*` | `http_send_cors` header output |
| `telemetry_enabled` | bool | `true` | `telemetry_log` toggle for SQLite request logging |
| `auto_sync_on_retrieval` | bool | `true` | `retrieval_select` toggle for fast mtime/hash freshness auto-sync |
| `chunk_overlap_chars` | int | `150` | `knowledge_split_body` character context overlap between chunks |
| `default_fallback_slugs` | array | `['profile', 'cv', 'about']` | Default documents to return if no search match |
| `rate_limit_enabled` | bool | `false` | Enable SQLite-based IP rate limiting |
| `rate_limit_rpm` | int | `30` | Max requests per IP within the window |
| `rate_limit_window` | int | `60` | Time window in seconds for the rate limit |
| `llm_provider` | string | `groq` | `llm_complete` dispatcher selection (`groq`, `ollama`, `openai`) |
| `groq_api_key` | string | placeholder `gsk_…` | Groq reformulation + completion. Placeholder / empty → mock reply |
| `groq_model` | string | `llama-3.3-70b-versatile` | Groq model id |
| `ollama_endpoint` | string | `http://localhost:11434/v1/chat/completions` | Ollama local endpoint |
| `ollama_model` | string | `llama3.2` | Ollama local model id |
| `openai_api_key` | string | placeholder `sk-…` | OpenAI API key |
| `openai_model` | string | `gpt-4o-mini` | OpenAI model id |
| `gemini_api_keys` | string[] | pool of keys | Embeddings; rotate on HTTP 429/403 |
| `gemini_model` | string | `gemini-embedding-001` | Embedding model |
| `hybrid_strategy` | string | `rrf` | `rrf` or `linear` hybrid blend |
| `query_expansion_enabled` | bool | `false` | Extra LLM variants (2) fused into cosine via RRF |
| `vector_precision` | string | `f32` | `f32` or `int8` stored embeddings (`float16` not implemented) |
| `community_notes_enabled` | bool | `false` | Append validated chat notes to `community_notes.md` |
| `sync_webhook_secret` | string | `''` | HMAC/Bearer secret for `POST /?action=sync` |

## Runtime requirements

| Requirement | Why |
|---|---|
| PHP 8.0+ | `declare(strict_types=1)`, typed code |
| Extension `pdo_sqlite` | Vector + telemetry store |
| Extension `curl` | Groq + Gemini HTTP |
| Extension `mbstring` | Tokenization / snippets |
| Writable `data/` | SQLite create + WAL |

## Environment notes

- There is no `.env` loader — config is a plain PHP return array.
- Hosting that disables `allow_url_fopen` is fine; the code uses cURL.
- OpenSSL CA trust must work for HTTPS (`CURLOPT_SSL_VERIFYPEER` enabled).
