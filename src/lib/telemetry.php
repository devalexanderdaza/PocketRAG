<?php
/**
 * Telemetry & Logging Library for RAG Engine.
 * Logs query metrics, retrieval mode (hybrid vs bm25_fallback), latency, and fallbacks to SQLite.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Log a RAG request to the rag_telemetry SQLite table.
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
    } catch (Throwable $e) {
        error_log('telemetry_log failed: ' . $e->getMessage());
    }
}

/**
 * Retrieve recent telemetry logs from SQLite.
 *
 * @return list<array<string,mixed>>
 */
function telemetry_get_recent(string $dbPath, int $limit = 50): array
{
    try {
        $pdo = db_get_pdo($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM rag_telemetry ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('telemetry_get_recent failed: ' . $e->getMessage());
        return [];
    }
}
