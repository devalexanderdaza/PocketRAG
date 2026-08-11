<?php
/**
 * PocketRAG Configuration File Example.
 * 
 * Save this file as `config.php` in the root or private directory.
 * Contains API keys and model configurations for Groq and Gemini.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

return [
    'allowed_origin'  => '*',
    'telemetry_enabled' => true,
    'auto_sync_on_retrieval' => true,
    'chunk_overlap_chars' => 150,

    // Active LLM Provider: 'groq', 'ollama', or 'openai'
    'llm_provider'    => 'groq',

    'groq_api_key'    => 'gsk_your_groq_api_key_here',
    'groq_model'      => 'llama-3.3-70b-versatile',
    
    // Ollama Local-First Settings
    'ollama_endpoint' => 'http://localhost:11434/v1/chat/completions',
    'ollama_model'    => 'llama3.2',

    // OpenAI Settings
    'openai_api_key'  => 'sk-your_openai_api_key_here',
    'openai_model'    => 'gpt-4o-mini',

    // Pool of Gemini API keys for automatic rotation on 429/403 rate limits
    'gemini_api_keys' => [
        'AIzaSy_your_first_gemini_key',
        'AIzaSy_your_second_gemini_key',
    ],
    'gemini_model'      => 'gemini-embedding-001',
    'gemini_dimensions' => 768,
];
