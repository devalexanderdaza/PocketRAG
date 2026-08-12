<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/retrieval.php';

describe('Retrieval — retrieval_snippet');
it('truncates content to specified limit', function () {
    $content = 'This is a long content that should be truncated by the retrieval_snippet function to fit in the UI.';
    expect(retrieval_snippet($content, 10))->toBe('This is a…');
});
// ...
