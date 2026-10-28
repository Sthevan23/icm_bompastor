<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    json_response(503, [
        'error' => 'config',
        'message' => 'Copie api/config.example.php para api/config.php e configure o MySQL.',
    ]);
}

/** @var array<string, string> $config */
$config = require $configPath;

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['db_host'],
    $config['db_name'],
    $config['db_charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    json_response(503, [
        'error' => 'database',
        'message' => 'Não foi possível conectar ao MySQL. Verifique api/config.php.',
    ]);
}
