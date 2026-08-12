<?php
/**
 * SQLite-based Rate Limiter.
 * 
 * Tracks requests per IP address using a sliding window algorithm stored in SQLite.
 * Designed for shared PHP hosting where Redis/Memcached are unavailable.
 * Controlled by config keys: rate_limit_enabled, rate_limit_rpm, rate_limit_window.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

/**
 * Initialise the rate_limit table in the given database if it does not exist.
 *
 * @param PDO $pdo An active PDO connection.
 */
function rate_limit_init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limit (
            ip          TEXT NOT NULL,
            hit_at      INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_rate_limit_ip_hit ON rate_limit(ip, hit_at);
    ");
}

/**
 * Check whether the given IP has exceeded the configured request rate.
 * 
 * Uses a sliding window: counts rows for this IP within the last `window` seconds.
 * Inserts a new hit row if the limit is not exceeded. Prunes old rows periodically.
 *
 * @param string $dbPath Absolute path to the SQLite database.
 * @param string $ip     The client IP address.
 * @return bool True if the request is within limits, false if rate-limited.
 */
function rate_limit_check(string $dbPath, string $ip): bool
{
    $config  = http_config();
    $enabled = (bool) ($config['rate_limit_enabled'] ?? false);

    if (!$enabled) {
        return true;
    }

    $maxHits = (int) ($config['rate_limit_rpm']    ?? 30);
    $window  = (int) ($config['rate_limit_window'] ?? 60);
    $now     = time();
    $cutoff  = $now - $window;

    try {
        $pdo = db_get_pdo($dbPath);
        rate_limit_init_schema($pdo);

        // Count hits for this IP within the sliding window
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit WHERE ip = :ip AND hit_at > :cutoff'
        );
        $stmt->execute([':ip' => $ip, ':cutoff' => $cutoff]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $maxHits) {
            return false;
        }

        // Record this hit
        $pdo->prepare('INSERT INTO rate_limit (ip, hit_at) VALUES (:ip, :hit_at)')
            ->execute([':ip' => $ip, ':hit_at' => $now]);

        // Prune old rows stochastically (~5% of requests) to avoid unbounded growth
        if (random_int(1, 20) === 1) {
            $pdo->prepare('DELETE FROM rate_limit WHERE hit_at <= :cutoff')
                ->execute([':cutoff' => $cutoff]);
        }

        return true;
    } catch (Throwable $e) {
        // Fail open: if the rate limit DB is unavailable, allow the request
        error_log('rate_limit_check failed: ' . $e->getMessage());
        return true;
    }
}

/**
 * Resolve the real client IP address, respecting trusted proxy headers.
 *
 * @return string The resolved IP address, or '0.0.0.0' as a safe fallback.
 */
function rate_limit_get_ip(): string
{
    $trustedProxies = http_config()['trusted_proxies'] ?? [];
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if ($forwarded !== '' && in_array($remoteAddr, $trustedProxies, true)) {
        $ips = array_map('trim', explode(',', $forwarded));
        for ($i = count($ips) - 1; $i >= 0; $i--) {
            if (!filter_var($ips[$i], FILTER_VALIDATE_IP)) {
                return '0.0.0.0';
            }
            if (!in_array($ips[$i], $trustedProxies, true)) {
                return $ips[$i];
            }
        }
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
}
