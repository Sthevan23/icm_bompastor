<?php
declare(strict_types=1);

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    api_connect();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException) {
    http_response_code(503);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}
