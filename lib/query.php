<?php
/**
 * Optional multi-query expansion for retrieval.
 *
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/llm.php';

/**
 * Parse up to two query variants from an LLM JSON payload.
 *
 * @param string $raw LLM output.
 * @return list<string> At most two non-empty variants.
 */
function query_expansion_parse(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    if (preg_match('/\{.*\}/s', $raw, $m) === 1) {
        $raw = $m[0];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $list = $decoded['variants'] ?? [];
    if (!is_array($list)) {
        return [];
    }

    $out = [];
    foreach ($list as $item) {
        if (!is_string($item)) {
            continue;
        }
        $item = trim($item);
        if ($item === '' || mb_strlen($item, 'UTF-8') > 240) {
            continue;
        }
        $out[] = $item;
        if (count($out) >= 2) {
            break;
        }
    }

    return $out;
}

/**
 * Generate up to two alternative search queries. Fail-open to an empty list.
 *
 * @param string $message Standalone search query.
 * @param array<string, mixed> $config Application config.
 * @return list<string> Variants (never includes the original message).
 */
function query_expansion_variants(string $message, array $config): array
{
    if (!(bool) ($config['query_expansion_enabled'] ?? false)) {
        return [];
    }

    $message = trim($message);
    if (mb_strlen($message, 'UTF-8') < 8) {
        return [];
    }

    try {
        $prompt = 'Return JSON only: {"variants":["...","..."]}. '
            . 'Give two short alternative search queries for this question, same language, no answers: '
            . $message;
        $raw = llm_complete(
            [['role' => 'user', 'content' => $prompt]],
            $config,
            120,
            0.2
        );
        $variants = query_expansion_parse($raw);
        $filtered = [];
        foreach ($variants as $variant) {
            if (mb_strtolower($variant, 'UTF-8') === mb_strtolower($message, 'UTF-8')) {
                continue;
            }
            $filtered[] = $variant;
        }
        return $filtered;
    } catch (Throwable $e) {
        error_log('query_expansion_variants: ' . $e->getMessage());
        return [];
    }
}
