<?php
/**
 * PHPoC-VeRAG Configuration File Example
 * Save as config.php in root or private directory.
 */

declare(strict_types=1);

return [
    'allowed_origin'  => '*',
    'groq_api_key'    => 'gsk_your_groq_api_key_here',
    'groq_model'      => 'llama-3.3-70b-versatile',
    
    // Pool of Gemini API keys for automatic rotation on 429/403 rate limits
    'gemini_api_keys' => [
        'AIzaSy_your_first_gemini_key',
        'AIzaSy_your_second_gemini_key',
    ],
    'gemini_model'      => 'gemini-embedding-001',
    'gemini_dimensions' => 768,
];
