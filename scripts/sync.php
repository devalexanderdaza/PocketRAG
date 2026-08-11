<?php
/**
 * Idempotent Ingestion Script: Markdown Files -> SQLite Vector Storage.
 * 
 * Run via CLI: php scripts/sync.php
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/sync.php';

$config = http_config();

$dbPath = dirname(__DIR__) . '/data/knowledge.sqlite';
$knowledgeDir = dirname(__DIR__) . '/data/knowledge';

$apiKeys = $config['gemini_api_keys'] ?? [];
$model = $config['gemini_model'] ?? 'gemini-embedding-001';
$targetDimensions = (int) ($config['gemini_dimensions'] ?? 768);

echo "PocketRAG Ingestion Pipeline Started\n";
sync_knowledge_run($knowledgeDir, $dbPath, $apiKeys, $model, $targetDimensions, true);
