<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/notes.php';

describe('Notes — notes_extract');

it('strips a note marker and returns typed payload', function () {
    $raw = "Answer here.\n<!--NOTE-->{\"type\":\"fact\",\"text\":\"PocketRAG runs on shared PHP hosting without Composer.\"}<!--/NOTE-->";
    $out = notes_extract($raw);
    expect($out['reply'])->toBe('Answer here.');
    expect($out['note']['type'])->toBe('fact');
});

it('returns original reply when no marker is present', function () {
    $out = notes_extract('Just an answer.');
    expect($out['reply'])->toBe('Just an answer.');
    expect($out['note'])->toBeNull();
});

describe('Notes — notes_validate');

it('accepts a well-formed fact note', function () {
    $valid = notes_validate([
        'type' => 'fact',
        'text' => 'PocketRAG stores embeddings as SQLite BLOBs packed as floats.',
    ]);
    expect($valid !== null)->toBeTrue();
});

it('rejects short text, html, and unknown types', function () {
    expect(notes_validate(['type' => 'fact', 'text' => 'too short']))->toBeNull();
    expect(notes_validate(['type' => 'other', 'text' => str_repeat('word ', 20)]))->toBeNull();
    expect(notes_validate(['type' => 'fact', 'text' => str_repeat('ok ', 15) . '<script>']))->toBeNull();
});

describe('Notes — notes_append');

it('creates community_notes.md with frontmatter and appends a second note', function () {
    $dir = sys_get_temp_dir() . '/pocketrag-notes-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    $path = $dir . '/community_notes.md';
    $note = ['type' => 'correction', 'text' => 'The hybrid default strategy is Reciprocal Rank Fusion not linear blend.'];
    expect(notes_append($path, $note))->toBeTrue();
    expect(notes_append($path, $note))->toBeTrue();
    $raw = (string) file_get_contents($path);
    expect(str_contains($raw, 'slug: community_notes'))->toBeTrue();
    expect(substr_count($raw, '(correction)'))->toBe(2);
});
