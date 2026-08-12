<?php
/**
 * OpenAI Chat Completion cURL Client.
 * 
 * Connects to OpenAI API to generate completions.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TIMEOUT_SECS = 20;

/**
 * Generate a completion from OpenAI API.
 *
 * @param list<array{role:string,content:string}> $messages The message history.
 * @param string $apiKey The OpenAI API key.
 * @param string $model The OpenAI model name.
 * @param int $maxTokens The maximum tokens to generate.
 * @param float $temperature The generation temperature.
 * @return string The completed response text.
 * @throws RuntimeException If the cURL request fails or returns non-2xx status.
 */
function openai_complete(
    array $messages,
    string $apiKey,
    string $model = 'gpt-4o-mini',
    int $maxTokens = 512,
    float $temperature = 0.7
): string {
    if (trim($apiKey) === '') {
        throw new RuntimeException('OpenAI API key is missing.');
    }

    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init(OPENAI_ENDPOINT);
    if ($ch === false) {
        throw new RuntimeException('Could not initialise cURL for OpenAI');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => OPENAI_TIMEOUT_SECS,
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

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("OpenAI API error HTTP {$status}");
    }

    $decoded = json_decode((string) $response, true);

    // Detect structured API error response
    if (isset($decoded['error'])) {
        $errMsg  = (string) ($decoded['error']['message'] ?? 'Unknown error');
        $errType = (string) ($decoded['error']['type'] ?? '');
        throw new RuntimeException("OpenAI API error ({$errType}): {$errMsg}");
    }

    return trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
}
