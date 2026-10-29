<?php
declare(strict_types=1);

require __DIR__ . '/init.php';
admin_require_login();

/** @var PDO $pdo */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        admin_flash_set('err', 'Sessão expirada. Tente novamente.');
        header('Location: create.php');
        exit;
    }

    $igreja = mb_substr(trim((string) ($_POST['igreja'] ?? '')), 0, 39);
    $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 39);
    $date0 = (string) ($_POST['date0'] ?? '');
    $date1 = (string) ($_POST['date1'] ?? '');

    if ($igreja === '' || $motivo === '' || !valid_date($date0) || !valid_date($date1) || $date1 < $date0) {
        admin_flash_set('err', 'Verifique igreja, motivo e período.');
        header('Location: create.php');
        exit;
    }

    $id = new_id();
    $token = new_token();
    $list = json_encode(empty_slot_list(), JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare(
        'INSERT INTO escalas (id, igreja, motivo, date0, date1, owner_token, current_list)
         VALUES (:id, :igreja, :motivo, :date0, :date1, :token, :list)'
    );
    $stmt->execute([
        ':id' => $id,
        ':igreja' => $igreja,
        ':motivo' => $motivo,
        ':date0' => $date0,
        ':date1' => $date1,
        ':token' => $token,
        ':list' => $list,
    ]);

    admin_flash_set('ok', 'Escala criada no servidor.');
    header('Location: edit.php?id=' . urlencode($id));
    exit;
}

$flash = admin_flash_get();
$today = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nova escala · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap">
      <div class="top">
        <h1>Nova escala</h1>
        <div class="actions">
          <a class="btn ghost" href="dashboard.php">Voltar</a>
        </div>
      </div>

      <?php if ($flash) : ?>
        <div class="flash <?= admin_h($flash['type']) ?>"><?= admin_h($flash['message']) ?></div>
      <?php endif; ?>

      <div class="card">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= admin_h(admin_csrf_token()) ?>" />
          <input type="hidden" name="action" value="create" />
          <div class="field">
            <label for="igreja">Igreja</label>
            <input id="igreja" name="igreja" type="text" maxlength="39" required placeholder="ICM Bom Pastor" />
          </div>
          <div class="field">
            <label for="motivo">Motivo</label>
            <input id="motivo" name="motivo" type="text" maxlength="39" required placeholder="Oração Ininterrupta" />
          </div>
          <div class="field">
            <label for="date0">Data inicial</label>
            <input id="date0" name="date0" type="date" value="<?= admin_h($today) ?>" required />
          </div>
          <div class="field">
            <label for="date1">Data final</label>
            <input id="date1" name="date1" type="date" value="<?= admin_h($today) ?>" required />
          </div>
          <button class="btn" type="submit">Criar escala</button>
        </form>
      </div>
    </div>
  </body>
</html>
