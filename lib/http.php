<?php
/**
 * Shared HTTP Plumbing.
 * 
 * Provides utilities for handling HTTP requests, responses, and configuration parsing.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

/**
 * Load and cache the configuration array.
 *
 * Falls back to config.example.php if config.php is missing (useful for dry runs).
 * Throws RuntimeException if neither file is found.
 *
 * @return array<string, mixed> The configuration array.
 * @throws RuntimeException If no configuration file exists.
 */
function http_config(): array
{
    if (defined('POCKETRAG_TEST_CONFIG')) {
        $override = constant('POCKETRAG_TEST_CONFIG');
        if (is_array($override)) {
            return $override;
        }
    }

    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path         = dirname(__DIR__) . '/config.php';
    $fallbackPath = dirname(__DIR__) . '/config.example.php';

    if (!is_file($path)) {
        if (!is_file($fallbackPath)) {
            throw new RuntimeException(
                'PocketRAG: No configuration file found. '
                . 'Copy config.example.php to config.php and add your API keys.'
            );
        }
        $path = $fallbackPath;
    }

    $loaded = require $path;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

/**
 * Send Cross-Origin Resource Sharing (CORS) headers.
 */
function http_send_cors(): void
{
    $config = http_config();
    $origin = (string) ($config['allowed_origin'] ?? '*');
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

/**
 * Send a JSON response and terminate execution.
 *
 * @param int $status The HTTP status code.
 * @param array<string, mixed> $payload The data payload to serialize.
 * @return never
 */
function http_send_json(int $status, array $payload): never
{
    if (!headers_sent()) {
        http_response_code($status);
        http_send_cors();
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enforce POST request method. Exits early for OPTIONS requests.
 */
function http_require_post(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        http_send_cors();
        exit;
    }
    if ($method !== 'POST') {
        http_send_json(405, ['error' => 'Method not allowed']);
    }
}

/**
 * Read and decode the JSON body from the incoming request.
 *
 * @return array<mixed, mixed> The decoded JSON array.
 */
function http_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
