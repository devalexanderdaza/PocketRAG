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
| `allowed_origin` | string | `*` | Present in example; **CORS currently hardcodes `*`** in `http_send_cors` |
| `groq_api_key` | string | placeholder `gsk_…` | Reformulation + completion. Placeholder / empty → mock reply, no reformulation |
| `groq_model` | string | `llama-3.3-70b-versatile` | Groq model id |
| `gemini_api_keys` | string[] | pool of keys | Embeddings; rotate on HTTP 429/403 |
| `gemini_model` | string | `gemini-embedding-001` | Embedding model |
| `gemini_dimensions` | int | `768` | `outputDimensionality` on embed requests |

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
