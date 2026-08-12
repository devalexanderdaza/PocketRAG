<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/groq.php';

describe('Groq — groq_complete');

it('throws RuntimeException when API key is invalid', function () {
    $messages = [['role' => 'user', 'content' => 'hello']];
    // With an invalid key, the network call should fail
    try {
        groq_complete($messages, 'invalid-key', 'llama3');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('Groq API error');
        return;
    }
    // If we reach here, it didn't throw, which is unexpected for an invalid key
    throw new Exception('Expected RuntimeException not thrown');
});
