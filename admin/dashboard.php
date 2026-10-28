<?php
declare(strict_types=1);

require __DIR__ . '/init.php';
admin_require_login();

/** @var PDO $pdo */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        admin_flash_set('err', 'Sessão expirada. Tente novamente.');
        header('Location: dashboard.php');
        exit;
    }
    $id = (string) ($_POST['id'] ?? '');
    if (preg_match('/^[a-f0-9]{16}$/', $id)) {
        $del = $pdo->prepare('DELETE FROM escalas WHERE id = :id');
        $del->execute([':id' => $id]);
        admin_flash_set('ok', 'Escala excluída.');
    }
    header('Location: dashboard.php');
    exit;
}

$rows = $pdo->query(
    'SELECT id, igreja, motivo, date0, date1, atualizado_em FROM escalas ORDER BY atualizado_em DESC'
)->fetchAll();

$flash = admin_flash_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Escalas · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap">
      <div class="top">
        <h1>Escalas cadastradas</h1>
        <div class="actions">
          <a class="btn ghost" href="../index.html">Site público</a>
          <a class="btn ghost" href="logout.php">Sair</a>
        </div>
      </div>

      <?php if ($flash) : ?>
        <div class="flash <?= admin_h($flash['type']) ?>"><?= admin_h($flash['message']) ?></div>
      <?php endif; ?>

      <div class="card">
        <?php if (!$rows) : ?>
          <p class="muted">Nenhuma escala no banco ainda.</p>
        <?php else : ?>
          <table>
            <thead>
              <tr>
                <th>Igreja</th>
                <th>Motivo</th>
                <th>Período</th>
                <th>Atualizado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row) : ?>
                <tr>
                  <td><?= admin_h($row['igreja']) ?></td>
                  <td><?= admin_h($row['motivo']) ?></td>
                  <td><?= admin_h($row['date0']) ?> → <?= admin_h($row['date1']) ?></td>
                  <td><?= admin_h((string) $row['atualizado_em']) ?></td>
                  <td>
                    <div class="actions">
                      <a class="btn" href="edit.php?id=<?= admin_h($row['id']) ?>">Editar</a>
                      <a class="btn ghost" href="../escala.html#<?= admin_h($row['id']) ?>" target="_blank" rel="noopener">Ver</a>
                      <form method="post" onsubmit="return confirm('Excluir esta escala?');">
                        <input type="hidden" name="csrf" value="<?= admin_h(admin_csrf_token()) ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?= admin_h($row['id']) ?>" />
                        <button class="btn danger" type="submit">Excluir</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </body>
</html>
