<!-- bmad:context -->
<!-- Verified 2026-08-10 against 97b8e7d7d866282910ffce67830988a38021b96d. Managed by bmad-project-context; edits inside this block are replaced on refresh. Keep anything you want preserved outside the markers. -->

## PocketRAG

Zero-dependency hybrid RAG engine for shared PHP hosting. PHP 8+ with PDO SQLite, native BM25 + Gemini embeddings + Groq — no Composer. Product docs live in `README.md`; planning artifacts (when present) under `_bmad-output/`.

## Policy

- Never add Composer, `vendor/`, or third-party PHP packages — zero external dependencies is a hard product constraint. Discuss exceptions before changing this.
- Never commit `config.php`, `data/knowledge.sqlite*`, or personal files under `data/knowledge/` (only `data/knowledge/base.md` is tracked). Copy secrets from `config.example.php` locally.
- Do not treat `foundation/` as guidance or source of truth; prefer tracked code and `README.md`.

## Where things are

- Chat/API endpoint: `index.php` (POST JSON `message` + optional `history`/`messages`)
- Ingest CLI: `scripts/sync.php`; sync/retrieval core in `lib/sync.php` and `lib/retrieval.php`
- Library modules: `lib/` (prefixed globals: `bm25_`, `knowledge_`, `retrieval_`, …)
- Knowledge Markdown: `data/knowledge/*.md`

## Running and verifying

- No automated PHP test suite yet — verify with `php scripts/sync.php`, then a manual PHP built-in server and `curl` POST against `index.php`.
- Example local serve: `php -S localhost:8080` from the repo root.
- CI also runs the zero-deps guard (fails if `composer.json` or `vendor/` appears).

## Conventions that differ from defaults

- No namespaces or Composer autoload — wire with `require_once` and module-prefixed functions in `lib/`.
- Keep `declare(strict_types=1)` on every PHP file.
- Store embedding vectors as SQLite BLOBs via `pack('f*')` / unpack in `lib/math.php`, not JSON arrays.

<!-- /bmad:context -->
