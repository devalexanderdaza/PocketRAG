<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/query.php';

describe('Query expansion — query_expansion_parse');

it('parses two variants from JSON', function () {
    $variants = query_expansion_parse('{"variants":["alpha search","beta search"]}');
    expect($variants)->toEqual(['alpha search', 'beta search']);
});

it('ignores extra variants beyond two', function () {
    $variants = query_expansion_parse('{"variants":["a","b","c"]}');
    expect($variants)->toHaveCount(2);
});

it('extracts JSON from surrounding prose', function () {
    $variants = query_expansion_parse("Sure.\n{\"variants\":[\"one query\",\"two query\"]}\n");
    expect($variants[0])->toBe('one query');
});

it('returns empty list for invalid JSON', function () {
    expect(query_expansion_parse('not json'))->toHaveCount(0);
});

describe('Query expansion — query_expansion_variants');

it('returns empty list when disabled', function () {
    $out = query_expansion_variants('What is PocketRAG hybrid search?', [
        'query_expansion_enabled' => false,
        'llm_provider' => 'groq',
        'groq_api_key' => 'gsk_your_groq_api_key_here',
    ]);
    expect($out)->toHaveCount(0);
});

it('returns empty list for short messages', function () {
    $out = query_expansion_variants('hi', [
        'query_expansion_enabled' => true,
        'llm_provider' => 'groq',
        'groq_api_key' => 'gsk_your_groq_api_key_here',
    ]);
    expect($out)->toHaveCount(0);
});
