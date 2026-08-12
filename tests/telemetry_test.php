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

it('filters logs by since timestamp', function () use ($dbPath) {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->exec('DELETE FROM rag_telemetry');

    $now = time();
    telemetry_log($dbPath, 'old_query', 'old_search', 'hybrid', false, null, 1, 10.0);

    $pdo->exec("UPDATE rag_telemetry SET created_at = $now WHERE user_query = 'old_query'");

    $oneHourAgo = $now - 3600;
    $logs = telemetry_get_recent($dbPath, 50, $oneHourAgo);
    expect(count($logs))->toBe(1);
    expect($logs[0]['user_query'])->toBe('old_query');

    $oneHourAhead = $now + 3600;
    $logs = telemetry_get_recent($dbPath, 50, $oneHourAhead);
    expect(count($logs))->toBe(0);
});

describe('Telemetry — telemetry_prune');

it('deletes logs older than specified days', function () use ($dbPath) {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->exec('DELETE FROM rag_telemetry');

    telemetry_log($dbPath, 'recent', 's', 'hybrid', false, null, 1, 10.0);
    $pdo->exec('UPDATE rag_telemetry SET created_at = 0 WHERE user_query = "recent"');

    telemetry_log($dbPath, 'old', 's', 'hybrid', false, null, 1, 10.0);

    telemetry_prune($dbPath, 30);

    $logs = telemetry_get_recent($dbPath, 50);
    expect(count($logs))->toBe(1);
    expect($logs[0]['user_query'])->toBe('old');
});

it('prunes nothing when all logs are recent', function () use ($dbPath) {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->exec('DELETE FROM rag_telemetry');

    telemetry_log($dbPath, 'log1', 's', 'hybrid', false, null, 1, 10.0);
    telemetry_log($dbPath, 'log2', 's', 'hybrid', false, null, 1, 10.0);

    telemetry_prune($dbPath, 30);

    $logs = telemetry_get_recent($dbPath, 50);
    expect(count($logs))->toBe(2);
});

describe('Telemetry — stochastic pruning');

it('telemetry_prune is called on stochastic trigger', function () {
    $tmpDir = __DIR__ . '/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir);
    }
    $stochasticDb = $tmpDir . '/test_stochastic_prune.sqlite';
    if (file_exists($stochasticDb)) {
        unlink($stochasticDb);
    }

    db_get_pdo($stochasticDb);

    $pdo = new PDO("sqlite:$stochasticDb");
    $pdo->exec('DELETE FROM rag_telemetry');

    $pdo->exec("INSERT INTO rag_telemetry (user_query, search_query, mode, fallback_occurred, fallback_reason, sources_count, duration_ms, created_at) VALUES ('old1', 's', 'hybrid', 0, NULL, 1, 10.0, 0)");
    $pdo->exec("INSERT INTO rag_telemetry (user_query, search_query, mode, fallback_occurred, fallback_reason, sources_count, duration_ms, created_at) VALUES ('old2', 's', 'hybrid', 0, NULL, 1, 10.0, 0)");

    telemetry_prune($stochasticDb, 30);

    $logs = telemetry_get_recent($stochasticDb, 50);
    expect(count($logs))->toBe(0);

    unlink($stochasticDb);
});
