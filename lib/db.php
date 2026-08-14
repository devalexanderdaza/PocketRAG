<?php
/**
 * SQLite3 Database Wrapper using PDO.
 * 
 * Autogenerates schema if missing and handles WAL mode configuration for performance.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

/**
 * Get a PDO instance for the specified database path.
 * 
 * Handles directory creation, sets error modes, and configures SQLite pragmas.
 * Uses a static map to reuse connections within the same request.
 *
 * @param string $dbPath The absolute path to the SQLite database file.
 * @return PDO The connected PDO instance.
 */
function db_get_pdo(string $dbPath): PDO
{
    static $pdoMap = [];

    if (isset($pdoMap[$dbPath])) {
        return $pdoMap[$dbPath];
    }

    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Performance & concurrency optimizations for SQLite
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    // Autogenerate schema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS knowledge_chunks (
            id TEXT PRIMARY KEY,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            tags TEXT NOT NULL,
            priority INTEGER DEFAULT 5,
            content TEXT NOT NULL,
            content_hash TEXT NOT NULL DEFAULT '',
            embedding BLOB NOT NULL,
            vector_magnitude REAL NOT NULL,
            embedding_model TEXT NOT NULL,
            dimensions INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_slug ON knowledge_chunks(slug);

        CREATE TABLE IF NOT EXISTS rag_telemetry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_query TEXT NOT NULL,
            search_query TEXT NOT NULL,
            mode TEXT NOT NULL, -- 'hybrid' or 'bm25_fallback'
            fallback_occurred INTEGER NOT NULL DEFAULT 0,
            fallback_reason TEXT,
            sources_count INTEGER NOT NULL,
            duration_ms REAL NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_telemetry_created ON rag_telemetry(created_at);

        CREATE TABLE IF NOT EXISTS query_cache (
            query_hash TEXT PRIMARY KEY,
            embedding BLOB NOT NULL,
            vector_magnitude REAL NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_query_cache_created ON query_cache(created_at);
    ");

    // Migration: Ensure content_hash column exists on legacy databases
    try {
        $stmt = $pdo->query("PRAGMA table_info(knowledge_chunks)");
        $columns = $stmt->fetchAll();
        $hasContentHash = false;
        foreach ($columns as $col) {
            if (($col['name'] ?? '') === 'content_hash') {
                $hasContentHash = true;
                break;
            }
        }
        if (!$hasContentHash) {
            $pdo->exec("ALTER TABLE knowledge_chunks ADD COLUMN content_hash TEXT NOT NULL DEFAULT ''");
        }
    } catch (Throwable $e) {
        // Log non-fatal migration error for visibility in the PHP error log
        error_log('db_get_pdo: content_hash migration skipped: ' . $e->getMessage());
    }

    $pdoMap[$dbPath] = $pdo;
    return $pdo;
}
