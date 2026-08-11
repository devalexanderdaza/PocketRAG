<?php
declare(strict_types=1);
// conversation.php requires llm.php which requires groq/openai/ollama.
// We load them all to satisfy require_once chains.
require_once dirname(__DIR__) . '/lib/groq.php';
require_once dirname(__DIR__) . '/lib/openai.php';
require_once dirname(__DIR__) . '/lib/ollama.php';
require_once dirname(__DIR__) . '/lib/llm.php';
require_once dirname(__DIR__) . '/lib/conversation.php';

describe('Conversation — conversation_normalize_history');

it('returns empty array for non-array input', function () {
    expect(conversation_normalize_history('not an array'))->toHaveCount(0);
    expect(conversation_normalize_history(null))->toHaveCount(0);
});

it('filters out invalid roles', function () {
    $history = conversation_normalize_history([
        ['role' => 'system',    'content' => 'system message'],
        ['role' => 'user',      'content' => 'user message'],
        ['role' => 'assistant', 'content' => 'assistant reply'],
        ['role' => 'unknown',   'content' => 'unknown role'],
    ]);
    expect(count($history))->toBe(2);
    expect($history[0]['role'])->toBe('user');
    expect($history[1]['role'])->toBe('assistant');
});

it('filters out messages with empty content', function () {
    $history = conversation_normalize_history([
        ['role' => 'user',      'content' => ''],
        ['role' => 'assistant', 'content' => 'reply'],
    ]);
    expect(count($history))->toBe(1);
});

it('keeps at most 10 messages', function () {
    $input = [];
    for ($i = 0; $i < 15; $i++) {
        $input[] = ['role' => 'user', 'content' => "message {$i}"];
    }
    $result = conversation_normalize_history($input);
    expect(count($result))->toBe(10);
});

it('keeps the last 10 messages when input exceeds 10', function () {
    $input = [];
    for ($i = 0; $i < 12; $i++) {
        $input[] = ['role' => 'user', 'content' => "message {$i}"];
    }
    $result = conversation_normalize_history($input);
    expect($result[0]['content'])->toBe('message 2');
    expect($result[9]['content'])->toBe('message 11');
});

describe('Conversation — conversation_reformulate_query');

it('returns original message when history is empty', function () {
    $config = ['llm_provider' => 'groq', 'groq_api_key' => 'placeholder'];
    $result = conversation_reformulate_query('What is PHP?', [], $config);
    expect($result)->toBe('What is PHP?');
});

it('returns original message when Groq key is a placeholder', function () {
    $config = [
        'llm_provider' => 'groq',
        'groq_api_key' => 'gsk_your_groq_api_key_here',
    ];
    $history = [['role' => 'user', 'content' => 'hello']];
    $result  = conversation_reformulate_query('Follow up?', $history, $config);
    expect($result)->toBe('Follow up?');
});
