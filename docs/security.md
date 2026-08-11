# Security Hardening Guide

PocketRAG is designed to be easily deployable on shared hosting, which inherently carries security risks if not configured correctly. This guide provides instructions on securing your installation.

## Critical Risks

PocketRAG stores sensitive information in plain text:
1. `config.php`: Contains API keys for Groq, OpenAI, and Gemini.
2. `data/knowledge.sqlite`: Contains all your embeddings (knowledge base) and telemetry logs (including user queries).

If these files are accessible via HTTP, your API keys and private data can be stolen.

## 1. Web Server Protection (Required)

You must prevent direct HTTP access to the configuration and database files.

### Apache
If you deploy to an Apache server, a `.htaccess` file is already provided in the repository root. This file automatically blocks access to `config.php` and `data/*.sqlite`. Ensure your host allows `.htaccess` overrides (specifically `AllowOverride Limit` or `All`).

To verify it is working, visit `http://yourdomain.com/config.php`. You should receive a 403 Forbidden error, not a blank page.

### Nginx
If you use Nginx, `.htaccess` files are ignored. You must add the following to your server block:

```nginx
location ~ ^/(config\.php|data/.*\.sqlite(-wal|-shm|-journal)?)$ {
    deny all;
    return 403;
}
```

## 2. Rate Limiting

PocketRAG includes a built-in, SQLite-based rate limiter to protect against abuse and API quota exhaustion. It uses a sliding window algorithm based on the client's IP address.

To enable it, edit your `config.php`:

```php
    'rate_limit_enabled' => true,
    'rate_limit_rpm'     => 30, // Maximum requests allowed
    'rate_limit_window'  => 60, // Time window in seconds
```

When a user exceeds the limit, PocketRAG returns a `429 Too Many Requests` status with a `Retry-After` header.

> **Note on Reverse Proxies:** If your PocketRAG instance is behind Cloudflare or another reverse proxy, the built-in rate limiter will use `HTTP_X_FORWARDED_FOR` to identify the true client IP. Ensure your server is configured to only trust proxy headers from known sources.

## 3. Storage Location (Alternative Approach)

The most robust way to secure your data is to move it out of the public web root entirely.

1. Move the `data` directory and `config.php` to a parent folder not served by your web server (e.g., `/var/www/private/`).
2. Update `lib/http.php` and `index.php` to reference the new paths.
