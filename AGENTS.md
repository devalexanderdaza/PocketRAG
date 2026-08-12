[AGENTS.md#46AB]
1:[AGENTS.md#9B11]
2:<!-- bmad:context -->
3:<!-- Verified 2026-08-12 against b8d40723a862528b2c96f4c0252c164a8eae2d19. Managed by bmad-project-context; edits inside this block are replaced on refresh. Keep anything you want preserved outside the markers. -->
4:
5:# Repository Guidelines
6:
7:PocketRAG — zero-dependency hybrid RAG engine for shared PHP hosting. Pure PHP 8+ with PDO SQLite, native BM25 + Gemini embeddings + Groq/OpenAI/Ollama LLMs. No Composer, no Node, no Docker. Quick start in `README.md`; human reference under `docs/`; planning artifacts (when present) under `_bmad-output/`.
8:
9:## Project Overview
10:
11:PocketRAG is a single-endpoint Retrieval-Augmented-Generation engine designed to run on cheap shared PHP hosting. It combines native Okapi BM25 keyword search with Gemini embedding-vector cosine similarity (hybrid score `0.7*cosine + 0.3*BM25`), feeds a retrieved context window into an LLM (Groq / Ollama / OpenAI), and returns a conversational reply over a stateless JSON API. Knowledge is authored as Markdown files with YAML frontmatter under `data/knowledge/`, embedded into a SQLite store via a single CLI ingestion script, and served by a procedural PHP front controller. The hard product constraint is **zero external dependencies** — no Composer, no `vendor/`, no third-party PHP packages.
12:
13:## Architecture & Data Flow
14:
15:Procedural, single-entry-point architecture. No router, no MVC, no DI container, no namespaces, no sessions/auth.
16:
17:**Request flow (`index.php`):**
18:- `GET` → serves static assets from `public/` (manual MIME map: css/js/html/png/svg) and falls back to `public/index.html` (the dark-mode chat UI driven by `public/assets/js/chat.js`).
19:- `POST` → `rate_limit_check` → `http_read_json_body` → `conversation_normalize_history` → `conversation_reformulate_query` (LLM) → `knowledge_load_chunks` (`data/knowledge/*.md`) → `knowledge_build_index` (in-memory BM25) → `retrieval_select` → `prompt_build` (`data/persona.md`) → `llm_complete` (dispatch `groq|ollama|openai`) → `telemetry_log` (SQLite) → `http_send_json`.
20:
21:**Retrieval pipeline (`lib/retrieval.php` → `lib/sync.php`):**
22:`synonyms_expand` → `bm25_search` → `sync_knowledge_if_needed` (freshness gate) → `embeddings_get` (Gemini, key-pool rotation on 429/403) → `db_get_pdo` SELECT over `knowledge_chunks` → `cosine_similarity_precomputed` → hybrid `0.7*cos + 0.3*bm25` → priority boost → top-K=4.
23:
24:**Storage:** SQLite via PDO singleton (`db_get_pdo` with static `pdoMap`), WAL mode, auto-schema for `knowledge_chunks` (embedding BLOB packed via `pack('f*')`) and `rag_telemetry` tables; legacy `content_hash` migration. Vectors are stored as binary BLOBs, not JSON arrays.
25:
26:**Config:** plain PHP array returned by `config.php` (gitignored), cached statically in `http_config()` with fallback to `config.example.php`; throws `RuntimeException` if neither exists. No `.env` loader.
27:
28:## Key Directories
29:
30:- `index.php` — front controller / procedural controller; sole HTTP entry point.
31:- `lib/` — all library modules, loaded via `require_once` with module-prefixed global functions (`bm25_`, `knowledge_`, `retrieval_`, `http_`, `vector_`/`math_`, `sync_`, `telemetry_`, `rate_limit_`, `conversation_`, `prompt_`, `llm_`, `groq_`, `ollama_`, `openai_`, `embeddings_`, `synonyms_`, `db_`). Module map in `docs/components.md`.
32:- `public/` — static web UI (`index.html`, `assets/js/chat.js`, css). The "view" is the JSON payload consumed by `chat.js`.
33:- `data/knowledge/` — Markdown knowledge files with YAML frontmatter (only `base.md` is tracked; personal files are gitignored). Format in `docs/knowledge.md`.
34:- `data/knowledge.sqlite*` — generated vector store (gitignored).
35:- `data/persona.md` — system persona injected into the LLM prompt.
36:- `data/synonyms.json` — optional query-expansion map.
37:- `scripts/` — CLI utilities (only `sync.php`).
38:- `tests/` — zero-dep BDD-style test harness + `*_test.php` suites.
39:- `docs/` — human-facing reference (index at `docs/README.md`).
40:- `.github/workflows/` — CI: `tests.yml` + `zero-deps.yml`.
41:- `.agents/skills/`, `_bmad/`, `_bmad-output/` — BMAD method infrastructure (all gitignored). Not product code; consult only when operating the BMAD method (e.g. `bmad-project-context` to refresh this file). Never treat `foundation/` as source of truth (it is not part of the project).
42:
43:## Development Commands
44:
45:```bash
46:# 1. Configure (one-time)
47:cp config.example.php config.php   # then edit config.php with your API keys
48:
49:# 2. Ingest knowledge -> SQLite vectors (idempotent; run after editing data/knowledge/*.md)
50:php scripts/sync.php
51:
52:# 3. Run the test suite (zero-dep; exit 0 = pass, 1 = fail)
53:php tests/run.php
54:
55:# 4. Serve locally and exercise the API
56:php -S localhost:8080              # from repo root
57:curl -s -X POST http://localhost:8080/ \
58:  -H 'Content-Type: application/json' \
59:  -d '{"message":"What is PocketRAG?","history":[]}'
60:
61:# If vectors look stale: delete the store and re-sync
62:rm -f data/knowledge.sqlite* && php scripts/sync.php
63:```
64:
65:No Composer, no Makefile, no Docker, no Node scripts. No linter/formatter/static-analyzer is configured.
66:
67:## Code Conventions & Common Patterns
68:
69:- **No namespaces, no PSR-4, no Composer autoload.** Wire modules with `require_once` and module-prefixed global functions in `lib/`.
70:- `declare(strict_types=1)` on **every** PHP file.
71:- **Naming:** `snake_case` for functions/variables; `UPPER_SNAKE` with module prefix for constants (`BM25_K1`, `EMBEDDINGS_TIMEOUT_SECS`, `KNOWLEDGE_MAX_CHUNK_CHARS`); lowercase `.php` filenames. Test files are `*_test.php` (not `*Test.php`).
72:- **Vectors:** store embedding vectors as SQLite BLOBs via `pack('f*')` / `unpack` in `lib/math.php` (`vector_pack` / `vector_unpack`), never as JSON arrays.
73:- **Error handling:** mixed. LLM clients (`groq_`/`ollama_`/`openai_`) throw `RuntimeException`, caught at the top of `index.php` → generic reply + `error` field. `embeddings_get` returns `?array` and rotates keys on 429/403. `rate_limit`, `telemetry`, `sync`, `conversation_reformulate` use fail-open `try/catch Throwable`. `cosine_similarity_precomputed` returns `0.0` + `error_log` on dimension mismatch. Logging is native `error_log()` (no PSR-3).
74:- **Async/concurrency:** none. Synchronous cURL with short timeouts (`EMBEDDINGS_TIMEOUT_SECS=2`, `GROQ_TIMEOUT_SECS=20`, `OLLAMA_TIMEOUT_SECS=30`). Groq client retries twice with exponential backoff + jitter. Sync ingestion uses an atomic transaction (`beginTransaction`/`commit`/`rollBack`) and collects all vectors before any writes. Rate-limit pruning is stochastic (~5%).
75:- **State / auth:** stateless JSON-only API. No sessions, no auth, no CSRF. Conversation history travels in the POST body and is normalized to the last 10 messages.
76:- **Config access:** always via `http_config()` (memoized); never `require` config directly.
77:- **Documentation:** PHPDoc blocks in English on every function (exported and internal helpers) and every class, covering purpose, `@param`, `@return`, `@throws` as applicable. Inline comments only for non-obvious logic, in English. No commented-out code.
78:
79:## Important Files
80:
81:- `index.php` — entry point; procedural controller (GET static, POST RAG pipeline).
82:- `lib/http.php` — `http_config()`, `http_send_cors()`, `http_send_json()`, `http_require_post()`, `http_read_json_body()`.
83:- `lib/retrieval.php` — `retrieval_select()`; orchestrates synonyms → BM25 → sync → embeddings → cosine → hybrid → top-K.
84:- `lib/sync.php` — `sync_knowledge_run()` (CLI ingestion), `sync_knowledge_if_needed()` (freshness gate). See `docs/auto-sync.md`.
85:- `lib/knowledge.php` — `knowledge_parse_frontmatter()`, `knowledge_split_body()` (paragraph-aware, overlap, `[...]` marker), `knowledge_load_chunks()`, `knowledge_build_index()`.
86:- `lib/bm25.php` — native Okapi BM25 (`bm25_fold`, `bm25_tokenize`, `bm25_index`, `bm25_search`); `BM25_K1=1.5`, `BM25_B=0.75`, EN+ES stopwords.
87:- `lib/math.php` — `vector_pack`/`vector_unpack`/`vector_magnitude`/`cosine_similarity_precomputed`.
88:- `lib/embeddings.php` — Gemini client with key-pool rotation (`embeddings_get(): ?array`).
89:- `lib/conversation.php` — `conversation_normalize_history()`, `conversation_reformulate_query()`.
90:- `lib/llm.php` + `lib/groq.php` / `lib/ollama.php` / `lib/openai.php` — LLM dispatch + provider clients.
91:- `config.example.php` — full config key reference (commit-able template).
92:- `.htaccess` — Apache 2.4+ security hardening only (denies `config.php`, `*.sqlite*`, hidden files; `Options -Indexes`); no rewrites.
93:- `docs/README.md` — human docs index (architecture, API, auto-sync, ops, deploy, contributing, components, knowledge, configuration).
94:
95:## Runtime / Tooling Preferences
96:
97:- **PHP 8.0+** (CI runs 8.2). Required extensions: `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`. No Composer.
98:- **No package manager.** Do not add `composer.json`, `composer.lock`, or `vendor/` — CI (`zero-deps.yml`) fails the build if any of these appear.
99:- **No linter/formatter/static analyzer** is configured. Conventions are enforced manually (see above) and via the zero-deps guard.
100:- **Web server:** Apache 2.4+ (`.htaccess`) or PHP built-in server (`php -S`) for local dev.
101:
102:## Testing & QA
103:
104:- **Framework:** custom zero-dependency BDD-style harness in `tests/run.php` (no PHPUnit, no Pest). DSL: `describe()` / `it()` / `expect()` with matchers `toBe`, `toEqual`, `toContain`, `toBeTrue`, `toBeFalse`, `toBeNull`, `toBeGreaterThan`, `toBeLessThan`, `toHaveCount`.
105:- **Run:** `php tests/run.php` (auto-discovers `tests/*_test.php` via glob; exit `0` pass / `1` fail).
106:- **Layout:** flat `tests/` directory, `*_test.php` naming, 1:1 mapping to `lib/` modules (e.g. `lib/bm25.php` → `tests/bm25_test.php`). No Unit/Feature split, no `setUp`/`tearDown` — each `it()` is self-contained with inline literal data.
107:- **No mocking framework.** External calls (Groq/OpenAI/Ollama) are avoided by passing placeholder API keys and asserting the function returns the original input without invoking the LLM.
108:- **No coverage tooling** (no Xdebug/PCOV, no flags, no thresholds).
109:- **CI:** `.github/workflows/tests.yml` (PHP 8.2, ext `sqlite3`/`pdo_sqlite`/`curl`, runs `php tests/run.php` on push/PR to `main`) and `.github/workflows/zero-deps.yml` (fails if `composer.json`/`composer.lock`/`vendor/` appear; runs on push to `main`+`develop` and PRs).
110:- **When adding a lib module**, add a matching `tests/<module>_test.php` so the suite stays 1:1.
111:
112:## Policy (non-negotiable)
113:
114:- Never add Composer, `vendor/`, or third-party PHP packages — zero external dependencies is a hard product constraint. Discuss exceptions before changing this.
115:- Never commit `config.php`, `data/knowledge.sqlite*`, or personal files under `data/knowledge/` (only `data/knowledge/base.md` is tracked). Copy secrets from `config.example.php` locally.
116:- Do not treat `foundation/` as guidance or source of truth; prefer tracked code, `README.md`, and `docs/`.
117:- When changing behavior, public contracts, or configuration surface, update the matching file under `docs/` in the same change (not limited to HTTP/retrieval/deploy).
118:- Conventional Commits in English are expected for commit messages (repo convention; not enforced by tooling).
119:- Git-flow (simplified): `main` = production, `develop` = integration. Branch feature work as `feature/*` from `develop`. PRs target `main`; `develop` is the named integration branch. PR template at `.github/pull_request_template.md` (Summary / Verification / Notes).
120:
121:<!-- /bmad:context -->

## Team Conventions

Authoritative team rules preserved outside the `bmad:context` block so they survive `bmad-project-context` refreshes. If the managed block above restates any of these, this section is the source of truth.

- **Documentation:** PHPDoc blocks in English on every function (exported and internal helpers) and every class, covering purpose, `@param`, `@return`, `@throws` as applicable. Inline comments only for non-obvious logic, in English. No commented-out code.
- **Docs stay current:** When changing behavior, public contracts, or configuration surface, update the matching file under `docs/` in the same change (not limited to HTTP/retrieval/deploy).
- **Git-flow (simplified):** `main` = production, `develop` = integration. Branch feature work as `feature/*` from `develop`. PRs target `main`; `develop` is the named integration branch. PR template at `.github/pull_request_template.md` (Summary / Verification / Notes).
- **Conventional Commits in English** are expected for commit messages (repo convention; not enforced by tooling).