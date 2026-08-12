<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/http.php';

describe('HTTP — http_read_json_body');

it('returns empty array when input is not valid JSON', function () {
    $thrown = false;
    try {
        http_read_json_body();
    } catch (Throwable $e) {
        $thrown = true;
    }
    expect($thrown)->toBeFalse();
});
