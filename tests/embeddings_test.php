<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/embeddings.php';

describe('Embeddings — embeddings_get');

it('returns null for empty text', function () {
    expect(embeddings_get('', ['key1']))->toBeNull();
});

it('returns null for empty API keys', function () {
    expect(embeddings_get('test text', []))->toBeNull();
});

it('returns null for whitespace-only text', function () {
    expect(embeddings_get('   ', ['key1']))->toBeNull();
});

it('serves a cached embedding without calling Gemini', function () {
    $dir = sys_get_temp_dir() . '/pocketrag-emb-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    $dbPath = $dir . '/cache.sqlite';
    $vector = [0.1, 0.2, 0.3];
    embeddings_cache_put($dbPath, 'cached query', 'gemini-embedding-001', 3, $vector);
    $hit = embeddings_get('cached query', [], 'gemini-embedding-001', 3, 2, $dbPath);
    expect($hit !== null)->toBeTrue();
    expect(count($hit))->toBe(3);
});
