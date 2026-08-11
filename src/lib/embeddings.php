<?php
/**
 * Gemini API Embeddings Client with Key Rotation & Timeout.
 */

declare(strict_types=1);

const EMBEDDINGS_TIMEOUT_SECS = 2;
const EMBEDDINGS_SYNC_TIMEOUT_SECS = 15;

/**
 * Fetch vector embedding for text using key pool rotation.
 *
 * @param list<string> $apiKeys
 * @return list<float>|null Returns array of floats or null on complete failure.
 */
function embeddings_get(
    string $text,
    array $apiKeys,
    string $model = 'gemini-embedding-001',
    int $dimensions = 768,
    int $timeoutSecs = EMBEDDINGS_TIMEOUT_SECS
): ?array {
    if (trim($text) === '' || $apiKeys === []) {
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

        // Rate limit (429) or quota exceeded (403): rotate to next key
        if ($status === 429 || $status === 403) {
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
            return array_map('floatval', $values);
        }
    }

    error_log('embeddings_get: All API keys exhausted or failed.');
    return null;
}
