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
require_once __DIR__ . '/lib/rate_limit.php';
require_once __DIR__ . '/lib/sync.php';

// Serve Chat UI and static assets from public/ directory on GET requests
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicDir = __DIR__ . '/public';

    if (($_GET['action'] ?? '') === 'telemetry') {
        $dbPath = __DIR__ . '/data/knowledge.sqlite';
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 50)));
        $since = max(0, (int) ($_GET['since'] ?? 0));
        $logs = telemetry_get_recent($dbPath, $limit, $since);
        http_send_json(200, ['logs' => $logs]);
        exit(0);
    }

    // Normalize path
    $filePath = realpath($publicDir . $uri);
    
    // If request points directly to a file inside public/ (e.g. assets/css/chat.css)
    if ($filePath && str_starts_with($filePath, $publicDir) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon'
        ];
        
        $mime = $mimeTypes[$ext] ?? 'text/plain';
        header("Content-Type: {$mime}");
        readfile($filePath);
        exit(0);
    }
    
    // Fallback to serving public/index.html
    $indexHtml = $publicDir . '/index.html';
    if (file_exists($indexHtml)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexHtml);
        exit(0);
    }
}

http_require_post();

// Sync webhook endpoint for GitHub Actions
if (($_GET['action'] ?? '') === 'sync') {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false) {
        http_send_json(400, ['error' => 'Unable to read request body.']);
    }
    $config = http_config();

    if (!sync_webhook_validate($config, $rawBody)) {
        http_send_json(401, ['error' => 'Unauthorized']);
    }

    $knowledgeDir = __DIR__ . '/data/knowledge';
    $dbPath = __DIR__ . '/data/knowledge.sqlite';
    $apiKeys = $config['gemini_api_keys'] ?? [];
    $model = $config['gemini_model'] ?? 'gemini-embedding-001';
    $dimensions = (int) ($config['gemini_dimensions'] ?? 768);

    $startTime = microtime(true);
    $result = sync_knowledge_run($knowledgeDir, $dbPath, $apiKeys, $model, $dimensions, false);
    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    http_send_json(200, [
        'ok' => true,
        'chunks' => $result['processed'],
        'skipped' => $result['skipped'],
        'deleted' => $result['deleted'],
        'duration_ms' => $durationMs,
    ]);
}

// Rate limiting check (SQLite-based sliding window; enabled via config)
$dbPath = __DIR__ . '/data/knowledge.sqlite';
if (!rate_limit_check($dbPath, rate_limit_get_ip())) {
    header('Retry-After: 60');
    http_send_json(429, ['error' => 'Too many requests. Please slow down.']);
}

$body = http_read_json_body();

$message = trim((string) ($body['message'] ?? ''));
if (strlen($message) > 4000) {
    http_send_json(400, ['error' => 'Message exceeds maximum length of 4000 characters.']);
}
$filter = $body['filter'] ?? null;
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

// Reformulate query using the configured LLM provider (if conversation history exists)
$searchQuery = conversation_reformulate_query($message, $history, $config);

// Load chunks and build BM25 index
$chunks = knowledge_load_chunks($knowledgeDir);
$index  = knowledge_build_index($chunks);

// Run Hybrid Retrieval with (potentially reformulated) search query
$retrieved = retrieval_select($chunks, $index, $searchQuery, '', $filter);
$context   = $retrieved['context'];
$sources   = $retrieved['sources'];

// Build System Prompt
$systemPrompt = prompt_build($context);

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

