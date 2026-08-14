<?php
/**
 * Optional conversation-driven community notes.
 *
 * @package PocketRAG
 */

declare(strict_types=1);

const NOTES_MARKER_OPEN = '<!--NOTE-->';
const NOTES_MARKER_CLOSE = '<!--/NOTE-->';

/**
 * Extract a note JSON payload from an LLM reply, if present.
 *
 * @param string $reply Raw model reply.
 * @return array{reply:string,note:?array{type:string,text:string}} Stripped reply and optional note.
 */
function notes_extract(string $reply): array
{
    $start = strpos($reply, NOTES_MARKER_OPEN);
    $end = strpos($reply, NOTES_MARKER_CLOSE);
    if ($start === false || $end === false || $end <= $start) {
        return ['reply' => $reply, 'note' => null];
    }

    $jsonStart = $start + strlen(NOTES_MARKER_OPEN);
    $json = trim(substr($reply, $jsonStart, $end - $jsonStart));
    $stripped = trim(substr($reply, 0, $start) . substr($reply, $end + strlen(NOTES_MARKER_CLOSE)));

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return ['reply' => $stripped !== '' ? $stripped : $reply, 'note' => null];
    }

    $type = (string) ($decoded['type'] ?? '');
    $text = (string) ($decoded['text'] ?? '');
    return [
        'reply' => $stripped,
        'note'  => ['type' => $type, 'text' => $text],
    ];
}

/**
 * Validate a candidate community note.
 *
 * @param array{type?:string,text?:string} $note Candidate note.
 * @return array{type:string,text:string}|null Valid note or null.
 */
function notes_validate(array $note): ?array
{
    $type = (string) ($note['type'] ?? '');
    $text = trim((string) ($note['text'] ?? ''));
    if ($type !== 'correction' && $type !== 'fact') {
        return null;
    }
    $len = mb_strlen($text, 'UTF-8');
    if ($len < 20 || $len > 800) {
        return null;
    }
    if (str_contains($text, '---') || str_contains($text, '<') || str_contains($text, '>')
        || str_contains($text, '../') || str_contains($text, "\0")) {
        return null;
    }
    return ['type' => $type, 'text' => $text];
}

/**
 * Append a validated note to community_notes.md (create file with frontmatter if needed).
 *
 * @param string $path Destination markdown path.
 * @param array{type:string,text:string} $note Validated note.
 * @return bool True when the write succeeded.
 */
function notes_append(string $path, array $note): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $stamp = gmdate('Y-m-d H:i:s') . ' UTC';
    $block = "\n- [{$stamp}] ({$note['type']}) {$note['text']}\n";

    if (!is_file($path)) {
        $header = "---\nslug: community_notes\ntitle: Community notes\ntags: [community]\npriority: 3\n---\n\n# Community notes\n\nAppend-only facts captured from chat.\n";
        return file_put_contents($path, $header . $block, LOCK_EX) !== false;
    }

    return file_put_contents($path, $block, FILE_APPEND | LOCK_EX) !== false;
}
