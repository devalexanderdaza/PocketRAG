<?php
/**
 * Ollama Local LLM cURL Client.
 * 
 * Connects to Ollama API (OpenAI-compatible endpoint) to generate completions.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

const OLLAMA_TIMEOUT_SECS = 30;

/**
 * Generate a completion from an Ollama instance.
 *
 * @param list<array{role:string,content:string}> $messages The message history.
 * @param string $endpoint The Ollama API endpoint URL (e.g. http://localhost:11434/v1/chat/completions).
 * @param string $model The Ollama model name (e.g. llama3.2).
 * @param int $maxTokens The maximum tokens to generate.
 * @param float $temperature The generation temperature.
 * @return string The completed response text.
 * @throws RuntimeException If the cURL request fails.
 */
function ollama_complete(
    array $messages,
    string $endpoint = 'http://localhost:11434/v1/chat/completions',
    string $model = 'llama3.2',
    int $maxTokens = 512,
    float $temperature = 0.7
): string {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $resolvedEndpoint = $endpoint !== '' ? $endpoint : 'http://localhost:11434/v1/chat/completions';
    $ch = curl_init($resolvedEndpoint);
    if ($ch === false) {
        throw new RuntimeException('Could not initialise cURL for Ollama');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => OLLAMA_TIMEOUT_SECS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Ollama API error HTTP {$status}");
    }

    $decoded = json_decode((string) $response, true);

    // Detect structured API error response
    if (isset($decoded['error'])) {
        $errMsg  = (string) ($decoded['error']['message'] ?? 'Unknown error');
        $errType = (string) ($decoded['error']['type'] ?? '');
        throw new RuntimeException("Ollama API error ({$errType}): {$errMsg}");
    }

    return trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
}
