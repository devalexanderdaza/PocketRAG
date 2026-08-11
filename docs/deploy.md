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

## Security Hardening (Critical)

> **Important:** PocketRAG stores sensitive API keys and user queries. You must secure it.

If you are using Apache, a `.htaccess` file is included in the repository that automatically blocks direct access to `config.php` and your `data/*.sqlite` files.

If you are using Nginx or another web server, you **must** configure it manually.

For full instructions, including rate limiting setup, read the [Security Guide](security.md).

## Post-deploy checklist

1. ✅ PHP 8.1+ active?
2. ✅ `pdo_sqlite` and `curl` enabled?
3. ✅ `data/` directory writable by the web server?
4. ✅ `config.php` populated with keys?
5. ✅ `php scripts/sync.php` ran successfully?
6. ✅ `https://yourdomain.com/config.php` returns a **403 Forbidden** error?
7. ✅ `https://yourdomain.com/data/knowledge.sqlite` returns a **403 Forbidden** error?
8. `curl` POST smoke test against the public URL
9. Confirm `mode: hybrid` on a known question
10. Confirm `data/knowledge.sqlite` created and growing

## Scaling expectations

This MVP scans all Markdown and all stored vectors **per request**. Fine for small knowledge bases on shared hosts. Larger corpora will need retrieval indexes / candidate pruning — not implemented yet.

## CI / promotion

GitHub Actions only guards zero dependencies. There is no deploy workflow in-repo; promotion is copy/rsync/FTP per your host.
