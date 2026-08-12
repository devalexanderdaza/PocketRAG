# Repository Guidelines

PocketRAG — zero-dependency hybrid RAG engine for shared PHP hosting. Pure PHP 8+ with PDO SQLite, native BM25 + Gemini embeddings + Groq/OpenAI/Ollama LLMs. No Composer, no Node, no Docker. Quick start in `README.md`; human reference under `docs/`.

## Project Overview

PocketRAG is a single-endpoint Retrieval-Augmented-Generation engine designed to run on cheap shared PHP hosting. It combines native Okapi BM25 keyword search with Gemini embedding-vector cosine similarity (hybrid score `0.7*cosine + 0.3*BM25`), feeds a retrieved context window into an LLM (Groq / Ollama / OpenAI), and returns a conversational reply over a stateless JSON API. Knowledge is authored as Markdown files with YAML frontmatter under `data/knowledge/`, embedded into a SQLite store via a single CLI ingestion script, and served by a procedural PHP front controller. The hard product constraint is **zero external dependencies** — no Composer, no `vendor/`, no third-party PHP packages.

## Architecture & Data Flow

Procedural, single-entry-point architecture. No router, no MVC, no DI container, no namespaces, no sessions/auth.

**Request flow (`index.php`):**
- `GET` → serves static assets from `public/` (manual MIME map: css/js/html/png/svg) and falls back to `public/index.html` (the dark-mode chat UI driven by `public/assets/js/chat.js`).
- `POST` → `rate_limit_check` → `http_read_json_body` → `conversation_normalize_history` → `conversation_reformulate_query` (LLM) → `knowledge_load_chunks` (`data/knowledge/*.md`) → `knowledge_build_index` (in-memory BM25) → `retrieval_select` → `prompt_build` (`data/persona.md`) → `llm_complete` (dispatch `groq|ollama|openai`) → `telemetry_log` (SQLite) → `http_send_json`.

**Retrieval pipeline (`lib/retrieval.php` → `lib/sync.php`):**
`synonyms_expand` → `bm25_search` → `sync_knowledge_if_needed` (freshness gate) → `embeddings_get` (Gemini, key-pool rotation on 429/403) → `db_get_pdo` SELECT over `knowledge_chunks` → `cosine_similarity_precomputed` → hybrid `0.7*cos + 0.3*bm25` → priority boost → top-K=4.

**Storage:** SQLite via PDO singleton (`db_get_pdo` with static `pdoMap`), WAL mode, auto-schema for `knowledge_chunks` (embedding BLOB packed via `pack('f*')`) and `rag_telemetry` tables; legacy `content_hash` migration. Vectors are stored as binary BLOBs, not JSON arrays.

**Config:** plain PHP array returned by `config.php` (gitignored), cached statically in `http_config()` with fallback to `config.example.php`; throws `RuntimeException` if neither exists. No `.env` loader.

## Key Directories

- `index.php` — front controller / procedural controller; sole HTTP entry point.
- `lib/` — all library modules, loaded via `require_once` with module-prefixed global functions (`bm25_`, `knowledge_`, `retrieval_`, `http_`, `vector_`/`math_`, `sync_`, `telemetry_`, `rate_limit_`, `conversation_`, `prompt_`, `llm_`, `groq_`, `ollama_`, `openai_`, `embeddings_`, `synonyms_`, `db_`). Module map in `docs/components.md`.
- `public/` — static web UI (`index.html`, `assets/js/chat.js`, css). The "view" is the JSON payload consumed by `chat.js`.
- `data/knowledge/` — Markdown knowledge files with YAML frontmatter (only `base.md` is tracked; personal files are gitignored). Format in `docs/knowledge.md`.
- `data/knowledge.sqlite*` — generated vector store (gitignored).
- `data/persona.md` — system persona injected into the LLM prompt.
- `data/synonyms.json` — optional query-expansion map.
- `scripts/` — CLI utilities (only `sync.php`).
- `tests/` — zero-dep BDD-style test harness + `*_test.php` suites.
- `docs/` — human-facing reference (index at `docs/README.md`).
- `.github/workflows/` — CI: `tests.yml` + `zero-deps.yml`.
- `foundation/` — not part of the project; never treat as source of truth (see Policy).

## Development Commands

