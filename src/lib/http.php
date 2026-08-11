<?php
/**
 * Shared HTTP Plumbing.
 */

declare(strict_types=1);

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

function http_send_cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

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

function http_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
