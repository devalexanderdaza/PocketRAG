<?php
/**
 * Ollama Local LLM cURL Client.
 * 
 * Connects to Ollama API (OpenAI-compatible endpoint) to generate completions.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

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

    $ch = curl_init($endpoint !== '' ? $endpoint : 'http://localhost:11434/v1/chat/completions');
    if ($ch === false) {
        throw new RuntimeException('Could not initialise cURL for Ollama');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    unset($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Ollama API error HTTP {$status}");
    }

    $decoded = json_decode((string) $response, true);
    return trim($decoded['choices'][0]['message']['content'] ?? '');
}
