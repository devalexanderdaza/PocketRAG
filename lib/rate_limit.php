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
 * @param string $dbPath Absolute path to the SQLite database.
 * @param string $ip     The client IP address.
 * @return bool True if the request is within limits, false if rate-limited.
 */
function rate_limit_check(string $dbPath, string $ip): bool
{
    try {
        if (!(http_config()['rate_limit_enabled'] ?? false)) {
            return true;
        }

        $pdo = db_get_pdo($dbPath);
        rate_limit_init_schema($pdo); // Ensure schema exists

        // Stochastic pruning
        if (rand(1, 20) === 1) {
            $window = http_config()['rate_limit_window'] ?? 60;
            $pdo->prepare("DELETE FROM rate_limit WHERE hit_at < ?")
                ->execute([time() - $window]);
        }

        $window = http_config()['rate_limit_window'] ?? 60;
        $limit = http_config()['rate_limit_rpm'] ?? 30;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limit WHERE ip = ? AND hit_at > ?");
        $stmt->execute([$ip, time() - $window]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= $limit) {
            return false;
        }

        $stmt = $pdo->prepare("INSERT INTO rate_limit (ip, hit_at) VALUES (?, ?)");
        $stmt->execute([$ip, time()]);
        return true;
    } catch (Throwable $e) {
        error_log('rate_limit_check: ' . $e->getMessage());
        return true; // Fail open
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
            // Validate IP format
            if (!filter_var($ips[$i], FILTER_VALIDATE_IP)) {
                return '0.0.0.0'; // Fail on malformed IP
            }
            if (!in_array($ips[$i], $trustedProxies, true)) {
                return $ips[$i];
            }
        }
    }
    
    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
}
