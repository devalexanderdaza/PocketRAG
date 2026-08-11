# Deploy

PocketRAG is designed as a **drop-in PHP tree** for shared hosting (cPanel, HostGator-style, etc.): upload files, point the domain/document root at the app, ensure SQLite + cURL work.

## What to upload

Include:

- `index.php`
- `lib/`
- `scripts/` (optional on the server if you sync elsewhere)
- `data/knowledge/` (at least your Markdown)
- `config.php` created **on the server** from `config.example.php` (do not upload a laptop config with the wrong keys)

Do **not** rely on Composer install steps — there are none.

## Document root

Prefer the repository root as the web root so `/index.php` is reachable. If the host forces `public_html/`, either:

- Place the project contents there, or
- Symlink / alias `index.php` + `lib/` + `data/` accordingly (keep relative paths intact: code uses `dirname(__DIR__)` from `lib/`).

## Permissions

| Path | Need |
|---|---|
| `data/` | Writable by the PHP user (create SQLite + WAL) |
| `data/knowledge/` | Readable |
| `config.php` | Readable by PHP; not world-writable if avoidable |

## PHP extensions

Enable: `pdo_sqlite`, `curl`, `mbstring`, `json` (usually default).

## Secrets on shared hosting

Common pattern: keep `config.php` outside the web root and require it — **current code expects `config.php` next to `index.php`**. If you relocate it, change `http_config()` / sync path resolution together. Until then, protect `config.php` with host rules (deny HTTP access) if the tree is web-visible.

Suggested Apache fragment (if allowed):

```apache
<Files "config.php">
  Require all denied
</Files>
```

Deny web access to `data/*.sqlite*` similarly when possible.

## Post-deploy checklist

1. `config.php` present with real Groq + Gemini keys
2. Knowledge Markdown deployed
3. Run `php scripts/sync.php` over SSH/CLI, or hit chat once and allow auto-sync (CLI preferred)
4. `curl` POST smoke test against the public URL
5. Confirm `mode: hybrid` on a known question
6. Confirm `data/knowledge.sqlite` created and growing

## Scaling expectations

This MVP scans all Markdown and all stored vectors **per request**. Fine for small knowledge bases on shared hosts. Larger corpora will need retrieval indexes / candidate pruning — not implemented yet.

## CI / promotion

GitHub Actions only guards zero dependencies. There is no deploy workflow in-repo; promotion is copy/rsync/FTP per your host.
