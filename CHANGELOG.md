# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- **CRITICAL**: Added `.htaccess` to block direct HTTP access to `config.php` and SQLite databases (`data/*.sqlite`).
- Added SQLite-based sliding window rate limiting (configurable via `rate_limit_enabled`, `rate_limit_rpm`, `rate_limit_window`).
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
- Corrected documentation in `docs/api.md` (CORS is properly applied) and `docs/auto-sync.md` (chunk skip logic relies on `content_hash`).

### Changed
- Refactored `prompt_build` to read the assistant persona (name, description, rules) from an external `data/persona.md` file rather than hardcoding it in PHP.
- Refactored `synonyms_expand` to load domain-specific query expansion dictionaries from an external `data/synonyms.json` file.
- Made the default fallback document slugs configurable via `default_fallback_slugs` in `config.php`.
- Implemented exponential backoff with jitter for HTTP 429/5xx retries in `groq_complete`.
- Improved static analysis by adding `@return never` to `http_send_json`.
- Unified embedding sync timeout to use `EMBEDDINGS_SYNC_TIMEOUT_SECS` (15s) instead of a hardcoded 5 seconds.
- Added explicit SSL verification and a configurable timeout (`OLLAMA_TIMEOUT_SECS`) to the Ollama client.

### Added
- Pure PHP, zero-dependency unit testing harness (`tests/run.php`) and test suites for BM25, Math, Knowledge, Synonyms, and Conversation modules.
- GitHub Actions CI workflow to run the test suite on Push/PR.
- Created `docs/customization.md` to guide users on modifying the assistant's behavior without editing source code.
