<?php
/**
 * Markdown Loader and Section Chunker.
 */

declare(strict_types=1);

require_once __DIR__ . '/bm25.php';

const KNOWLEDGE_MIN_CHUNK_CHARS = 320;
const KNOWLEDGE_MAX_CHUNK_CHARS = 900;

function knowledge_parse_frontmatter(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    if (strncmp($raw, "---\n", 4) !== 0) {
        return ['meta' => [], 'body' => trim($raw)];
    }
    $end = strpos($raw, "\n---", 3);
    if ($end === false) {
        return ['meta' => [], 'body' => trim($raw)];
    }
    $header = substr($raw, 4, $end - 4);
    $body   = substr($raw, $end + 4);
    $meta   = [];

    foreach (explode("\n", $header) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, ':')) {
            continue;
        }
        [$key, $value] = explode(':', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if ($value !== '' && $value[0] === '[' && substr($value, -1) === ']') {
            $inner = trim(substr($value, 1, -1));
            $meta[$key] = $inner === '' ? [] : array_map(fn($item) => trim($item, " \t'\""), explode(',', $inner));
            continue;
        }
        $meta[$key] = trim($value, "'\"");
    }

    return ['meta' => $meta, 'body' => trim($body)];
}

function knowledge_split_body(string $body): array
{
    $paragraphs = preg_split('/\n\s*\n/', $body) ?: [];
    $paragraphs = array_values(array_filter(
        array_map(fn($p) => trim($p), $paragraphs),
        fn($p) => $p !== ''
    ));

    $merged = [];
    $carry  = '';
    foreach ($paragraphs as $paragraph) {
        $isBareLabel = !str_contains($paragraph, "\n") && substr($paragraph, -1) === ':' && mb_strlen($paragraph) < 60;
        if ($isBareLabel) {
            $carry = $carry === '' ? $paragraph : $carry . "\n" . $paragraph;
            continue;
        }
        $merged[] = $carry === '' ? $paragraph : $carry . "\n" . $paragraph;
        $carry    = '';
    }
    if ($carry !== '') {
        $merged[] = $carry;
    }

    $chunks = [];
    foreach ($merged as $paragraph) {
        $lastIndex = count($chunks) - 1;
        if ($lastIndex >= 0 && strlen($chunks[$lastIndex]) < KNOWLEDGE_MIN_CHUNK_CHARS && strlen($chunks[$lastIndex]) + strlen($paragraph) <= KNOWLEDGE_MAX_CHUNK_CHARS) {
            $chunks[$lastIndex] .= "\n\n" . $paragraph;
            continue;
        }
        $chunks[] = $paragraph;
    }

    $count = count($chunks);
    if ($count > 1 && strlen($chunks[$count - 1]) < KNOWLEDGE_MIN_CHUNK_CHARS) {
        $candidate = $chunks[$count - 2] . "\n\n" . $chunks[$count - 1];
        if (strlen($candidate) <= KNOWLEDGE_MAX_CHUNK_CHARS) {
            array_splice($chunks, $count - 2, 2, [$candidate]);
        }
    }

    $bounded = [];
    foreach ($chunks as $chunk) {
        if (strlen($chunk) <= KNOWLEDGE_MAX_CHUNK_CHARS) {
            $bounded[] = $chunk;
            continue;
        }
        $sentences = preg_split('/(?<=[.!?])\s+/', $chunk) ?: [$chunk];
        $buffer    = '';
        foreach ($sentences as $sentence) {
            if ($buffer !== '' && strlen($buffer) + strlen($sentence) + 1 > KNOWLEDGE_MAX_CHUNK_CHARS) {
                $bounded[] = trim($buffer);
                $buffer    = '';
            }
            $buffer = $buffer === '' ? $sentence : $buffer . ' ' . $sentence;
        }
        if (trim($buffer) !== '') {
            $bounded[] = trim($buffer);
        }
    }

    return $bounded;
}

function knowledge_load_chunks(string $directory): array
{
    $paths = glob(rtrim($directory, '/') . '/*.md') ?: [];
    sort($paths);
    $chunks = [];

    foreach ($paths as $path) {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            continue;
        }

        $parsed = knowledge_parse_frontmatter($raw);
        $meta   = $parsed['meta'];
        $slug   = (string) ($meta['slug'] ?? pathinfo($path, PATHINFO_FILENAME));
        $title  = (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $slug)));
        $tags   = $meta['tags'] ?? '';
        $tags   = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $priority = (int) ($meta['priority'] ?? 5);

        foreach (knowledge_split_body($parsed['body']) as $position => $content) {
            $chunks[] = [
                'id'       => $slug . '#' . $position,
                'slug'     => $slug,
                'title'    => $title,
                'tags'     => $tags,
                'content'  => $content,
                'priority' => $priority,
            ];
        }
    }

    return $chunks;
}

function knowledge_build_index(array $chunks): array
{
    $seenSlugs = [];
    $documents = [];

    foreach ($chunks as $chunk) {
        $text = $chunk['content'];
        if (!isset($seenSlugs[$chunk['slug']])) {
            $seenSlugs[$chunk['slug']] = true;
            $text = $chunk['title'] . ' ' . $chunk['tags'] . ' ' . $text;
        }
        $documents[] = [
            'id'    => $chunk['id'],
            'text'  => $text,
            'group' => $chunk['slug'],
        ];
    }

    return bm25_index($documents);
}
