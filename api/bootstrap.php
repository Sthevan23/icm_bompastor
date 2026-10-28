<?php
declare(strict_types=1);

require_once __DIR__ . '/connect.php';

try {
    [$config, $pdo] = api_connect();
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'missing_config') {
        json_response(503, [
            'error' => 'config',
            'message' => 'Crie api/config.php ou envie config.hostinger.php pelo Git.',
        ]);
    }
    json_response(503, [
        'error' => 'database',
        'message' => 'Não foi possível conectar ao MySQL. Confira api/config.hostinger.php e importe database.sql.',
    ]);
}
