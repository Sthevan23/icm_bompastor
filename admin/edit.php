<?php
declare(strict_types=1);

require __DIR__ . '/init.php';
admin_require_login();

/** @var PDO $pdo */

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    admin_flash_set('err', 'Escala inválida.');
    header('Location: dashboard.php');
    exit;
}

$row = admin_fetch_scale($pdo, $id);
if (!$row) {
    admin_flash_set('err', 'Escala não encontrada.');
    header('Location: dashboard.php');
    exit;
}

$keys = valid_slot_keys();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        admin_flash_set('err', 'Sessão expirada. Tente novamente.');
        header('Location: edit.php?id=' . urlencode($id));
        exit;
    }

    $igreja = mb_substr(trim((string) ($_POST['igreja'] ?? '')), 0, 39);
    $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 39);
    $date0 = (string) ($_POST['date0'] ?? '');
    $date1 = (string) ($_POST['date1'] ?? '');

    if ($igreja === '' || $motivo === '' || !valid_date($date0) || !valid_date($date1) || $date1 < $date0) {
        admin_flash_set('err', 'Verifique igreja, motivo e período.');
        header('Location: edit.php?id=' . urlencode($id));
        exit;
    }

    $slotsIn = $_POST['slot'] ?? [];
    if (!is_array($slotsIn)) {
        $slotsIn = [];
    }

    $list = empty_slot_list();
    foreach ($keys as $key) {
        $raw = trim((string) ($slotsIn[$key] ?? ''));
        $list[$key] = $raw === '' ? null : mb_substr($raw, 0, 32);
    }

    $upd = $pdo->prepare(
        'UPDATE escalas SET igreja = :igreja, motivo = :motivo, date0 = :date0, date1 = :date1,
         current_list = :list, atualizado_em = UTC_TIMESTAMP() WHERE id = :id'
    );
    $upd->execute([
        ':igreja' => $igreja,
        ':motivo' => $motivo,
        ':date0' => $date0,
        ':date1' => $date1,
        ':list' => json_encode($list, JSON_UNESCAPED_UNICODE),
        ':id' => $id,
    ]);

    admin_flash_set('ok', 'Escala salva com sucesso.');
    header('Location: edit.php?id=' . urlencode($id));
    exit;
}

$record = row_to_record($row);
$filledCount = count_filled_slots($record['currentList']);
$flash = admin_flash_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Editar escala · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap">
      <div class="top">
        <h1>Editar escala</h1>
        <div class="actions">
          <a class="btn ghost" href="dashboard.php">← Voltar</a>
          <a class="btn ghost" href="../escala.html?id=<?= admin_h($id) ?>" target="_blank" rel="noopener">Ver no site</a>
        </div>
      </div>

      <?php if ($flash) : ?>
        <div class="flash <?= admin_h($flash['type']) ?>"><?= admin_h($flash['message']) ?></div>
      <?php endif; ?>

      <form method="post" class="card">
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?= admin_h($id) ?>" />
        <input type="hidden" name="csrf" value="<?= admin_h(admin_csrf_token()) ?>" />

        <div class="field">
          <label for="igreja">Igreja</label>
          <input id="igreja" name="igreja" type="text" maxlength="39" required value="<?= admin_h($record['igreja']) ?>" />
        </div>
        <div class="field">
          <label for="motivo">Motivo</label>
          <input id="motivo" name="motivo" type="text" maxlength="39" required value="<?= admin_h($record['motivo']) ?>" />
        </div>
        <div class="grid-2">
          <div class="field">
            <label for="date0">Primeiro dia</label>
            <input id="date0" name="date0" type="date" required value="<?= admin_h($record['date0']) ?>" />
          </div>
          <div class="field">
            <label for="date1">Último dia</label>
            <input id="date1" name="date1" type="date" required value="<?= admin_h($record['date1']) ?>" />
          </div>
        </div>

        <p class="muted" style="margin: 0 0 0.5rem">
          ID: <code><?= admin_h($id) ?></code> —
          <strong><?= (int) $filledCount ?> / 96</strong> horários preenchidos no servidor.
          Se estiver 0 mas o site mostra nomes, use
          <a href="import.php">Importar do navegador</a> no aparelho onde cadastrou ontem.
        </p>

        <div class="slots">
          <?php foreach ($keys as $key) :
              $val = $record['currentList'][$key] ?? '';
              $name = $val === null ? '' : (string) $val;
              ?>
            <div class="slot">
              <time><?= admin_h($key) ?></time>
              <input
                type="text"
                name="slot[<?= admin_h($key) ?>]"
                maxlength="32"
                placeholder="Vazio"
                value="<?= admin_h($name) ?>"
              />
            </div>
          <?php endforeach; ?>
        </div>

        <div class="actions" style="margin-top: 1.25rem">
          <button class="btn" type="submit">Salvar alterações</button>
        </div>
      </form>
    </div>
  </body>
</html>
