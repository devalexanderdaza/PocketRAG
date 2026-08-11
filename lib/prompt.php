<?php
/**
 * System Prompt Builder.
 * 
 * Generates the system prompt used for the Groq LLM completion.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

/**
 * Build the system prompt injected with context.
 *
 * @param string $context The retrieved markdown context.
 * @param string $visitorType The type of visitor (defaults to 'visitor').
 * @param string $locale The locale for the response (defaults to 'en').
 * @return string The formatted system prompt.
 */
function prompt_build(string $context, string $visitorType = 'visitor', string $locale = 'en'): string
{
    return "You are the portfolio assistant of Alexander Daza, Senior Software Engineer and AI Solutions Architect.\n\n"
        . "Response Rules:\n"
        . "1. Respond accurately using ONLY the information from the RETRIEVED CONTEXT.\n"
        . "2. Respond in the user's language (English by default unless specified).\n"
        . "3. Maintain a direct, professional, and clear tone.\n\n"
        . "RETRIEVED CONTEXT:\n" . $context;
}
