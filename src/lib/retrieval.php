<?php
/**
 * Context selection: Hybrid RAG Engine (Cosine Similarity + Okapi BM25).
 * 
 * Fallbacks gracefully to BM25 if embedding API is unavailable.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/synonyms.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/math.php';
require_once __DIR__ . '/embeddings.php';
require_once __DIR__ . '/sync.php';

const RETRIEVAL_TOP_K = 4;
const RETRIEVAL_CITATION_RATIO = 0.35;
const RETRIEVAL_MAX_CITATIONS = 3;

/**
 * Select top K context chunks using Hybrid Search (Cosine + BM25).
 * 
 * Computes individual BM25 and Cosine similarity scores, normalizes them,
 * and combines them into a hybrid score. Fallbacks to pure BM25 on API failure.
 *
 * @param list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> $chunks Array of all knowledge chunks.
 * @param array{docs:list<array{id:string,len:int,freqs:array<string,int>}>,df:array<string,int>,avgdl:float,n:int} $index Precomputed BM25 index.
 * @param string $message The standalone query to search for.
 * @param string $flowId Optional conversational flow ID.
 * @return array{context:string,sources:list<array{id:string,label:string,snippet:string,score:float}>,mode:string,fallback_occurred:bool,fallback_reason:string|null} Retrieval results.
 */
function retrieval_select(
    array $chunks,
    array $index,
    string $message,
    string $flowId = ''
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
    $configPath = dirname(__DIR__) . '/config.php';
    if (!is_file($configPath)) {
        $configPath = dirname(__DIR__) . '/config.example.php';
    }
    $config = require $configPath;

    $apiKeys = $config['gemini_api_keys'] ?? [];
    $model = $config['gemini_model'] ?? 'gemini-embedding-001';
    $dimensions = (int) ($config['gemini_dimensions'] ?? 768);
    $dbPath = dirname(__DIR__) . '/data/knowledge.sqlite';
    $knowledgeDir = dirname(__DIR__) . '/data/knowledge';

    // Freshness Check & Auto-Sync
    sync_knowledge_if_needed($knowledgeDir, $dbPath, $apiKeys, $model, $dimensions);

    $queryVector = embeddings_get($expandedQuery, $apiKeys, $model, $dimensions);
    $cosineScores = [];

    if ($queryVector !== null && is_file($dbPath)) {
        try {
            $pdo = db_get_pdo($dbPath);
            $queryMag = vector_magnitude($queryVector);

            $stmt = $pdo->query('SELECT id, embedding, vector_magnitude FROM knowledge_chunks');
            while ($row = $stmt->fetch()) {
                $chunkVec = vector_unpack($row['embedding']);
                $sim = cosine_similarity_precomputed(
                    $queryVector,
                    $queryMag,
                    $chunkVec,
                    (float) $row['vector_magnitude']
                );

                // Normalise Cosine Score from [-1, 1] to [0, 1]
                $cosineScores[$row['id']] = ($sim + 1.0) / 2.0;
            }
        } catch (Exception $e) {
            error_log('retrieval_select: Vector DB read error: ' . $e->getMessage());
        }
    }

    // Step 3: Compute Hybrid Scores
    $ranked = [];
    $allCandidateIds = array_unique(array_merge(array_keys($bm25Scores), array_keys($cosineScores)));

    foreach ($allCandidateIds as $id) {
        if (!isset($byId[$id])) {
            continue;
        }

        $normCosine = $cosineScores[$id] ?? 0.0;
        $normBm25 = isset($bm25Scores[$id]) ? ($bm25Scores[$id] / $maxBm25) : 0.0;

        // Hybrid formula: 70% Cosine + 30% BM25
        if ($cosineScores !== []) {
            $hybridScore = (0.7 * $normCosine) + (0.3 * $normBm25);
        } else {
            // Fallback to pure BM25 if vector search was unavailable
            $hybridScore = $normBm25;
        }

        $ranked[] = ['id' => $id, 'score' => $hybridScore];
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
 * Defaults to "profile" or "cv" chunks if available.
 *
 * @param list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> $chunks All available chunks.
 * @return list<array{id:string,score:float}> The default fallback hits.
 */
function retrieval_default_chunks(array $chunks): array
{
    $picked = [];
    foreach (['profile', 'cv'] as $slug) {
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


