<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/telemetry.php';
require_once dirname(__DIR__) . '/lib/db.php';

// Setup temp DB
$dbPath = __DIR__ . '/test_telemetry.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

// Ensure table exists via db_get_pdo which handles schema autogeneration
db_get_pdo($dbPath);

describe('Telemetry — telemetry_log');

it('logs a request to the database', function () use ($dbPath) {
    telemetry_log(
        $dbPath,
        'user query',
        'search query',
        'hybrid',
        false,
        null,
        5,
        123.45
    );

    $logs = telemetry_get_recent($dbPath, 1);
    expect(count($logs))->toBe(1);
    expect($logs[0]['user_query'])->toBe('user query');
    expect($logs[0]['mode'])->toBe('hybrid');
    expect((float)$logs[0]['duration_ms'])->toBe(123.45);
});

describe('Telemetry — telemetry_get_recent');

it('retrieves recent logs', function () use ($dbPath) {
    // Clear first
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->exec('DELETE FROM rag_telemetry');

    telemetry_log($dbPath, 'q1', 's1', 'hybrid', false, null, 1, 10.0);
    telemetry_log($dbPath, 'q2', 's2', 'bm25_fallback', true, 'timeout', 0, 20.0);

    $logs = telemetry_get_recent($dbPath, 10);
    expect(count($logs))->toBe(2);
    expect($logs[0]['user_query'])->toBe('q2');
    expect($logs[1]['user_query'])->toBe('q1');
});
