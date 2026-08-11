<?php
/**
 * Decoupled LLM Provider Dispatcher.
 * 
 * Routes completion requests to active provider ('groq', 'ollama', or 'openai').
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/groq.php';
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/openai.php';

/**
 * Dispatch completion request to configured LLM provider.
 *
 * @param list<array{role:string,content:string}> $messages The message history.
 * @param array<string, mixed> $config The application configuration array.
 * @param int $maxTokens The maximum tokens to generate.
 * @param float $temperature The generation temperature.
 * @return string The generated completion response.
 * @throws RuntimeException If provider execution fails.
 */
function llm_complete(
    array $messages,
    array $config,
    int $maxTokens = 512,
    float $temperature = 0.7
): string {
    $provider = strtolower((string) ($config['llm_provider'] ?? 'groq'));

    switch ($provider) {
        case 'ollama':
            $endpoint = (string) ($config['ollama_endpoint'] ?? 'http://localhost:11434/v1/chat/completions');
            $model    = (string) ($config['ollama_model'] ?? 'llama3.2');
            return ollama_complete($messages, $endpoint, $model, $maxTokens, $temperature);

        case 'openai':
            $apiKey = (string) ($config['openai_api_key'] ?? '');
            $model  = (string) ($config['openai_model'] ?? 'gpt-4o-mini');
            return openai_complete($messages, $apiKey, $model, $maxTokens, $temperature);

        case 'groq':
        default:
            $apiKey = (string) ($config['groq_api_key'] ?? '');
            $model  = (string) ($config['groq_model'] ?? 'llama-3.3-70b-versatile');
            if ($apiKey === '' || str_contains($apiKey, 'your_groq_api_key')) {
                return '[MOCK RESPONSE]: Please configure your LLM API key in config.php to receive live responses.';
            }
            return groq_complete($messages, $apiKey, $model, $maxTokens, $temperature);
    }
}
