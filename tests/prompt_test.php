<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/prompt.php';

describe('Prompt — prompt_load_persona');

it('loads default persona when persona file is missing', function () {
    $persona = prompt_load_persona();
    expect($persona['name'])->toBe('PocketRAG Assistant');
    expect($persona['rules'])->toContain('RETRIEVED CONTEXT');
});

describe('Prompt — prompt_build');

it('builds a prompt correctly', function () {
    $context = 'test context';
    $prompt = prompt_build($context);
    expect($prompt)->toContain('PocketRAG Assistant');
    expect($prompt)->toContain('RETRIEVED CONTEXT:');
    expect($prompt)->toContain($context);
});
