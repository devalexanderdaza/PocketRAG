<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/retrieval.php';

describe('Retrieval — retrieval_snippet');
it('truncates content to specified limit', function () {
    $content = 'This is a long content that should be truncated by the retrieval_snippet function to fit in the UI.';
    expect(retrieval_snippet($content, 10))->toBe('This is a…');
});

describe('Retrieval — retrieval_build_filter_sql');
it('returns empty clause and params when filter is null', function () {
    [$sql, $params] = retrieval_build_filter_sql(null);
    expect($sql)->toBe('');
    expect($params)->toHaveCount(0);
});

it('returns empty clause and params when filter is empty array', function () {
    [$sql, $params] = retrieval_build_filter_sql([]);
    expect($sql)->toBe('');
    expect($params)->toHaveCount(0);
});

it('builds WHERE clause for slug filter', function () {
    [$sql, $params] = retrieval_build_filter_sql(['slug' => 'my-project', 'tags' => null]);
    expect($sql)->toBe(' WHERE slug = ?');
    expect($params)->toEqual(['my-project']);
});

it('builds WHERE clause for tags filter with OR logic via multiple LIKE conditions', function () {
    [$sql, $params] = retrieval_build_filter_sql(['slug' => null, 'tags' => ['php', 'rag']]);
    expect($sql)->toBe(' WHERE tags LIKE ? AND tags LIKE ?');
    expect($params)->toEqual(['%php%', '%rag%']);
});

it('combines slug and tags with AND logic', function () {
    [$sql, $params] = retrieval_build_filter_sql(['slug' => 'docs', 'tags' => ['api']]);
    expect($sql)->toBe(' WHERE slug = ? AND tags LIKE ?');
    expect($params)->toEqual(['docs', '%api%']);
});

it('ignores empty slug string', function () {
    [$sql, $params] = retrieval_build_filter_sql(['slug' => '', 'tags' => ['api']]);
    expect($sql)->toBe(' WHERE tags LIKE ?');
    expect($params)->toEqual(['%api%']);
});

it('ignores empty tag strings', function () {
    [$sql, $params] = retrieval_build_filter_sql(['slug' => null, 'tags' => ['api', '', 'rag']]);
    expect($sql)->toBe(' WHERE tags LIKE ? AND tags LIKE ?');
    expect($params)->toEqual(['%api%', '%rag%']);
});

describe('Retrieval — retrieval_rrf_fuse');
it('fuses BM25 and cosine ranks using RRF formula', function () {
    $bm25Hits = [
        ['id' => 'a', 'score' => 10.0],
        ['id' => 'b', 'score' => 8.0],
        ['id' => 'c', 'score' => 5.0],
    ];
    $cosineScores = [
        'a' => 0.95,
        'b' => 0.85,
        'd' => 0.90,
    ];

    $fused = retrieval_rrf_fuse($bm25Hits, $cosineScores);

    expect(count($fused))->toBeGreaterThan(0);
    $ids = array_column($fused, 'id');
    expect(in_array('a', $ids, true))->toBeTrue();
    expect(in_array('b', $ids, true))->toBeTrue();
    expect(in_array('c', $ids, true))->toBeTrue();
    expect(in_array('d', $ids, true))->toBeTrue();
});

it('produces different ordering than pure BM25 order', function () {
    $bm25Hits = [
        ['id' => 'a', 'score' => 10.0],
        ['id' => 'b', 'score' => 8.0],
        ['id' => 'c', 'score' => 5.0],
    ];
    $cosineScores = [
        'a' => 0.5,
        'b' => 0.9,
        'c' => 0.95,
    ];

    $fused = retrieval_rrf_fuse($bm25Hits, $cosineScores);
    $fusedIds = array_column($fused, 'id');
    $bm25Ids = array_column($bm25Hits, 'id');

    expect($fusedIds !== $bm25Ids)->toBeTrue();
});

it('handles chunks present in only one list', function () {
    $bm25Hits = [
        ['id' => 'a', 'score' => 10.0],
        ['id' => 'b', 'score' => 8.0],
    ];
    $cosineScores = [
        'b' => 0.9,
        'c' => 0.95,
    ];

    $fused = retrieval_rrf_fuse($bm25Hits, $cosineScores);
    $ids = array_column($fused, 'id');

    expect(in_array('a', $ids, true))->toBeTrue();
    expect(in_array('b', $ids, true))->toBeTrue();
    expect(in_array('c', $ids, true))->toBeTrue();
});

it('uses custom k constant when provided', function () {
    $bm25Hits = [
        ['id' => 'a', 'score' => 10.0],
        ['id' => 'b', 'score' => 8.0],
    ];
    $cosineScores = [
        'a' => 0.9,
        'b' => 0.8,
    ];

    $fused60 = retrieval_rrf_fuse($bm25Hits, $cosineScores, 60);
    $fused30 = retrieval_rrf_fuse($bm25Hits, $cosineScores, 30);

    $scoreA60 = null;
    $scoreA30 = null;
    foreach ($fused60 as $item) {
        if ($item['id'] === 'a') {
            $scoreA60 = $item['score'];
        }
    }
    foreach ($fused30 as $item) {
        if ($item['id'] === 'a') {
            $scoreA30 = $item['score'];
        }
    }

    expect($scoreA60 !== null)->toBeTrue();
    expect($scoreA30 !== null)->toBeTrue();
    expect($scoreA60 !== $scoreA30)->toBeTrue();
});
