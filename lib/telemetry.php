<?php
/**
 * Telemetry & Logging Library for RAG Engine.
 * 
 * Logs query metrics, retrieval mode (hybrid vs bm25_fallback), latency, and fallbacks to SQLite.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

/**
 * Log a RAG request to the rag_telemetry SQLite table.
 *
 * @param string $dbPath Path to the SQLite database.
 * @param string $userQuery The user's original query.
 * @param string $searchQuery The standalone query used for retrieval.
 * @param string $mode The retrieval mode ('hybrid' or 'bm25_fallback').
 * @param bool $fallbackOccurred True if fallback occurred.
 * @param string|null $fallbackReason Reason for the fallback.
 * @param int $sourcesCount Number of sources retrieved.
 * @param float $durationMs Request duration in milliseconds.
 */
function telemetry_log(
    string $dbPath,
    string $userQuery,
    string $searchQuery,
    string $mode,
    bool $fallbackOccurred,
    ?string $fallbackReason,
    int $sourcesCount,
    float $durationMs
): void {
    $config = http_config();
    $telemetryEnabled = (bool) ($config['telemetry_enabled'] ?? true);

    if (!$telemetryEnabled) {
        return;
    }

    try {
        $pdo = db_get_pdo($dbPath);
        $stmt = $pdo->prepare('
            INSERT INTO rag_telemetry (
                user_query, search_query, mode, fallback_occurred, fallback_reason, sources_count, duration_ms, created_at
            ) VALUES (
                :user_query, :search_query, :mode, :fallback_occurred, :fallback_reason, :sources_count, :duration_ms, :created_at
            )
        ');

        $stmt->execute([
            ':user_query'        => $userQuery,
            ':search_query'      => $searchQuery,
            ':mode'              => $mode,
            ':fallback_occurred' => $fallbackOccurred ? 1 : 0,
            ':fallback_reason'   => $fallbackReason,
            ':sources_count'     => $sourcesCount,
            ':duration_ms'       => $durationMs,
            ':created_at'        => time(),
        ]);

        if (random_int(1, 20) === 1) {
            telemetry_prune($dbPath);
        }
    } catch (Throwable $e) {
        error_log('telemetry_log failed: ' . $e->getMessage());
    }
}

/**
 * Retrieve recent telemetry logs from SQLite.
 *
 * @param string $dbPath Path to the SQLite database.
 * @param int $limit Maximum number of logs to return.
 * @param int $since Optional unix timestamp; only return logs created at or after this time.
 * @return list<array<string,mixed>> The telemetry logs.
 */
function telemetry_get_recent(string $dbPath, int $limit = 50, int $since = 0): array
{
    try {
        $pdo = db_get_pdo($dbPath);
        if ($since > 0) {
            $stmt = $pdo->prepare('SELECT * FROM rag_telemetry WHERE created_at >= :since ORDER BY id DESC LIMIT :limit');
            $stmt->bindValue(':since', $since, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM rag_telemetry ORDER BY id DESC LIMIT :limit');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('telemetry_get_recent failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Prune telemetry logs older than the specified number of days.
 *
 * @param string $dbPath Path to the SQLite database.
 * @param int $olderThanDays Delete logs older than this many days (default 30).
 */
function telemetry_prune(string $dbPath, int $olderThanDays = 30): void
{
    try {
        $pdo = db_get_pdo($dbPath);
        $cutoff = time() - ($olderThanDays * 86400);
        $stmt = $pdo->prepare('DELETE FROM rag_telemetry WHERE created_at < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
    } catch (Throwable $e) {
        error_log('telemetry_prune failed: ' . $e->getMessage());
    }
}
