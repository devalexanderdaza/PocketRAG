<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/openai.php';

describe('OpenAI — openai_complete');

it('throws RuntimeException when API key is empty', function () {
    $messages = [['role' => 'user', 'content' => 'hello']];
    try {
        openai_complete($messages, '', 'gpt-4o-mini');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('OpenAI API key is missing.');
        return;
    }
    throw new Exception('Expected RuntimeException not thrown');
});

it('throws RuntimeException when API key is invalid', function () {
    $messages = [['role' => 'user', 'content' => 'hello']];
    // With an invalid key, the network call should fail
    try {
        openai_complete($messages, 'invalid-key', 'gpt-4o-mini');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('OpenAI API error');
        return;
    }
    throw new Exception('Expected RuntimeException not thrown');
});
