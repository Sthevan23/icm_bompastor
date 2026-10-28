<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * @return array{0: array<string, string>, 1: PDO}
 */
function api_connect(): array
{
    $config = null;
    foreach (['config.php', 'config.hostinger.php'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (is_file($path)) {
            $config = require $path;
            break;
        }
    }

    if (!is_array($config)) {
        throw new RuntimeException(
            'missing_config',
            0
        );
    }

    $hosts = [$config['db_host'] ?? 'localhost'];
    if ($hosts[0] === 'localhost') {
        $hosts[] = '127.0.0.1';
    }

    $last = null;
    foreach ($hosts as $host) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $host,
            $config['db_name'],
            $config['db_charset'] ?? 'utf8mb4'
        );
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return [$config, $pdo];
        } catch (PDOException $e) {
            $last = $e;
        }
    }

    throw new RuntimeException('database', 0, $last);
}
