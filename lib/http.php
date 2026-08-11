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
 * @return array<string, mixed> The configuration array.
 */
function http_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $path = dirname(__DIR__) . '/config.php';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/config.example.php';
    }
    $config = require $path;
    return is_array($config) ? $config : [];
}

/**
 * Send Cross-Origin Resource Sharing (CORS) headers.
 */
function http_send_cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

/**
 * Send a JSON response and terminate execution.
 *
 * @param int $status The HTTP status code.
 * @param array<string, mixed> $payload The data payload to serialize.
 */
function http_send_json(int $status, array $payload): void
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
