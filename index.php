<?php
/**
 * PocketRAG Main Chat Endpoint.
 * 
 * Accepts POST JSON requests with `{"message": "question"}` and an optional `history` array.
 * Performs a hybrid retrieval (BM25 + Vector) and returns the generated response from Groq LLM.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/knowledge.php';
require_once __DIR__ . '/lib/retrieval.php';
require_once __DIR__ . '/lib/prompt.php';
require_once __DIR__ . '/lib/llm.php';
require_once __DIR__ . '/lib/conversation.php';
require_once __DIR__ . '/lib/telemetry.php';

http_require_post();
$body = http_read_json_body();

$message = trim((string) ($body['message'] ?? ''));
$rawHistory = $body['messages'] ?? ($body['history'] ?? []);
$history = conversation_normalize_history($rawHistory);

if ($message === '' && $history !== []) {
    $lastItem = end($history);
    if ($lastItem !== false && $lastItem['role'] === 'user') {
        $message = $lastItem['content'];
        array_pop($history);
    }
}

if ($message === '') {
    http_send_json(400, ['error' => 'Message is required']);
}

$startTime = microtime(true);
$config = http_config();
$knowledgeDir = __DIR__ . '/data/knowledge';

$groqApiKey = (string) ($config['groq_api_key'] ?? '');
$groqModel  = (string) ($config['groq_model'] ?? 'llama-3.3-70b-versatile');

// Reformulate query if conversation history exists
$searchQuery = conversation_reformulate_query($message, $history, $groqApiKey, $groqModel);

// Load chunks and build BM25 index
$chunks = knowledge_load_chunks($knowledgeDir);
$index  = knowledge_build_index($chunks);

// Run Hybrid Retrieval with (potentially reformulated) search query
$retrieved = retrieval_select($chunks, $index, $searchQuery);
$context   = $retrieved['context'];
$sources   = $retrieved['sources'];

// Build System Prompt
$systemPrompt = prompt_build($context, 'visitor', 'es');

// Assemble LLM completion messages (system prompt + history + current user message)
$llmMessages = [
    ['role' => 'system', 'content' => $systemPrompt],
];

foreach ($history as $prevMsg) {
    $llmMessages[] = $prevMsg;
}

$llmMessages[] = ['role' => 'user', 'content' => $message];

$reply = '';
$error = null;

try {
    $reply = llm_complete($llmMessages, $config, 512);
} catch (Exception $e) {
    $error = $e->getMessage();
    $reply = 'An error occurred while processing the request.';
}

$durationMs = round((microtime(true) - $startTime) * 1000, 2);
$dbPath = __DIR__ . '/data/knowledge.sqlite';

telemetry_log(
    $dbPath,
    $message,
    $searchQuery,
    $retrieved['mode'],
    $retrieved['fallback_occurred'],
    $retrieved['fallback_reason'],
    count($sources),
    $durationMs
);

http_send_json(200, [
    'reply'             => $reply,
    'search_query'      => $searchQuery,
    'sources'           => $sources,
    'mode'              => $retrieved['mode'],
    'fallback_occurred' => $retrieved['fallback_occurred'],
    'duration_ms'       => $durationMs,
    'error'             => $error,
]);

