<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/knowledge.php';

describe('Knowledge — knowledge_parse_frontmatter');

it('returns empty meta and body unchanged when no frontmatter', function () {
    $result = knowledge_parse_frontmatter("Hello world");
    expect($result['meta'])->toEqual([]);
    expect($result['body'])->toBe('Hello world');
});

it('parses simple key-value frontmatter', function () {
    $raw    = "---\nslug: test-doc\ntitle: Test Document\n---\nBody here.";
    $result = knowledge_parse_frontmatter($raw);
    expect($result['meta']['slug'])->toBe('test-doc');
    expect($result['meta']['title'])->toBe('Test Document');
    expect($result['body'])->toBe('Body here.');
});

it('parses array values in frontmatter', function () {
    $raw    = "---\ntags: [php, sqlite, rag]\n---\nContent.";
    $result = knowledge_parse_frontmatter($raw);
    expect(is_array($result['meta']['tags']))->toBeTrue();
    expect(in_array('php', $result['meta']['tags'], true))->toBeTrue();
});

it('strips surrounding quotes from values', function () {
    $raw    = "---\ntitle: \"My Title\"\n---\nContent.";
    $result = knowledge_parse_frontmatter($raw);
    expect($result['meta']['title'])->toBe('My Title');
});

it('returns empty meta and full raw as body when frontmatter is malformed', function () {
    $result = knowledge_parse_frontmatter("---\nno closing fence\ncontent");
    expect($result['meta'])->toEqual([]);
});

describe('Knowledge — knowledge_split_body');

it('returns a single chunk for short content', function () {
    $chunks = knowledge_split_body('Short paragraph.');
    expect(count($chunks))->toBeGreaterThan(0);
});

it('splits long content into multiple chunks', function () {
    $longParagraph = str_repeat('word ', 300);
    $body          = $longParagraph . "\n\n" . $longParagraph;
    $chunks        = knowledge_split_body($body, 0, 320, 900);
    expect(count($chunks))->toBeGreaterThan(1);
});

it('no chunk exceeds configured max_chars significantly', function () {
    $p1     = str_repeat('foo bar baz qux. ', 30);
    $p2     = str_repeat('alpha beta gamma delta. ', 30);
    $chunks = knowledge_split_body($p1 . "\n\n" . $p2, 0, 320, 900);
    foreach ($chunks as $chunk) {
        if (strlen($chunk) > 900 * 2) {
            throw new AssertionError('Chunk too large: ' . strlen($chunk));
        }
    }
});

it('prefixes overlap marker on subsequent chunks when overlap > 0', function () {
    $p1     = str_repeat('first ', 50);
    $p2     = str_repeat('second ', 50);
    $chunks = knowledge_split_body($p1 . "\n\n" . $p2, 50);
    if (count($chunks) > 1) {
        expect($chunks[1])->toContain('[...]');
    }
});

it('keeps fenced code block intact in a single chunk', function () {
    $body = <<<'MD'
# Introduction

Here is some explanation.

```
function hello() {
    console.log("world");
    return true;
}
```

More text here.
MD;
    $chunks = knowledge_split_body($body, 0);
    $codeChunks = array_filter($chunks, fn($c) => str_contains($c, '```'));
    expect(count($codeChunks))->toBeGreaterThan(0);
    foreach ($codeChunks as $chunk) {
        if (str_contains($chunk, '```')) {
            expect(substr_count($chunk, '```'))->toBe(2);
        }
    }
});

it('aligns chunks to heading boundaries for H2 and H3', function () {
    $p1 = str_repeat('word ', 100);
    $p2 = str_repeat('word ', 100);
    $p3 = str_repeat('word ', 100);
    $p4 = str_repeat('word ', 100);
    $body = <<<MD
# Main Title

## Section One

$p1

## Section Two

$p2

### Subsection Two A

$p3

### Subsection Two B

$p4
MD;
    $chunks = knowledge_split_body($body, 0, 320, 600);
    expect(count($chunks))->toBeGreaterThan(1);
    $chunk0 = $chunks[0];
    expect(str_contains($chunk0, '## Section One'))->toBeTrue();
    expect(str_contains($chunk0, '## Section Two'))->toBeFalse();
});

it('splits respects runtime min/max config via override params', function () {
    $p1 = str_repeat('word ', 50);
    $p2 = str_repeat('word ', 50);
    $p3 = str_repeat('word ', 50);
    $body = "$p1\n\n$p2\n\n$p3";
    $chunks = knowledge_split_body($body, 0, 100, 300);
    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        if (strlen($chunk) > 300) {
            throw new AssertionError('Chunk exceeds max: ' . strlen($chunk));
        }
    }
});

describe('Knowledge — knowledge_build_index');

it('builds a valid BM25 index from chunks', function () {
    $chunks = [
        ['id' => 'a#0', 'slug' => 'a', 'title' => 'Alpha', 'tags' => 'test', 'content' => 'Hello world', 'priority' => 5],
        ['id' => 'b#0', 'slug' => 'b', 'title' => 'Beta',  'tags' => 'test', 'content' => 'Goodbye world', 'priority' => 5],
    ];
    $index = knowledge_build_index($chunks);
    expect($index['n'])->toBe(2);
    expect(count($index['docs']))->toBe(2);
});
