<?php
/**
 * Context selection: Hybrid RAG Engine (Cosine Similarity + Okapi BM25).
 * 
 * Fallbacks gracefully to BM25 if embedding API is unavailable.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/synonyms.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/math.php';
require_once __DIR__ . '/embeddings.php';
require_once __DIR__ . '/sync.php';
require_once __DIR__ . '/query.php';

const RETRIEVAL_TOP_K = 4;
const RETRIEVAL_CITATION_RATIO = 0.35;
const RETRIEVAL_MAX_CITATIONS = 3;
const RETRIEVAL_RRF_K = 60;

/**
 * Fuse two ranked lists using Reciprocal Rank Fusion.
 *
 * RRF formula: score_rrf[chunk_id] += 1/(k + rank_position)
 * where rank_position is 1-based. Handles chunks present in only one list.
 *
 * @param list<array{id:string,score:float}> $bm25Hits BM25 hits sorted by score descending.
 * @param array<string,float> $cosineScores Cosine scores indexed by chunk ID (already normalized 0-1).
 * @param int $k RRF constant (default 60).
 * @return list<array{id:string,score:float}> Fused and sorted results.
 */
function retrieval_rrf_fuse(array $bm25Hits, array $cosineScores, int $k = RETRIEVAL_RRF_K): array
{
    $rrfScores = [];

    foreach ($bm25Hits as $position => $hit) {
        $rank = $position + 1;
        $id = $hit['id'];
        $rrfScores[$id] = ($rrfScores[$id] ?? 0.0) + (1.0 / ($k + $rank));
    }

    $cosineIds = array_keys($cosineScores);
    $cosineSorted = $cosineScores;
    arsort($cosineSorted);
    foreach (array_keys($cosineSorted) as $position => $id) {
        $rank = $position + 1;
        $rrfScores[$id] = ($rrfScores[$id] ?? 0.0) + (1.0 / ($k + $rank));
    }

    $fused = [];
    foreach ($rrfScores as $id => $score) {
        $fused[] = ['id' => $id, 'score' => $score];
    }

    usort($fused, static function (array $a, array $b): int {
        return $b['score'] <=> $a['score'];
    });

    return $fused;
}

/**
 * Merge several cosine score maps with Reciprocal Rank Fusion.
 *
 * @param list<array<string,float>> $maps Per-query id => score maps.
 * @param int $k RRF k constant.
 * @return array<string,float> Fused scores keyed by chunk id.
 */
function retrieval_rrf_merge_maps(array $maps, int $k = RETRIEVAL_RRF_K): array
{
    $rrfScores = [];
    foreach ($maps as $map) {
        arsort($map);
        $rank = 1;
        foreach ($map as $id => $score) {
            $rrfScores[$id] = ($rrfScores[$id] ?? 0.0) + (1.0 / ($k + $rank));
            $rank++;
        }
    }
    return $rrfScores;
}

/**
 * Select top K context chunks using Hybrid Search (Cosine + BM25).
 * 
 * Computes individual BM25 and Cosine similarity scores, normalizes them,
 * and combines them into a hybrid score. Fallbacks to pure BM25 on API failure.
 *
 * @param list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int,file?:string,heading?:?string,line?:int}> $chunks Array of all knowledge chunks.
 * @param array{docs:list<array{id:string,len:int,freqs:array<string,int>}>,df:array<string,int>,avgdl:float,n:int} $index Precomputed BM25 index.
 * @param string $message The standalone query to search for.
 * @param string $flowId Optional conversational flow ID.
 * @param array{slug:string|null,tags:list<string>|null}|null $filter Optional pre-filter for multi-tenancy (slug = exact match, tags = OR logic for LIKE matches).
 * @return array{context:string,sources:list<array{id:string,label:string,snippet:string,score:float,file:?string,heading:?string,line:?int}>,mode:string,fallback_occurred:bool,fallback_reason:string|null} Retrieval results.
 */
