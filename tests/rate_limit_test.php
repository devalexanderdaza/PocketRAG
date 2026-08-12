<?php
declare(strict_types=1);

define('POCKETRAG_TEST_CONFIG', [
    'trusted_proxies' => ['10.0.0.1'],
    'rate_limit_enabled' => true
]);

require_once dirname(__DIR__) . '/lib/rate_limit.php';

describe('Rate Limit — rate_limit_get_ip');

it('returns REMOTE_ADDR when XFF is absent', function () {
    $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    expect(rate_limit_get_ip())->toBe('1.2.3.4');
});

it('trusts XFF when REMOTE_ADDR is in trusted_proxies', function () {
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1, 10.0.0.1';
    expect(rate_limit_get_ip())->toBe('192.168.1.1');
});

it('rejects malformed IPs in XFF', function () {
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid-ip, 10.0.0.1';
    expect(rate_limit_get_ip())->toBe('0.0.0.0');
});
