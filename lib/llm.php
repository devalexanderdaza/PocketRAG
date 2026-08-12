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

    // Mock detection for all providers
    $groqApiKey    = (string) ($config['groq_api_key'] ?? '');
    $openaiApiKey  = (string) ($config['openai_api_key'] ?? '');
    $ollamaEndpoint = (string) ($config['ollama_endpoint'] ?? '');
    $ollamaModel   = (string) ($config['ollama_model'] ?? '');
    $ollamaApiKey  = (string) ($config['ollama_api_key'] ?? '');

    if ($provider === 'groq' && ($groqApiKey === '' || str_contains($groqApiKey, 'your_groq_api_key'))) {
        return '[MOCK RESPONSE]: Please configure your LLM API key in config.php to receive live responses.';
    }
    if ($provider === 'openai' && ($openaiApiKey === '' || str_contains($openaiApiKey, 'your_openai_api_key'))) {
        return '[MOCK RESPONSE]: Please configure your LLM API key in config.php to receive live responses.';
    }
    if ($provider === 'ollama' && (
        (str_contains($ollamaEndpoint, 'localhost') && $ollamaModel === '') ||
        str_contains($ollamaApiKey, 'your_ollama')
    )) {
        return '[MOCK RESPONSE]: Please configure your LLM API key in config.php to receive live responses.';
    }
    // Default case (unsupported provider) uses groq — check groq key for mock
    if ($provider !== 'groq' && $provider !== 'openai' && $provider !== 'ollama'
        && ($groqApiKey === '' || str_contains($groqApiKey, 'your_groq_api_key'))
    ) {
        return '[MOCK RESPONSE]: Please configure your LLM API key in config.php to receive live responses.';
    }

    switch ($provider) {
        case 'ollama':
            $endpoint = $ollamaEndpoint !== '' ? $ollamaEndpoint : 'http://localhost:11434/v1/chat/completions';
            $model    = $ollamaModel !== '' ? $ollamaModel : 'llama3.2';
            return ollama_complete($messages, $endpoint, $model, $maxTokens, $temperature);

        case 'openai':
            $apiKey = $openaiApiKey;
            $model  = (string) ($config['openai_model'] ?? 'gpt-4o-mini');
            return openai_complete($messages, $apiKey, $model, $maxTokens, $temperature);

        case 'groq':
        default:
            $apiKey = $groqApiKey;
            $model  = (string) ($config['groq_model'] ?? 'llama-3.3-70b-versatile');
            return groq_complete($messages, $apiKey, $model, $maxTokens, $temperature);
    }
}
