<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../api/helpers.php';

$configPath = __DIR__ . '/../api/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo 'Configure api/config.php antes de usar o painel.';
    exit;
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
} catch (PDOException) {
    http_response_code(503);
    echo 'Erro ao conectar no MySQL. Verifique api/config.php.';
    exit;
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_ok(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], $token);
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_auth']);
}

function admin_require_login(): void
{
    if (!admin_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_verify(string $user, string $pass): bool
{
    global $config;
    $expectedUser = (string) ($config['admin_user'] ?? '01');
    $expectedPass = (string) ($config['admin_pass'] ?? 'icm2026');
    return hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass);
}

function admin_fetch_scale(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM escalas WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_flash_set(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_flash_get(): ?array
{
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}
