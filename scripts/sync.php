<?php
/**
 * Idempotent Ingestion Script: Markdown Files -> SQLite Vector Storage.
 * 
 * Run via CLI: php scripts/sync.php
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/sync.php';

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    $configPath = dirname(__DIR__) . '/config.example.php';
}

$config = require $configPath;

$dbPath = dirname(__DIR__) . '/data/knowledge.sqlite';
$knowledgeDir = dirname(__DIR__) . '/data/knowledge';

$apiKeys = $config['gemini_api_keys'] ?? [];
$model = $config['gemini_model'] ?? 'gemini-embedding-001';
$targetDimensions = (int) ($config['gemini_dimensions'] ?? 768);

echo "PocketRAG Ingestion Pipeline Started\n";
sync_knowledge_run($knowledgeDir, $dbPath, $apiKeys, $model, $targetDimensions, true);