```bash
# 1. Configure (one-time)
cp config.example.php config.php   # then edit config.php with your API keys

# 2. Ingest knowledge -> SQLite vectors (idempotent; run after editing data/knowledge/*.md)
php scripts/sync.php

# 3. Run the test suite (zero-dep; exit 0 = pass, 1 = fail)
php tests/run.php

# 4. Serve locally and exercise the API
php -S localhost:8080              # from repo root
curl -s -X POST http://localhost:8080/ \
  -H 'Content-Type: application/json' \
  -d '{"message":"What is PocketRAG?","history":[]}'

# If vectors look stale: delete the store and re-sync
rm -f data/knowledge.sqlite* && php scripts/sync.php
```

No Composer, no Makefile, no Docker, no Node scripts. No linter/formatter/static-analyzer is configured.

## Code Conventions & Common Patterns

- **No namespaces, no PSR-4, no Composer autoload.** Wire modules with `require_once` and module-prefixed global functions in `lib/`.
- `declare(strict_types=1)` on **every** PHP file.
- **Naming:** `snake_case` for functions/variables; `UPPER_SNAKE` with module prefix for constants (`BM25_K1`, `EMBEDDINGS_TIMEOUT_SECS`, `KNOWLEDGE_MAX_CHUNK_CHARS`); lowercase `.php` filenames. Test files are `*_test.php` (not `*Test.php`).
- **Vectors:** store embedding vectors as SQLite BLOBs via `pack('f*')` / `unpack` in `lib/math.php` (`vector_pack` / `vector_unpack`), never as JSON arrays.
- **Error handling:** mixed. LLM clients (`groq_`/`ollama_`/`openai_`) throw `RuntimeException`, caught at the top of `index.php` → generic reply + `error` field. `embeddings_get` returns `?array` and rotates keys on 429/403. `rate_limit`, `telemetry`, `sync`, `conversation_reformulate` use fail-open `try/catch Throwable`. `cosine_similarity_precomputed` returns `0.0` + `error_log` on dimension mismatch. Logging is native `error_log()` (no PSR-3).
- **Async/concurrency:** none. Synchronous cURL with short timeouts (`EMBEDDINGS_TIMEOUT_SECS=2`, `GROQ_TIMEOUT_SECS=20`, `OLLAMA_TIMEOUT_SECS=30`). Groq client retries twice with exponential backoff + jitter. Sync ingestion uses an atomic transaction (`beginTransaction`/`commit`/`rollBack`) and collects all vectors before any writes. Rate-limit pruning is stochastic (~5%).
- **State / auth:** stateless JSON-only API. No sessions, no auth, no CSRF. Conversation history travels in the POST body and is normalized to the last 10 messages.
- **Config access:** always via `http_config()` (memoized); never `require` config directly.
- **Documentation:** PHPDoc blocks in English on every function (exported and internal helpers) and every class, covering purpose, `@param`, `@return`, `@throws` as applicable. Inline comments only for non-obvious logic, in English. No commented-out code.

## Important Files

- `index.php` — entry point; procedural controller (GET static, POST RAG pipeline).
- `lib/http.php` — `http_config()`, `http_send_cors()`, `http_send_json()`, `http_require_post()`, `http_read_json_body()`.
- `lib/retrieval.php` — `retrieval_select()`; orchestrates synonyms → BM25 → sync → embeddings → cosine → hybrid → top-K.
- `lib/sync.php` — `sync_knowledge_run()` (CLI ingestion), `sync_knowledge_if_needed()` (freshness gate). See `docs/auto-sync.md`.
- `lib/knowledge.php` — `knowledge_parse_frontmatter()`, `knowledge_split_body()` (paragraph-aware, overlap, `[...]` marker), `knowledge_load_chunks()`, `knowledge_build_index()`.
- `lib/bm25.php` — native Okapi BM25 (`bm25_fold`, `bm25_tokenize`, `bm25_index`, `bm25_search`); `BM25_K1=1.5`, `BM25_B=0.75`, EN+ES stopwords.
- `lib/math.php` — `vector_pack`/`vector_unpack`/`vector_magnitude`/`cosine_similarity_precomputed`.
- `lib/embeddings.php` — Gemini client with key-pool rotation (`embeddings_get(): ?array`).
- `lib/conversation.php` — `conversation_normalize_history()`, `conversation_reformulate_query()`.
- `lib/llm.php` + `lib/groq.php` / `lib/ollama.php` / `lib/openai.php` — LLM dispatch + provider clients.
- `config.example.php` — full config key reference (commit-able template).
- `.htaccess` — Apache 2.4+ security hardening only (denies `config.php`, `*.sqlite*`, hidden files; `Options -Indexes`); no rewrites.
- `docs/README.md` — human docs index (architecture, API, auto-sync, ops, deploy, contributing, components, knowledge, configuration).

