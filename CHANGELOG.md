# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] — 2026-08-14

### Added
- **Source citations**: `sources[]` now includes `file`, `heading`, and `line`; chat UI shows the reference.
- **Query embedding cache**: SQLite `query_cache` (7-day TTL, stochastic prune) used by `embeddings_get` before Gemini.
- **Query expansion** (opt-in `query_expansion_enabled`): up to two LLM query variants fused into cosine ranking via RRF. Default off. HyDE documents are not used.
- **Int8 vector storage** (`vector_precision: int8`): magic-prefixed BLOBs; magnitudes stay f32. Default remains `f32`. `float16` is not implemented.
- **Community notes** (opt-in `community_notes_enabled`): validated `<!--NOTE-->` JSON stripped from the reply and appended to `data/knowledge/community_notes.md`. Default off.

## [0.4.0] — 2026-08-12

### Added
- **Telemetry read endpoint**: `GET /?action=telemetry` returns recent logs from `rag_telemetry` as JSON. Accepts `?limit=N` (default 50) and `?since=timestamp` (unix timestamp) to filter.
- **Telemetry pruning**: `telemetry_prune($olderThanDays = 30)` deletes records older than N days. Called stochastically (~5%) on each `telemetry_log()` write to prevent unbounded table growth.
- **Secure sync webhook**: `POST /?action=sync` triggers `sync_knowledge_run()` protected by HMAC-SHA256 (`X-Hub-Signature-256`) or Bearer token validation against `sync_webhook_secret` in config. Returns `{ok, chunks, skipped, deleted, duration_ms}`.
- `sync_webhook_secret` config key for webhook authentication.

### Changed
- `embeddings_get` now retries on HTTP 5xx errors in addition to 429/403, rotating API keys on server-side failures.
- `llm_complete` returns a unified `[MOCK RESPONSE]` for any provider with a placeholder API key (Groq, OpenAI, Ollama), not just Groq.
- `prompt_build` removed dead `$visitorType` and `$locale` parameters.
- Message content in `conversation_normalize_history` is truncated to 4000 chars to prevent oversized history payloads.
- `index.php` returns `400` if the incoming message exceeds 4000 characters.

### Fixed
- `conversation_reformulate_query` now respects the configured LLM provider (not just Groq) for query reformulation.

## [0.3.0] — 2026-08-12

### Changed
- `knowledge_split_body` now respects Markdown headings (`#{1-6}`) and fenced code blocks (`` ``` ``) as split boundaries, preventing chunks from breaking structured content mid-block.
- `chunk_min_chars` (default 320) and `chunk_max_chars` (default 900) are now configurable via `config.php` instead of hardcoded constants.

### Added
- **Reciprocal Rank Fusion (RRF)**: `retrieval_select` now uses RRF (`1/(k+rank)`, `k=60`) as the default hybrid scoring strategy, replacing the legacy linear blend (`0.7*cos + 0.3*bm25`). Set `hybrid_strategy: linear` in `config.php` to preserve the old behavior.
- **Metadata pre-filtering**: POST body accepts an optional top-level `filter` object (`{ "slug": "string", "tags": [...] }`) to scope retrieval to specific documents or tag sets. Fully backward-compatible — absent filter scans all chunks.
- Tests for RRF fusion logic, filter SQL construction, and structure-aware chunking.

### Fixed
- Removed deprecated `curl_close()` calls from all cURL clients (`groq`, `ollama`, `openai`, `embeddings`) — these have been no-ops since PHP 8.0 and trigger deprecation warnings in PHP 8.5.

## [0.2.0] — 2026-08-12

### Security
- **CRITICAL**: Added `.htaccess` to block direct HTTP access to `config.php` and SQLite databases (`data/*.sqlite`).
- Added SQLite-based sliding window rate limiting (configurable via `rate_limit_enabled`, `rate_limit_rpm`, `rate_limit_window`).
- Hardened `rate_limit_get_ip()` against `X-Forwarded-For` spoofing: the proxy chain is only trusted when `REMOTE_ADDR` is in `trusted_proxies` (new config key), forwarded entries are validated, and malformed/forged chains fail safe to `0.0.0.0`.
- `http_config()` now throws a `RuntimeException` instead of failing silently if `config.php` (or `config.example.php`) is missing, preventing unintended unconfigured states.
- Created `docs/security.md` detailing hardening for Apache, Nginx, and rate limiter configuration.

### Fixed
- Fixed bug in `cosine_similarity_precomputed` where vectors of mismatched dimensions (e.g., after changing model config without a DB reset) would silently return invalid scores. It now logs an error and returns `0.0`.
- Fixed bug in `sync_knowledge_run` where a failure mid-sync could leave the database in an inconsistent state. Operations are now wrapped in an atomic SQLite transaction.
- Fixed bug in `conversation_reformulate_query` where it hardcoded the use of `groq_complete`. It now respects the active `llm_provider` (Groq, OpenAI, Ollama) via `llm_complete`.
- Fixed multiple cURL connection leaks by properly calling `curl_close()` instead of `unset()`.
- Handled structured JSON error responses from LLM API providers so they are thrown as readable `RuntimeExceptions`.
- Fixed silent failures in `db_get_pdo` migrations and Markdown file loading by explicitly logging them via `error_log`.
- Fixed duplicate PHPDoc block in `knowledge_split_body`.
- Fixed test (8.1) failure by restoring trusted-proxy IP resolution in `rate_limit_get_ip()`.
- Corrected documentation in `docs/api.md` (CORS is properly applied) and `docs/auto-sync.md` (chunk skip logic relies on `content_hash`).

### Changed
- Refactored `prompt_build` to read the assistant persona (name, description, rules) from an external `data/persona.md` file rather than hardcoding it in PHP.
- Refactored `synonyms_expand` to load domain-specific query expansion dictionaries from an external `data/synonyms.json` file.
- Made the default fallback document slugs configurable via `default_fallback_slugs` in `config.php`.
- Implemented exponential backoff with jitter for HTTP 429/5xx retries in `groq_complete`.
- Improved static analysis by adding `@return never` to `http_send_json`.
- Unified embedding sync timeout to use `EMBEDDINGS_SYNC_TIMEOUT_SECS` (15s) instead of a hardcoded 5 seconds.
- Added explicit SSL verification and a configurable timeout (`OLLAMA_TIMEOUT_SECS`) to the Ollama client.
- Documented strict git-flow branching convention in `docs/contributing.md` and added `ROADMAP.md`.
- Aligned README and docs with the current code (multi-provider reformulation, config-throw behavior, updated API components).

### Added
- Pure PHP, zero-dependency unit testing harness (`tests/run.php`) with 1:1 lib→test coverage (BM25, Math, Knowledge, Synonyms, Conversation, DB, Embeddings, HTTP, LLM providers, Prompt, Retrieval, Sync, Telemetry, Rate Limit).
- GitHub Actions CI workflow running the suite on push/PR to `main` and `develop` under a PHP 8.1/8.2/8.3 matrix.
- Created `docs/customization.md` to guide users on modifying the assistant's behavior without editing source code.
