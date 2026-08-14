<?php
/**
 * Gemini API Embeddings Client.
 * 
 * Handles vector embedding generation with key rotation and timeout management.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/math.php';

const EMBEDDINGS_TIMEOUT_SECS = 2;
const EMBEDDINGS_SYNC_TIMEOUT_SECS = 15;
const EMBEDDINGS_CACHE_TTL_DAYS = 7;

/**
 * Fetch a vector embedding for text using key pool rotation.
 * 
 * Will try multiple keys if rate limited (429) or quota exceeded (403).
 *
 * @param string $text The text to embed.
 * @param list<string> $apiKeys Array of Gemini API keys.
 * @param string $model The model ID to use.
 * @param int $dimensions Output dimensions.
 * @param int $timeoutSecs cURL timeout in seconds.
 * @param string|null $dbPath Optional SQLite path for the query embedding cache.
 * @return list<float>|null Returns array of floats or null on complete failure.
 */
function embeddings_get(
    string $text,
    array $apiKeys,
    string $model = 'gemini-embedding-001',
    int $dimensions = 768,
    int $timeoutSecs = EMBEDDINGS_TIMEOUT_SECS,
    ?string $dbPath = null
): ?array {
    if (trim($text) === '') {
        return null;
    }

    $dbPath = $dbPath ?? dirname(__DIR__) . '/data/knowledge.sqlite';
    $cached = embeddings_cache_get($dbPath, $text, $model, $dimensions);
    if ($cached !== null) {
        return $cached;
    }

    if ($apiKeys === []) {
        return null;
    }

    $payload = [
        'content' => [
            'parts' => [
                ['text' => $text],
            ],
        ],
    ];

    if ($dimensions > 0) {
        $payload['outputDimensionality'] = $dimensions;
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return null;
    }

    foreach ($apiKeys as $key) {
        if (!is_string($key) || trim($key) === '') {
            continue;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':embedContent?key=' . urlencode($key);

        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutSecs,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSecs),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        // Rate limit (429), quota exceeded (403), or server error (5xx): rotate to next key
        if ($status === 429 || $status === 403 || $status >= 500) {
            error_log("embeddings_get: Key rate-limited (HTTP {$status}). Rotating key...");
            continue;
        }

        if ($response === false || $status !== 200) {
            error_log("embeddings_get: HTTP {$status} failure.");
            continue;
        }

        $decoded = json_decode((string) $response, true);
        $values  = $decoded['embedding']['values'] ?? null;

        if (is_array($values) && count($values) > 0) {
            $vector = array_map('floatval', $values);
            embeddings_cache_put($dbPath, $text, $model, $dimensions, $vector);
            return $vector;
        }
    }

    error_log('embeddings_get: All API keys exhausted or failed.');
    return null;
}

/**
 * Build a stable cache key for a query embedding.
 *
 * @param string $text Query text.
 * @param string $model Embedding model id.
 * @param int $dimensions Output dimensions.
 * @return string SHA-256 hex digest.
 */
function embeddings_cache_hash(string $text, string $model, int $dimensions): string
{
    return hash('sha256', $model . '|' . $dimensions . '|' . $text);
}

/**
 * Read a cached query embedding, if present and not expired.
 *
 * @param string $dbPath SQLite path.
 * @param string $text Query text.
 * @param string $model Embedding model id.
 * @param int $dimensions Output dimensions.
 * @return list<float>|null Cached vector or null.
 */
function embeddings_cache_get(string $dbPath, string $text, string $model, int $dimensions): ?array
{
    try {
        $pdo = db_get_pdo($dbPath);
        $hash = embeddings_cache_hash($text, $model, $dimensions);
        $minCreated = time() - (EMBEDDINGS_CACHE_TTL_DAYS * 86400);
        $stmt = $pdo->prepare('SELECT embedding FROM query_cache WHERE query_hash = :hash AND created_at >= :min_created');
        $stmt->execute([':hash' => $hash, ':min_created' => $minCreated]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $vector = vector_unpack_stored((string) $row['embedding']);
        return $vector === [] ? null : $vector;
    } catch (Throwable $e) {
        error_log('embeddings_cache_get: ' . $e->getMessage());
        return null;
    }
}

/**
 * Store a query embedding in the cache (fail-open).
 *
 * @param string $dbPath SQLite path.
 * @param string $text Query text.
 * @param string $model Embedding model id.
 * @param int $dimensions Output dimensions.
 * @param list<float> $vector Embedding values.
 */
function embeddings_cache_put(string $dbPath, string $text, string $model, int $dimensions, array $vector): void
{
    try {
        $pdo = db_get_pdo($dbPath);
        $hash = embeddings_cache_hash($text, $model, $dimensions);
        $stmt = $pdo->prepare('
            INSERT INTO query_cache (query_hash, embedding, vector_magnitude, created_at)
            VALUES (:hash, :embedding, :mag, :created_at)
            ON CONFLICT(query_hash) DO UPDATE SET
                embedding = excluded.embedding,
                vector_magnitude = excluded.vector_magnitude,
                created_at = excluded.created_at
        ');
        $stmt->execute([
            ':hash'       => $hash,
            ':embedding'  => vector_pack_stored($vector, 'f32'),
            ':mag'        => vector_magnitude($vector),
            ':created_at' => time(),
        ]);
        if (random_int(1, 20) === 1) {
            embeddings_cache_prune($dbPath);
        }
    } catch (Throwable $e) {
        error_log('embeddings_cache_put: ' . $e->getMessage());
    }
}

/**
 * Delete query-cache rows older than the TTL.
 *
 * @param string $dbPath SQLite path.
 * @param int $olderThanDays Age threshold in days.
 */
function embeddings_cache_prune(string $dbPath, int $olderThanDays = EMBEDDINGS_CACHE_TTL_DAYS): void
{
    try {
        $pdo = db_get_pdo($dbPath);
        $cutoff = time() - ($olderThanDays * 86400);
        $stmt = $pdo->prepare('DELETE FROM query_cache WHERE created_at < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
    } catch (Throwable $e) {
        error_log('embeddings_cache_prune: ' . $e->getMessage());
    }
}

