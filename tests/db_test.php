<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

describe('DB — db_get_pdo');

it('creates a database and initializes schema', function () {
    $tmpDir = __DIR__ . '/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir);
    $tmpDb = $tmpDir . '/test.db';
    if (is_file($tmpDb)) unlink($tmpDb);
    
    $pdo = db_get_pdo($tmpDb);
    expect(get_class($pdo))->toBe(PDO::class);
    
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='knowledge_chunks'");
    $result = $stmt->fetch();
    expect($result !== false)->toBe(true);
    
    unlink($tmpDb);
    rmdir($tmpDir);
});
