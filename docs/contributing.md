# Contributing

## Product rules (non-negotiable)

1. **Zero external PHP dependencies** — no Composer, no `vendor/`, no third-party packages. Discuss exceptions before proposing them. CI fails the PR if `composer.json` / `composer.lock` / `vendor/` appear.
2. **Do not commit secrets or generated DBs** — `config.php`, `data/knowledge.sqlite*`, and personal files under `data/knowledge/` (except `base.md`).
3. **Do not treat `foundation/` as guidance** — prefer tracked code, `README.md`, and this `docs/` tree.
4. **Keep `declare(strict_types=1)`** on every PHP file.
5. **No namespaces / Composer autoload** — add functions with a clear module prefix in `lib/` and `require_once` from callers.

## Dev loop

```bash
cp config.example.php config.php   # once
# edit knowledge under data/knowledge/
php scripts/sync.php
php -S localhost:8080
# curl POST against index.php — see docs/ops.md and docs/api.md
```

There is no PHPUnit suite yet. Manual verification is required before opening a PR that touches retrieval, sync, or HTTP behavior.

## Code style

| Topic | Convention |
|---|---|
| Structure | One concern per `lib/*.php` file; prefix all exported functions |
| Vectors | Store as SQLite BLOBs via `pack('f*')` (`lib/math.php`), not JSON arrays |
| Errors | Prefer graceful degradation (BM25 fallback, mock Groq) over hard crashes on optional APIs |
| Docs | Update the relevant file under `docs/` when behavior or contracts change |

## Pull requests

- Prefer small, focused PRs.
- Describe **why**, verification steps (`sync` + `curl` outcome), and any config/knowledge fixtures needed.
- Do not add dependency manifests “just for DX.”
- If you change the HTTP JSON contract, update [API](api.md) in the same PR.

## Synonyms and domain bias

`lib/synonyms.php` and `lib/prompt.php` currently reflect a portfolio-assistant framing. When generalizing PocketRAG, treat those as **customization points**, not universal product copy.

## Agent / automation notes

Repository agent instructions live in `AGENTS.md`. Human architecture and ops detail belong here under `docs/`, not duplicated as large prose inside agent instruction files.

## License

README states MIT. Confirm the `LICENSE` file in the repository root of the published project when distributing.
