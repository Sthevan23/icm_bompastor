<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../api/helpers.php';
require __DIR__ . '/../api/connect.php';

function admin_fail_page(string $title, string $message): void
{
    http_response_code(503);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin · Configuração</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap login-box">
      <div class="card">
        <h1 style="margin: 0 0 0.5rem; color: #9b0b22"><?= admin_h($title) ?></h1>
        <p class="muted"><?= admin_h($message) ?></p>
        <p class="muted" style="margin-top: 1rem">
          <a href="../index.html">Voltar ao site</a>
        </p>
      </div>
    </div>
  </body>
</html>
    <?php
    exit;
}

try {
    [$config, $pdo] = api_connect();
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'missing_config') {
        admin_fail_page(
            'Configuração ausente',
            'Envie api/config.hostinger.php ou api/config.php para o servidor.'
        );
    }
    admin_fail_page(
        'Erro no banco de dados',
        'Verifique usuário e senha MySQL. No phpMyAdmin, importe o arquivo database.sql para criar a tabela escalas.'
    );
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
