<?php
/**
 * Groq Chat Completion cURL Client.
 * 
 * Connects to the Groq API to generate chat completions using specified models.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_TIMEOUT_SECS = 20;

/**
 * Generate a completion from Groq API.
 *
 * @param list<array{role:string,content:string}> $messages The message history.
 * @param string $apiKey The Groq API key.
 * @param string $model The Groq model name.
 * @param int $maxTokens The maximum tokens to generate.
 * @param float $temperature The generation temperature.
 * @return string The completed response text.
 * @throws RuntimeException If the cURL request fails or returns a non-2xx status.
 */
function groq_complete(
    array $messages,
    string $apiKey,
    string $model,
    int $maxTokens = 512,
    float $temperature = 0.7
): string {
    $payload = json_encode([
        'model'       => $model,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
        'messages'    => $messages,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $maxRetries = 2;
    $attempt = 0;
    $response = false;
    $status = 0;

    while ($attempt < $maxRetries) {
        $attempt++;
        $ch = curl_init(GROQ_ENDPOINT);
        if ($ch === false) {
            throw new RuntimeException('Could not initialise cURL for Groq');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => GROQ_TIMEOUT_SECS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($response !== false && $status >= 200 && $status < 300) {
            break;
        }

        // Retry on 429 (rate limit) or 5xx server errors if attempts remain
        if ($attempt < $maxRetries && ($status === 429 || $status >= 500 || $response === false)) {
            // Exponential backoff with jitter: 500ms * 2^attempt + 0-200ms random
            $delayMs = (int) (500 * (2 ** ($attempt - 1))) + random_int(0, 200);
            usleep($delayMs * 1000);
        }
    }

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Groq API error HTTP {$status}");
    }

    $decoded = json_decode((string) $response, true);

    // Detect structured API error response (e.g. rate limit message body)
    if (isset($decoded['error'])) {
        $errMsg  = (string) ($decoded['error']['message'] ?? 'Unknown error');
        $errType = (string) ($decoded['error']['type'] ?? '');
        throw new RuntimeException("Groq API error ({$errType}): {$errMsg}");
    }

    return trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
}
