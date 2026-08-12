<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/ollama.php';

describe('Ollama — ollama_complete');

it('throws RuntimeException when Ollama is unreachable', function () {
    $messages = [['role' => 'user', 'content' => 'hello']];
    // Ollama is local, so this will fail to connect
    try {
        ollama_complete($messages, 'http://invalid-ollama-url:11434/v1/chat/completions', 'llama3');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('Ollama API error');
        return;
    }
    throw new Exception('Expected RuntimeException not thrown');
});
