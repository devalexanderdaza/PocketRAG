<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/sync.php';

describe('Sync — sync_knowledge_run');

it('returns zero metrics for empty directory', function () {
    // Create a temporary empty directory
    $tempDir = sys_get_temp_dir() . '/rag_test_' . uniqid();
    mkdir($tempDir);

    $metrics = sync_knowledge_run($tempDir, ':memory:', [], 'model', 768, false);
    
    expect($metrics['processed'])->toBe(0);
    expect($metrics['skipped'])->toBe(0);
    expect($metrics['deleted'])->toBe(0);

    rmdir($tempDir);
});
