<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

if (admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    if (admin_verify($user, $pass)) {
        $_SESSION['admin_auth'] = true;
        admin_csrf_token();
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin · Oração Ininterrupta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap login-box">
      <div class="card">
        <h1 style="margin: 0 0 0.25rem; color: #9b0b22">Painel administrativo</h1>
        <p class="muted" style="margin: 0 0 1.25rem">Oração Ininterrupta — ICM Bom Pastor</p>
        <?php if ($error !== '') : ?>
          <div class="flash err"><?= admin_h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="">
          <div class="field">
            <label for="user">Usuário</label>
            <input id="user" name="user" type="text" autocomplete="username" required />
          </div>
          <div class="field">
            <label for="pass">Senha</label>
            <input id="pass" name="pass" type="password" autocomplete="current-password" required />
          </div>
          <button class="btn" type="submit" style="width: 100%">Entrar</button>
        </form>
      </div>
      <p class="muted" style="text-align: center; margin-top: 1rem">
        <a href="../index.html">Voltar ao site</a>
      </p>
    </div>
  </body>
</html>
