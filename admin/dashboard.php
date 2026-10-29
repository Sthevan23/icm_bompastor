<?php
declare(strict_types=1);

require __DIR__ . '/init.php';
admin_require_login();

/** @var PDO $pdo */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_slots') {
    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        admin_flash_set('err', 'Sessão expirada. Tente novamente.');
        header('Location: dashboard.php');
        exit;
    }
    $id = (string) ($_POST['id'] ?? '');
    if (preg_match('/^[a-f0-9]{16}$/', $id)) {
        $empty = json_encode(empty_slot_list(), JSON_UNESCAPED_UNICODE);
        $upd = $pdo->prepare(
            'UPDATE escalas SET current_list = :list, atualizado_em = UTC_TIMESTAMP() WHERE id = :id'
        );
        $upd->execute([':list' => $empty, ':id' => $id]);
        admin_flash_set('ok', 'Horários zerados. A escala continua; só os nomes foram apagados.');
    }
    header('Location: dashboard.php');
    exit;
}

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

try {
    $rows = $pdo->query(
        'SELECT id, igreja, motivo, date0, date1, atualizado_em, current_list FROM escalas ORDER BY atualizado_em DESC'
    )->fetchAll();
} catch (PDOException) {
    admin_fail_page(
        'Tabela não encontrada',
        'Importe database.sql no phpMyAdmin (tabela escalas).'
    );
}

$flash = admin_flash_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Escalas · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css?v=2" />
  </head>
  <body>
    <div class="wrap">
      <div class="top">
        <div>
          <h1>Escalas cadastradas</h1>
          <p class="muted" style="margin: 0.25rem 0 0; font-size: 0.9rem">
            Só aqui no painel é possível excluir a escala inteira.
          </p>
        </div>
        <div class="actions">
          <a class="btn" href="create.php">Nova escala</a>
          <a class="btn ghost" href="import.php">Importar do navegador</a>
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
          <p class="muted" style="margin-top: 0.75rem">
            Se você vê escalas no site, elas podem estar só no navegador.
            Use <a href="import.php">Importar do navegador</a> ou crie uma
            <a href="create.php">nova escala</a> aqui (vai direto para o MySQL).
          </p>
        <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Igreja</th>
                  <th>Motivo</th>
                  <th>Período</th>
                  <th>Preenchimento</th>
                  <th>Último registro</th>
                  <th class="col-actions">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $row) :
                    $list = json_decode((string) ($row['current_list'] ?? ''), true);
                    $filled = is_array($list) ? count_filled_slots($list) : 0;
                    ?>
                  <tr>
                    <td><?= admin_h($row['igreja']) ?></td>
                    <td><?= admin_h($row['motivo']) ?></td>
                    <td><?= admin_h($row['date0']) ?> → <?= admin_h($row['date1']) ?></td>
                    <td>
                      <?= admin_h(slot_fill_percentage($filled)) ?>%
                      · <?= (int) $filled ?> de 96 horários
                    </td>
                    <td><?= admin_h(format_datetime_br((string) $row['atualizado_em'])) ?></td>
                    <td class="col-actions">
                      <div class="row-actions">
                        <a class="btn" href="edit.php?id=<?= admin_h($row['id']) ?>">Editar</a>
                        <a class="btn ghost" href="../escala.html?id=<?= admin_h($row['id']) ?>" target="_blank" rel="noopener">Ver</a>
                        <form method="post" onsubmit="return confirm('Zerar todos os horários desta escala? Os nomes serão apagados.');">
                          <input type="hidden" name="csrf" value="<?= admin_h(admin_csrf_token()) ?>" />
                          <input type="hidden" name="action" value="reset_slots" />
                          <input type="hidden" name="id" value="<?= admin_h($row['id']) ?>" />
                          <button class="btn ghost" type="submit">Zerar</button>
                        </form>
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
          </div>
        <?php endif; ?>
      </div>
    </div>
  </body>
</html>
