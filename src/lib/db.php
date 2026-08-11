<?php
/**
 * SQLite3 Database Wrapper using PDO.
 * Autogenerates schema if missing and handles WAL mode configuration.
 */

declare(strict_types=1);

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
    ");

    $pdoMap[$dbPath] = $pdo;
    return $pdo;
}
