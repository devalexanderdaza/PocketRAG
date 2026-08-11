<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bm25.php';
require_once dirname(__DIR__) . '/lib/synonyms.php';

describe('Synonyms — synonyms_expand (no synonyms.json)');

it('returns original query when synonyms.json is absent', function () {
    // synonyms.json does not exist in the test context → expect no expansion
    $result = synonyms_expand('what is pocketrag');
    // Result should at minimum contain the original query
    expect($result)->toContain('what is pocketrag');
});

it('does not crash on empty query', function () {
    $result = synonyms_expand('');
    expect(is_string($result))->toBeTrue();
});