## Runtime / Tooling Preferences

- **PHP 8.0+** (CI runs 8.2). Required extensions: `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`. No Composer.
- **No package manager.** Do not add `composer.json`, `composer.lock`, or `vendor/` — CI (`zero-deps.yml`) fails the build if any of these appear.
- **No linter/formatter/static analyzer** is configured. Conventions are enforced manually (see above) and via the zero-deps guard.
- **Web server:** Apache 2.4+ (`.htaccess`) or PHP built-in server (`php -S`) for local dev.

## Testing & QA

- **Framework:** custom zero-dependency BDD-style harness in `tests/run.php` (no PHPUnit, no Pest). DSL: `describe()` / `it()` / `expect()` with matchers `toBe`, `toEqual`, `toContain`, `toBeTrue`, `toBeFalse`, `toBeNull`, `toBeGreaterThan`, `toBeLessThan`, `toHaveCount`.
- **Run:** `php tests/run.php` (auto-discovers `tests/*_test.php` via glob; exit `0` pass / `1` fail).
- **Layout:** flat `tests/` directory, `*_test.php` naming, 1:1 mapping to `lib/` modules (e.g. `lib/bm25.php` → `tests/bm25_test.php`). No Unit/Feature split, no `setUp`/`tearDown` — each `it()` is self-contained with inline literal data.
- **No mocking framework.** External calls (Groq/OpenAI/Ollama) are avoided by passing placeholder API keys and asserting the function returns the original input without invoking the LLM.
- **No coverage tooling** (no Xdebug/PCOV, no flags, no thresholds).
- **CI:** `.github/workflows/tests.yml` (PHP 8.2, ext `sqlite3`/`pdo_sqlite`/`curl`, runs `php tests/run.php` on push/PR to `main`) and `.github/workflows/zero-deps.yml` (fails if `composer.json`/`composer.lock`/`vendor/` appear; runs on push to `main`+`develop` and PRs).
- **When adding a lib module**, add a matching `tests/<module>_test.php` so the suite stays 1:1.

## Policy (non-negotiable)

- Never add Composer, `vendor/`, or third-party PHP packages — zero external dependencies is a hard product constraint. Discuss exceptions before changing this.
- Never commit `config.php`, `data/knowledge.sqlite*`, or personal files under `data/knowledge/` (only `data/knowledge/base.md` is tracked). Copy secrets from `config.example.php` locally.
- Do not treat `foundation/` as guidance or source of truth; prefer tracked code, `README.md`, and `docs/`.
- When changing behavior, public contracts, or configuration surface, update the matching file under `docs/` in the same change (not limited to HTTP/retrieval/deploy).
- Conventional Commits in English are expected for commit messages (repo convention; not enforced by tooling).
- Git-flow (simplified): `main` = production, `develop` = integration. Branch feature work as `feature/*` from `develop`. PRs target `main`; `develop` is the named integration branch. PR template at `.github/pull_request_template.md` (Summary / Verification / Notes).

## Team Conventions

Authoritative team rules preserved outside the `bmad:context` block so they survive `bmad-project-context` refreshes. If the managed block above restates any of these, this section is the source of truth.

- **Documentation:** PHPDoc blocks in English on every function (exported and internal helpers) and every class, covering purpose, `@param`, `@return`, `@throws` as applicable. Inline comments only for non-obvious logic, in English. No commented-out code.
- **Docs stay current:** When changing behavior, public contracts, or configuration surface, update the matching file under `docs/` in the same change (not limited to HTTP/retrieval/deploy).
- **Git-flow (simplified):** `main` = production, `develop` = integration. Branch feature work as `feature/*` from `develop`. PRs target `main`; `develop` is the named integration branch. PR template at `.github/pull_request_template.md` (Summary / Verification / Notes).
- **Conventional Commits in English** are expected for commit messages (repo convention; not enforced by tooling).
