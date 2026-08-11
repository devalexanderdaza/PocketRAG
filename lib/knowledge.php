<?php
/**
 * Markdown Loader and Section Chunker.
 * 
 * Parses markdown files with YAML frontmatter and splits bodies into manageable chunks.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

require_once __DIR__ . '/bm25.php';

const KNOWLEDGE_MIN_CHUNK_CHARS = 320;
const KNOWLEDGE_MAX_CHUNK_CHARS = 900;

/**
 * Parse frontmatter and body from a raw markdown string.
 *
 * @param string $raw The raw markdown content.
 * @return array{meta:array<string,mixed>,body:string} Parsed metadata and body.
 */
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

/**
 * Split markdown body into optimized chunks with optional overlap.
 * 
 * Respects paragraph boundaries and tries to keep chunks within the configured limits.
 *
 * @param string $body The markdown body to split.
 * @param int $overlapChars Characters of context overlap between chunks.
 * @return list<string> Array of text chunks.
 */
function knowledge_split_body(string $body, int $overlapChars = 150): array
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

    if ($overlapChars > 0 && count($bounded) > 1) {
        $overlapped = [$bounded[0]];
        $totalBounded = count($bounded);
        for ($i = 1; $i < $totalBounded; $i++) {
            $prev = $bounded[$i - 1];
            $prefix = mb_strlen($prev, 'UTF-8') > $overlapChars ? mb_substr($prev, -$overlapChars, null, 'UTF-8') : $prev;
            $spacePos = mb_strpos($prefix, ' ', 0, 'UTF-8');
            if ($spacePos !== false && $spacePos < 30) {
                $prefix = mb_substr($prefix, $spacePos + 1, null, 'UTF-8');
            }
            $overlapped[] = '[...] ' . trim($prefix) . "\n\n" . $bounded[$i];
        }
        return $overlapped;
    }

    return $bounded;
}

/**
 * Load and chunk all markdown files from a directory.
 *
 * @param string $directory Path to the directory containing markdown files.
 * @param int|null $overlapChars Optional override for chunk overlap.
 * @return list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> Array of parsed chunks.
 */
function knowledge_load_chunks(string $directory, ?int $overlapChars = null): array
{
    $paths = glob(rtrim($directory, '/') . '/*.md') ?: [];
    sort($paths);
    $chunks = [];

    if ($overlapChars === null && function_exists('http_config')) {
        $config = http_config();
        $overlapChars = (int) ($config['chunk_overlap_chars'] ?? 150);
    } else {
        $overlapChars = $overlapChars ?? 150;
    }

    foreach ($paths as $path) {
        $raw = file_get_contents($path);
        if ($raw === false) {
            error_log('knowledge_load_chunks: cannot read file ' . $path);
            continue;
        }

        $parsed = knowledge_parse_frontmatter($raw);
        $meta   = $parsed['meta'];
        $slug   = (string) ($meta['slug'] ?? pathinfo($path, PATHINFO_FILENAME));
        $title  = (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $slug)));
        $tags   = $meta['tags'] ?? '';
        $tags   = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $priority = (int) ($meta['priority'] ?? 5);

        foreach (knowledge_split_body($parsed['body'], $overlapChars) as $position => $content) {
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

/**
 * Build a BM25 lexical index from loaded knowledge chunks.
 *
 * @param list<array{id:string,slug:string,title:string,tags:string,content:string,priority:int}> $chunks
 * @return array{docs:list<array{id:string,len:int,freqs:array<string,int>}>,df:array<string,int>,avgdl:float,n:int} The BM25 index.
 */
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
