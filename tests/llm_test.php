<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/llm.php';

describe('LLM — llm_complete');

it('returns mock response for Groq when key is missing or placeholder', function () {
    $messages = [['role' => 'user', 'content' => 'hello']];
    $config = ['llm_provider' => 'groq', 'groq_api_key' => ''];
    $result = llm_complete($messages, $config);
    expect($result)->toContain('[MOCK RESPONSE]');
});

it('uses configured provider', function () {
    // We just test if it dispatches. For actual dispatch tests, we test the providers individually.
    // Testing dispatching to unsupported provider, should fallback to default (groq)
    $messages = [['role' => 'user', 'content' => 'hello']];
    $config = ['llm_provider' => 'unsupported', 'groq_api_key' => ''];
    $result = llm_complete($messages, $config);
    expect($result)->toContain('[MOCK RESPONSE]');
});
