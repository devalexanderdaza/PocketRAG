<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bm25.php';

describe('BM25 — bm25_fold');

it('lowercases ASCII', function () {
    expect(bm25_fold('Hello World'))->toBe('hello world');
});

it('removes accents from common Spanish chars', function () {
    // ñ folds to n, accented vowels fold to their base vowel
    expect(bm25_fold('áéíóú'))->toBe('aeiou');
    expect(bm25_fold('ñ'))->toBe('n');
});

it('handles empty string', function () {
    expect(bm25_fold(''))->toBe('');
});

describe('BM25 — bm25_tokenize');

it('splits into tokens and removes stopwords', function () {
    $tokens = bm25_tokenize('a quick brown fox');
    // 'a' is in BM25_STOPWORDS, so it should NOT appear in output
    expect(in_array('a', $tokens, true))->toBeFalse();
    // 'quick' is not a stopword
    expect(in_array('quick', $tokens, true))->toBeTrue();
});

it('returns empty array for all-stopword input', function () {
    // 'a' and 'and' are both in BM25_STOPWORDS — count is 0
    $tokens = bm25_tokenize('a and');
    expect(in_array('a', $tokens, true))->toBeFalse();
    expect(in_array('and', $tokens, true))->toBeFalse();
});

it('strips non-alphanumeric characters', function () {
    $tokens = bm25_tokenize('hello, world!');
    expect(in_array('hello', $tokens, true))->toBeTrue();
    expect(in_array('world', $tokens, true))->toBeTrue();
});

describe('BM25 — bm25_index + bm25_search');

it('builds index with correct n and avgdl', function () {
    $index = bm25_index([
        ['id' => 'a', 'text' => 'foo bar baz'],
        ['id' => 'b', 'text' => 'foo qux'],
    ]);
    expect($index['n'])->toBe(2);
    expect($index['avgdl'])->toBeGreaterThan(0.0);
});

it('returns empty results for empty index', function () {
    $index = bm25_index([]);
    expect(bm25_search($index, 'anything'))->toHaveCount(0);
});

it('returns empty results for empty query', function () {
    $index = bm25_index([['id' => 'a', 'text' => 'hello world']]);
    expect(bm25_search($index, ''))->toHaveCount(0);
});

it('scores matching document above non-matching', function () {
    $index  = bm25_index([
        ['id' => 'match',    'text' => 'retrieval augmented generation'],
        ['id' => 'nomatch',  'text' => 'cooking recipes pasta'],
    ]);
    $results = bm25_search($index, 'retrieval generation');
    expect($results[0]['id'])->toBe('match');
    expect($results[0]['score'])->toBeGreaterThan(0.0);
});

it('results are sorted descending by score', function () {
    $index   = bm25_index([
        ['id' => 'a', 'text' => 'php pdo sqlite database'],
        ['id' => 'b', 'text' => 'php language programming'],
        ['id' => 'c', 'text' => 'cooking pasta italy'],
    ]);
    $results = bm25_search($index, 'php sqlite database');
    $scores  = array_column($results, 'score');
    $sorted  = $scores;
    rsort($sorted);
    expect($scores)->toEqual($sorted);
});
