<?php
/**
 * System Prompt Builder.
 * 
 * Generates the system prompt used for LLM completions, reading the assistant
 * persona from data/persona.md so operators can customize identity without
 * editing PHP source code.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

/**
 * Load assistant persona from data/persona.md.
 * 
 * Reads the optional YAML frontmatter (name, description) and the body (rules text).
 * Falls back to generic defaults if the file is missing or unreadable.
 *
 * @return array{name:string,description:string,rules:string} Parsed persona fields.
 */
function prompt_load_persona(): array
{
    $personaPath = dirname(__DIR__) . '/data/persona.md';
    $defaults    = [
        'name'        => 'PocketRAG Assistant',
        'description' => 'AI-powered knowledge base assistant.',
        'rules'       => implode("\n", [
            '1. Respond accurately using ONLY the information from the RETRIEVED CONTEXT.',
            '2. Respond in the user\'s language (English by default unless specified).',
            '3. Maintain a direct, professional, and clear tone.',
            '4. If the answer is not in the context, say so honestly — do not invent information.',
        ]),
    ];

    if (!is_file($personaPath)) {
        return $defaults;
    }

    $raw = file_get_contents($personaPath);
    if ($raw === false) {
        error_log('prompt_load_persona: cannot read data/persona.md');
        return $defaults;
    }

    $raw = str_replace("\r\n", "\n", $raw);
    $name        = $defaults['name'];
    $description = $defaults['description'];
    $body        = trim($raw);

    // Parse optional YAML frontmatter
    if (strncmp($raw, "---\n", 4) === 0) {
        $end = strpos($raw, "\n---", 3);
        if ($end !== false) {
            $header = substr($raw, 4, $end - 4);
            $body   = trim(substr($raw, $end + 4));

            foreach (explode("\n", $header) as $line) {
                if (str_starts_with($line, 'name:')) {
                    $name = trim(substr($line, 5), " \"'");
                } elseif (str_starts_with($line, 'description:')) {
                    $description = trim(substr($line, 12), " \"'");
                }
            }
        }
    }

    return [
        'name'        => $name !== '' ? $name : $defaults['name'],
        'description' => $description !== '' ? $description : $defaults['description'],
        'rules'       => $body !== '' ? $body : $defaults['rules'],
    ];
}

/**
 * Build the system prompt injected with retrieved context.
 *
 * The assistant persona (name, description, rules) is read from data/persona.md.
 * Falls back to generic PocketRAG defaults if the persona file is absent.
 *
 * @param string $context The retrieved markdown context.
 * @return string The formatted system prompt.
 */
function prompt_build(string $context): string
{
    $persona = prompt_load_persona();

    return "You are {$persona['name']}. {$persona['description']}\n\n"
        . "{$persona['rules']}\n\n"
        . "RETRIEVED CONTEXT:\n" . $context;
}
