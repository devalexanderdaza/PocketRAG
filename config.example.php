<?php
/**
 * PocketRAG Configuration File Example.
 * 
 * Copy this file to `config.php` and fill in your API keys.
 * See docs/configuration.md for the full reference of all available keys.
 * See docs/customization.md for persona, synonyms, and domain customization.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

return [
    'allowed_origin'         => '*',
    'telemetry_enabled'      => true,
    'auto_sync_on_retrieval' => true,
    'chunk_overlap_chars'    => 150,

    // Slugs used as fallback context when no chunk scores above zero.
    // Add slugs that represent your "default" or "about" documents.
    'default_fallback_slugs' => ['profile', 'cv', 'about'],

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

    // Rate limiting (SQLite-based, shared-hosting compatible)
    // Set rate_limit_enabled to true to restrict requests per IP.
    'rate_limit_enabled' => false,
    'rate_limit_rpm'     => 30,        // max requests per IP per window
    'rate_limit_window'  => 60,        // window duration in seconds
];
