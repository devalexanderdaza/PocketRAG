<?php
/**
 * Query Expansion — Synonym Dictionary Loader.
 * 
 * Loads word and phrase synonym maps from data/synonyms.json so operators can
 * customize query expansion for their domain without editing PHP source code.
 * Falls back to empty maps (no expansion) if the file is absent or invalid.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/bm25.php';

/**
 * Load and parse the synonym dictionary from data/synonyms.json.
 *
 * Returns a struct with 'words' and 'phrases' arrays.
 * Both default to empty arrays if the file is missing or unparseable.
 *
 * @return array{words:array<string,string>,phrases:array<string,string>} Synonym maps.
 */
function synonyms_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $path = dirname(__DIR__) . '/data/synonyms.json';
    if (!is_file($path)) {
        $cache = ['words' => [], 'phrases' => []];
        return $cache;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        error_log('synonyms_load: cannot read data/synonyms.json');
        $cache = ['words' => [], 'phrases' => []];
        return $cache;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        error_log('synonyms_load: data/synonyms.json is not valid JSON');
        $cache = ['words' => [], 'phrases' => []];
        return $cache;
    }

    $words   = isset($decoded['words'])   && is_array($decoded['words'])   ? $decoded['words']   : [];
    $phrases = isset($decoded['phrases']) && is_array($decoded['phrases']) ? $decoded['phrases'] : [];

    $cache = [
        'words'   => array_map('strval', $words),
        'phrases' => array_map('strval', $phrases),
    ];

    return $cache;
}

/**
 * Get the exact word synonym map.
 *
 * @return array<string,string> Map of single words to their expanded terms.
 */
function synonyms_map(): array
{
    return synonyms_load()['words'];
}

/**
 * Get the phrase synonym map.
 *
 * @return array<string,string> Map of phrases to their expanded terms.
 */
function synonyms_phrases(): array
{
    return synonyms_load()['phrases'];
}

/**
 * Expand a query using the synonym dictionary.
 * 
 * Matches full phrases first, then tokenizes and matches individual words.
 * If data/synonyms.json is absent or empty, returns the original query unchanged.
 *
 * @param string $query The original query string.
 * @return string The expanded query string containing original and new terms.
 */
function synonyms_expand(string $query): string
{
    $expanded   = [];
    $normalized = trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', bm25_fold($query)));
    $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

    foreach (synonyms_phrases() as $phrase => $terms) {
        if ($normalized !== '' && str_contains($normalized, (string) $phrase)) {
            $expanded[] = $terms;
        }
    }

    $map  = synonyms_map();
    $seen = [];

    foreach (bm25_tokenize($query) as $term) {
        if (!isset($map[$term]) || isset($seen[$term])) {
            continue;
        }
        $seen[$term] = true;
        $expanded[]  = $map[$term];
    }

    return $expanded === [] ? $query : $query . ' ' . implode(' ', $expanded);
}
