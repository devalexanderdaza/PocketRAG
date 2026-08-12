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
