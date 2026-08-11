<?php
/**
 * Groq Chat Completion cURL Client.
 */

declare(strict_types=1);

const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_TIMEOUT_SECS = 20;

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

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Groq API error HTTP {$status}");
    }

    $decoded = json_decode((string) $response, true);
    return trim($decoded['choices'][0]['message']['content'] ?? '');
}
