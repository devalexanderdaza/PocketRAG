<?php
/**
 * Conversation & History Utilities: Message handling & Query Reformulation for Conversational RAG.
 */

declare(strict_types=1);

require_once __DIR__ . '/groq.php';

/**
 * Format and sanitize an incoming history array.
 *
 * @param mixed $rawHistory
 * @return list<array{role:string,content:string}>
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
 * If history is empty, returns the original message.
 *
 * @param list<array{role:string,content:string}> $history
 */
function conversation_reformulate_query(
    string $latestMessage,
    array $history,
    string $apiKey,
    string $model = 'llama-3.3-70b-versatile'
): string {
    if ($history === [] || $apiKey === '' || str_contains($apiKey, 'your_groq_api_key')) {
        return $latestMessage;
    }

    $historyText = '';
    foreach ($history as $msg) {
        $historyText .= ucfirst($msg['role']) . ": " . $msg['content'] . "\n";
    }

    $promptMessages = [
        [
            'role' => 'system',
            'content' => 'Dada la siguiente conversación y la última pregunta del usuario, reformula la última pregunta para que sea una consulta independiente y autosuficiente (standalone query) en español para buscar en una base de conocimientos RAG. NO respondas a la pregunta, únicamente devuelve la consulta reformulada. Si la última pregunta ya es clara y autosuficiente, devuélvela exactamente como está.'
        ],
        [
            'role' => 'user',
            'content' => "Historial de conversación:\n{$historyText}\nÚltima pregunta: {$latestMessage}\nConsulta reformulada:"
        ]
    ];

    try {
        $standaloneQuery = trim(groq_complete($promptMessages, $apiKey, $model, 128));
        return $standaloneQuery !== '' ? $standaloneQuery : $latestMessage;
    } catch (Throwable $e) {
        error_log('conversation_reformulate_query failed: ' . $e->getMessage());
        return $latestMessage;
    }
}
