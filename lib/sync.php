<?php
/**
 * Shared Knowledge Sync Library.
 * 
 * Handles Markdown to SQLite Vector Synchronization.
 * Reused by CLI scripts and Auto-Sync (Freshness Check).
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/math.php';
require_once __DIR__ . '/embeddings.php';

/**
 * Synchronize knowledge markdown files to the SQLite vector database.
 * 
 * Embeds markdown content and stores it in the database.
 * Skips files that have not changed according to model and dimensions.
 *
 * @param string $knowledgeDir Path to the markdown files.
 * @param string $dbPath Path to the SQLite database.
 * @param list<string> $apiKeys Gemini API keys.
 * @param string $model Embedding model.
 * @param int $targetDimensions Output dimensions.
 * @param bool $verbose Print output to console.
 * @return array{processed:int,skipped:int,deleted:int} Sync metrics.
 */
function sync_knowledge_run(
    string $knowledgeDir,
    string $dbPath,
    array $apiKeys,
    string $model,
    int $targetDimensions,
    bool $verbose = false
): array {
    if (!is_dir($knowledgeDir)) {
        return ['processed' => 0, 'skipped' => 0, 'deleted' => 0];
    }

    $pdo = db_get_pdo($dbPath);
    $chunks = knowledge_load_chunks($knowledgeDir);

    if ($verbose) {
        echo "Loading Markdown files from {$knowledgeDir}...\n";
        echo "Loaded " . count($chunks) . " total chunks.\n";
    }

    $stmt = $pdo->query('SELECT id, content_hash, embedding_model, dimensions FROM knowledge_chunks');
    $existing = [];
    while ($row = $stmt->fetch()) {
        $existing[$row['id']] = $row;
    }

    $processed = 0;
    $skipped = 0;

    $insertStmt = $pdo->prepare('
        INSERT INTO knowledge_chunks (
            id, slug, title, tags, priority, content, content_hash, embedding, vector_magnitude, embedding_model, dimensions, created_at
        ) VALUES (
            :id, :slug, :title, :tags, :priority, :content, :content_hash, :embedding, :vector_magnitude, :embedding_model, :dimensions, :created_at
        ) ON CONFLICT(id) DO UPDATE SET
            title = excluded.title,
            tags = excluded.tags,
            priority = excluded.priority,
            content = excluded.content,
            content_hash = excluded.content_hash,
            embedding = excluded.embedding,
            vector_magnitude = excluded.vector_magnitude,
            embedding_model = excluded.embedding_model,
            dimensions = excluded.dimensions,
            created_at = excluded.created_at
    ');

    foreach ($chunks as $chunk) {
        $id = $chunk['id'];
        $contentHash = md5($chunk['content']);

        if (
            isset($existing[$id])
            && ($existing[$id]['content_hash'] ?? '') === $contentHash
            && $existing[$id]['embedding_model'] === $model
            && (int) $existing[$id]['dimensions'] === $targetDimensions
        ) {
            $skipped++;
            continue;
        }

        if ($verbose) {
            echo "Vectorizing chunk {$id}...\n";
        }

        $vector = embeddings_get(
            $chunk['content'],
            $apiKeys,
            $model,
            $targetDimensions,
            5
        );

        if ($vector === null) {
            if ($verbose) {
                echo "Error vectorizing chunk {$id}. Skipping.\n";
            }
            continue;
        }

        $magnitude = vector_magnitude($vector);
        $blob = vector_pack($vector);

        $insertStmt->execute([
            ':id'               => $chunk['id'],
            ':slug'             => $chunk['slug'],
            ':title'            => $chunk['title'],
            ':tags'             => $chunk['tags'],
            ':priority'         => $chunk['priority'],
            ':content'          => $chunk['content'],
            ':content_hash'     => $contentHash,
            ':embedding'        => $blob,
            ':vector_magnitude' => $magnitude,
            ':embedding_model'  => $model,
            ':dimensions'       => count($vector),
            ':created_at'       => time(),
        ]);

        $processed++;
    }

    // Clean up orphaned chunks
    $validIds = array_fill_keys(array_column($chunks, 'id'), true);
    $deleteStmt = $pdo->prepare('DELETE FROM knowledge_chunks WHERE id = :id');
    $deleted = 0;

    foreach ($existing as $id => $row) {
        if (!isset($validIds[$id])) {
            $deleteStmt->execute([':id' => $id]);
            $deleted++;
        }
    }

    if ($verbose) {
        echo "Sync Complete! Processed: {$processed}, Skipped: {$skipped}, Deleted Orphaned: {$deleted}\n";
    }

    return [
        'processed' => $processed,
        'skipped'   => $skipped,
        'deleted'   => $deleted,
    ];
}

/**
 * Perform a freshness check and sync if any markdown file is newer than the DB.
 *
 * @param string $knowledgeDir Path to the markdown files.
 * @param string $dbPath Path to the SQLite database.
 * @param list<string> $apiKeys Gemini API keys.
 * @param string $model Embedding model.
 * @param int $targetDimensions Output dimensions.
 */
function sync_knowledge_if_needed(
    string $knowledgeDir,
    string $dbPath,
    array $apiKeys,
    string $model,
    int $targetDimensions
): void {
    if (!is_dir($knowledgeDir)) {
        return;
    }

    $dbMtime = is_file($dbPath) ? filemtime($dbPath) : 0;
    $needsSync = false;

    $files = glob($knowledgeDir . '/*.md');
    if ($files !== false) {
        foreach ($files as $file) {
            if (filemtime($file) > $dbMtime) {
                $needsSync = true;
                break;
            }
        }
    }

    if ($needsSync) {
        try {
            sync_knowledge_run($knowledgeDir, $dbPath, $apiKeys, $model, $targetDimensions, false);
        } catch (Throwable $e) {
            error_log('sync_knowledge_if_needed failed: ' . $e->getMessage());
        }
    }
}
