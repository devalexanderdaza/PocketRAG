<?php
/**
 * Conversation & History Utilities.
 * 
 * Handles message normalization and query reformulation for Conversational RAG.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/llm.php';

/**
 * Format and sanitize an incoming conversation history array.
 * 
 * Filters out invalid messages and ensures only 'user' and 'assistant' roles are kept.
 * Retains a maximum of 10 recent messages.
 *
 * @param mixed $rawHistory The raw history payload from the API request.
 * @return list<array{role:string,content:string}> Normalized history array.
 */
function conversation_normalize_history(mixed $rawHistory): array
{
    if (!is_array($rawHistory)) {
        return [];
    }

    $normalized = [];
    foreach ($rawHistory as $msg) {
        if (!is_array($msg)) {
            continue;
        }

        $role = trim((string) ($msg['role'] ?? ''));
        $content = trim((string) ($msg['content'] ?? ''));

        if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
            $normalized[] = [
                'role'    => $role,
                'content' => $content,
            ];
        }
    }

    return array_slice($normalized, -10); // Keep last 10 messages max
}

/**
 * Build a standalone query from conversation history and the latest user message.
 * 
 * Uses the configured LLM provider (via llm_complete) to reformulate the user's
 * latest message into a self-contained standalone query suitable for RAG retrieval.
 * If history is empty or the provider is not configured, returns the original message.
 *
 * @param string $latestMessage The latest message from the user.
 * @param list<array{role:string,content:string}> $history The normalized conversation history.
 * @param array<string,mixed> $config The full application configuration array.
 * @return string The reformulated standalone query or the original message.
 */
function conversation_reformulate_query(
    string $latestMessage,
    array $history,
    array $config
): string {
    if ($history === []) {
        return $latestMessage;
    }

    // Quick-exit: detect unconfigured Groq key when Groq is the active provider
    $provider = strtolower((string) ($config['llm_provider'] ?? 'groq'));
    if ($provider === 'groq') {
        $apiKey = (string) ($config['groq_api_key'] ?? '');
        if ($apiKey === '' || str_contains($apiKey, 'your_groq_api_key')) {
            return $latestMessage;
        }
    }

    $historyText = '';
    foreach ($history as $msg) {
        $historyText .= ucfirst($msg['role']) . ': ' . $msg['content'] . "\n";
    }

    $promptMessages = [
        [
            'role'    => 'system',
            'content' => 'Given the following conversation history and the latest user question, '
                . 'reformulate the latest question so that it becomes an independent and self-sufficient '
                . 'standalone query to search in a RAG knowledge base. '
                . 'DO NOT answer the question, only return the reformulated query. '
                . 'If the latest question is already clear and self-sufficient, return it exactly as it is.',
        ],
        [
            'role'    => 'user',
            'content' => "Conversation history:\n{$historyText}\nLatest question: {$latestMessage}\nReformulated query:",
        ],
    ];

    try {
        $standaloneQuery = trim(llm_complete($promptMessages, $config, 128, 0.0));
        return $standaloneQuery !== '' ? $standaloneQuery : $latestMessage;
    } catch (Throwable $e) {
        error_log('conversation_reformulate_query failed: ' . $e->getMessage());
        return $latestMessage;
    }
}