function retrieval_select(
    array $chunks,
    array $index,
    string $message,
    string $flowId = '',
    ?array $filter = null
): array {
    $byId = [];
    foreach ($chunks as $chunk) {
        $byId[$chunk['id']] = $chunk;
    }

    $expandedQuery = synonyms_expand($message);

    // Step 1: Execute BM25 search
    $bm25Hits = bm25_search($index, $expandedQuery);
    $bm25Scores = [];
    $maxBm25 = 0.0001;

    foreach ($bm25Hits as $hit) {
        if ($hit['score'] > 0.0) {
            $bm25Scores[$hit['id']] = $hit['score'];
            if ($hit['score'] > $maxBm25) {
                $maxBm25 = $hit['score'];
            }
        }
    }

    // Step 2: Attempt Vector Search via Gemini API
    $config = http_config();

    $apiKeys = $config['gemini_api_keys'] ?? [];
    $model = $config['gemini_model'] ?? 'gemini-embedding-001';
    $dimensions = (int) ($config['gemini_dimensions'] ?? 768);
    $dbPath = dirname(__DIR__) . '/data/knowledge.sqlite';
    $knowledgeDir = dirname(__DIR__) . '/data/knowledge';
    $autoSync = (bool) ($config['auto_sync_on_retrieval'] ?? true);

    // Freshness Check & Auto-Sync (if enabled)
    if ($autoSync) {
        sync_knowledge_if_needed($knowledgeDir, $dbPath, $apiKeys, $model, $dimensions);
    }

    $queryVector = embeddings_get($expandedQuery, $apiKeys, $model, $dimensions);
    $cosineMaps = [];

    $scoreCosineAgainstDb = static function (?array $queryVector, PDO $pdo, float $queryMag, string $sql, array $params): array {
        if ($queryVector === null) {
            return [];
        }
        $cosineScores = [];
        $stmt = $pdo->prepare('SELECT id, embedding, vector_magnitude FROM knowledge_chunks' . $sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            $chunkVec = vector_unpack_stored((string) $row['embedding']);
            $sim = cosine_similarity_precomputed(
                $queryVector,
                $queryMag,
                $chunkVec,
                (float) $row['vector_magnitude']
            );
            $cosineScores[$row['id']] = ($sim + 1.0) / 2.0;
        }
        return $cosineScores;
    };

    $cosineScores = [];

    if ($queryVector !== null && is_file($dbPath)) {
        try {
            $pdo = db_get_pdo($dbPath);
            $queryMag = vector_magnitude($queryVector);
            [$sql, $params] = retrieval_build_filter_sql($filter);
            $cosineMaps[] = $scoreCosineAgainstDb($queryVector, $pdo, $queryMag, $sql, $params);

            $variants = query_expansion_variants($expandedQuery, $config);
            foreach ($variants as $variant) {
                $variantVector = embeddings_get($variant, $apiKeys, $model, $dimensions);
                if ($variantVector === null) {
                    continue;
                }
                $cosineMaps[] = $scoreCosineAgainstDb(
                    $variantVector,
                    $pdo,
                    vector_magnitude($variantVector),
                    $sql,
                    $params
                );
            }

            if (count($cosineMaps) === 1) {
                $cosineScores = $cosineMaps[0];
            } elseif (count($cosineMaps) > 1) {
                $cosineScores = retrieval_rrf_merge_maps($cosineMaps);
            }
        } catch (Exception $e) {
            error_log('retrieval_select: Vector DB read error: ' . $e->getMessage());
        }
    }

    // Step 3: Compute Hybrid Scores
    $strategy = $config['hybrid_strategy'] ?? 'rrf';

    if ($cosineScores === []) {
        $ranked = [];
        foreach ($bm25Scores as $id => $score) {
            if (!isset($byId[$id])) {
                continue;
            }
            $ranked[] = ['id' => $id, 'score' => $score / $maxBm25];
        }
    } elseif ($strategy === 'rrf') {
        $bm25Hits = [];
        foreach ($bm25Scores as $id => $score) {
            $bm25Hits[] = ['id' => $id, 'score' => $score];
        }
        usort($bm25Hits, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $ranked = retrieval_rrf_fuse($bm25Hits, $cosineScores);
    } else {
        $ranked = [];
        $allCandidateIds = array_unique(array_merge(array_keys($bm25Scores), array_keys($cosineScores)));
        foreach ($allCandidateIds as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $normCosine = $cosineScores[$id] ?? 0.0;
            $normBm25 = isset($bm25Scores[$id]) ? ($bm25Scores[$id] / $maxBm25) : 0.0;
            $hybridScore = (0.7 * $normCosine) + (0.3 * $normBm25);
            $ranked[] = ['id' => $id, 'score' => $hybridScore];
        }
    }

    $ranked = retrieval_apply_priority($ranked, $byId);
    $top = array_slice($ranked, 0, RETRIEVAL_TOP_K);

    if ($top === []) {
        $top = retrieval_default_chunks($chunks);
    }

    $topScore = $top[0]['score'] ?? 0.0;
    $citationFloor = $topScore * RETRIEVAL_CITATION_RATIO;

    $contextParts = [];
    $sources = [];
    $seenSlugs = [];

    foreach ($top as $hit) {
        $chunk = $byId[$hit['id']] ?? null;
        if ($chunk === null) {
            continue;
        }

        $contextParts[] = $chunk['title'] . ":\n" . $chunk['content'];

        if (isset($seenSlugs[$chunk['slug']])) {
            continue;
        }
        if ($hit['score'] <= 0.0 || $hit['score'] < $citationFloor || count($sources) >= RETRIEVAL_MAX_CITATIONS) {
            continue;
        }

        $seenSlugs[$chunk['slug']] = true;
        $sources[] = [
            'id'      => $chunk['slug'],
            'label'   => $chunk['title'],
            'snippet' => retrieval_snippet($chunk['content']),
            'score'   => round($hit['score'], 3),
            'file'    => isset($chunk['file']) ? (string) $chunk['file'] : null,
            'heading' => $chunk['heading'] ?? null,
            'line'    => isset($chunk['line']) ? (int) $chunk['line'] : null,
        ];
    }

    $isFallback = ($cosineScores === []);
    $mode = $isFallback ? 'bm25_fallback' : 'hybrid';
    $fallbackReason = $isFallback ? ($queryVector === null ? 'Gemini API unavailable / timeout' : 'No vectors in DB') : null;

    return [
        'context'           => implode("\n\n", $contextParts),
        'sources'           => $sources,
        'mode'              => $mode,
        'fallback_occurred' => $isFallback,
        'fallback_reason'   => $fallbackReason,
    ];
}

/**
 * Apply priority weighting to the ranked hits.
 * 
 * Boosts scores of chunks with a priority higher than 5.
 *
 * @param list<array{id:string,score:float}> $ranked The currently ranked hits.
 * @param array<string,array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> $byId Associative array of chunks indexed by ID.
 * @return list<array{id:string,score:float}> Re-ranked hits.
 */
function retrieval_apply_priority(array $ranked, array $byId): array
{
    foreach ($ranked as $position => $hit) {
        $priority = $byId[$hit['id']]['priority'] ?? 5;
        $ranked[$position]['score'] = $hit['score'] * (1.0 + (($priority - 5) * 0.05));
    }

    usort($ranked, static function (array $a, array $b): int {
        return $b['score'] <=> $a['score'];
    });

    return $ranked;
}

/**
 * Provide default fallback chunks if no search results match.
 * 
 * Falls back to chunks whose slug matches any entry in config 'default_fallback_slugs'.
 * If none match, returns the first available chunk.
 *
 * @param list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> $chunks All available chunks.
 * @return list<array{id:string,score:float}> The default fallback hits.
 */
function retrieval_default_chunks(array $chunks): array
{
    $config        = http_config();
    $fallbackSlugs = (array) ($config['default_fallback_slugs'] ?? ['profile', 'cv', 'about']);

    $picked = [];
    foreach ($fallbackSlugs as $slug) {
        $slug = (string) $slug;
        foreach ($chunks as $chunk) {
            if ($chunk['slug'] === $slug) {
                $picked[] = ['id' => $chunk['id'], 'score' => 0.0];
                break;
            }
        }
    }

    if ($picked === [] && $chunks !== []) {
        $picked[] = ['id' => $chunks[0]['id'], 'score' => 0.0];
    }

    return $picked;
}

/**
 * Generate a concise snippet from chunk content for UI display.
 *
 * @param string $content The full markdown content of the chunk.
 * @param int $limit Maximum number of characters for the snippet.
 * @return string The truncated snippet.
 */
function retrieval_snippet(string $content, int $limit = 140): string
{
    $flat = trim((string) preg_replace('/\s+/', ' ', $content));
    if (mb_strlen($flat, 'UTF-8') <= $limit) {
        return $flat;
    }
    return rtrim(mb_substr($flat, 0, $limit, 'UTF-8')) . '…';
}

/**
 * Build a parameterized SQL WHERE clause from a filter array.
 * 
 * @param array{slug:string|null,tags:list<string>|null}|null $filter The filter specification.
 * @return array{0:string,1:list<string>} Tuple of [SQL clause (with leading space), parameter values].
 */
function retrieval_build_filter_sql(?array $filter): array
{
    if ($filter === null) {
        return ['', []];
    }

    $conditions = [];
    $params = [];

    if (isset($filter['slug']) && $filter['slug'] !== '') {
        $conditions[] = 'slug = ?';
        $params[] = $filter['slug'];
    }

    if (isset($filter['tags']) && is_array($filter['tags'])) {
        foreach ($filter['tags'] as $tag) {
            if ($tag !== '') {
                $conditions[] = 'tags LIKE ?';
                $params[] = '%' . $tag . '%';
            }
        }
    }

    if ($conditions === []) {
        return ['', []];
    }

    return [' WHERE ' . implode(' AND ', $conditions), $params];
}


