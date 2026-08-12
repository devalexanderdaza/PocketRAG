<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/sync.php';

describe('Sync — sync_knowledge_run');

it('returns zero metrics for empty directory', function () {
    $tempDir = sys_get_temp_dir() . '/rag_test_' . uniqid();
    mkdir($tempDir);

    $metrics = sync_knowledge_run($tempDir, ':memory:', [], 'model', 768, false);

    expect($metrics['processed'])->toBe(0);
    expect($metrics['skipped'])->toBe(0);
    expect($metrics['deleted'])->toBe(0);

    rmdir($tempDir);
});

describe('Sync — sync_webhook_validate');

it('returns false when secret is empty', function () {
    $result = sync_webhook_validate(['sync_webhook_secret' => '']);
    expect($result)->toBe(false);
});

it('returns false when secret is not set', function () {
    $result = sync_webhook_validate([]);
    expect($result)->toBe(false);
});

it('validates HMAC signature correctly', function () {
    $secret = 'test-secret-123';
    $rawBody = '{"action":"sync"}';

    $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    $_SERVER['HTTP_AUTHORIZATION'] = '';

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret], $rawBody);

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    expect($result)->toBe(true);
});

it('rejects invalid HMAC signature', function () {
    $secret = 'test-secret-123';
    $rawBody = '{"action":"sync"}';

    $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=invalidsignature';
    $_SERVER['HTTP_AUTHORIZATION'] = '';

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret], $rawBody);

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    expect($result)->toBe(false);
});

it('validates Bearer token correctly', function () {
    $secret = 'bearer-token-456';

    $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = '';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $secret;

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret]);

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    expect($result)->toBe(true);
});

it('rejects invalid Bearer token', function () {
    $secret = 'bearer-token-456';

    $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = '';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret]);

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    expect($result)->toBe(false);
});

it('rejects when no auth headers present', function () {
    $secret = 'some-secret';

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret]);

    expect($result)->toBe(false);
});

it('prefers HMAC over Bearer when both present', function () {
    $secret = 'shared-secret';

    $rawBody = '{"action":"sync"}';
    $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';

    $result = sync_webhook_validate(['sync_webhook_secret' => $secret], $rawBody);

    unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
    unset($_SERVER['HTTP_AUTHORIZATION']);

    expect($result)->toBe(true);
});
