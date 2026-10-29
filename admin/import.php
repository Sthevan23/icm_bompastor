<?php
declare(strict_types=1);

require __DIR__ . '/init.php';
admin_require_login();

/** @var PDO $pdo */

$imported = 0;
$updated = 0;
$skipped = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!admin_csrf_ok($_POST['csrf'] ?? null)) {
        admin_flash_set('err', 'Sessão expirada. Tente novamente.');
        header('Location: import.php');
        exit;
    }

    $raw = trim((string) ($_POST['json'] ?? ''));
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        admin_flash_set('err', 'JSON inválido. Copie o conteúdo completo do localStorage.');
        header('Location: import.php');
        exit;
    }

    $scales = $data['scales'] ?? [];
    $tokens = $data['tokens'] ?? [];
    if (!is_array($scales)) {
        $scales = [];
    }
    if (!is_array($tokens)) {
        $tokens = [];
    }

    $check = $pdo->prepare('SELECT current_list FROM escalas WHERE id = :id LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO escalas (id, igreja, motivo, date0, date1, owner_token, current_list)
         VALUES (:id, :igreja, :motivo, :date0, :date1, :token, :list)'
    );
    $update = $pdo->prepare(
        'UPDATE escalas SET igreja = :igreja, motivo = :motivo, date0 = :date0, date1 = :date1,
         current_list = :list, atualizado_em = UTC_TIMESTAMP() WHERE id = :id'
    );

    foreach ($scales as $id => $local) {
        if (!is_string($id) || !preg_match('/^[a-f0-9]{16}$/', $id) || !is_array($local)) {
            continue;
        }
        $token = (string) ($tokens[$id] ?? '');
        if ($token === '') {
            $skipped += 1;
            continue;
        }

        $igreja = mb_substr(trim((string) ($local['igreja'] ?? '')), 0, 39);
        $motivo = mb_substr(trim((string) ($local['motivo'] ?? '')), 0, 39);
        $date0 = (string) ($local['date0'] ?? '');
        $date1 = (string) ($local['date1'] ?? '');
        if ($igreja === '' || $motivo === '' || !valid_date($date0) || !valid_date($date1)) {
            $errors[] = $id;
            continue;
        }

        $listIn = $local['currentList'] ?? [];
        if (!is_array($listIn)) {
            $listIn = [];
        }
        $localList = normalize_slot_list($listIn);

        $check->execute([':id' => $id]);
        $existing = $check->fetch();
        if ($existing) {
            $serverList = json_decode((string) $existing['current_list'], true);
            if (!is_array($serverList)) {
                $serverList = [];
            }
            $merged = merge_slot_lists($serverList, $localList);
            $before = count_filled_slots($serverList);
            $after = count_filled_slots($merged);
            if ($after <= $before) {
                $skipped += 1;
                continue;
            }
            $update->execute([
                ':igreja' => $igreja,
                ':motivo' => $motivo,
                ':date0' => $date0,
                ':date1' => $date1,
                ':list' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                ':id' => $id,
            ]);
            $updated += 1;
            continue;
        }

        $insert->execute([
            ':id' => $id,
            ':igreja' => $igreja,
            ':motivo' => $motivo,
            ':date0' => $date0,
            ':date1' => $date1,
            ':token' => $token,
            ':list' => json_encode($localList, JSON_UNESCAPED_UNICODE),
        ]);
        $imported += 1;
    }

    $msg = "Novas: {$imported}. Atualizadas (horários do celular): {$updated}. Ignoradas: {$skipped}.";
    if ($errors) {
        $msg .= ' Com erro de dados: ' . count($errors) . '.';
    }
    admin_flash_set($imported > 0 || $updated > 0 ? 'ok' : 'err', $msg);
    header('Location: dashboard.php');
    exit;
}

$flash = admin_flash_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Importar do navegador · Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="wrap">
      <div class="top">
        <h1>Importar escala do navegador</h1>
        <div class="actions">
          <a class="btn ghost" href="dashboard.php">Voltar</a>
        </div>
      </div>

      <?php if ($flash) : ?>
        <div class="flash <?= admin_h($flash['type']) ?>"><?= admin_h($flash['message']) ?></div>
      <?php endif; ?>

      <div class="card">
        <p class="muted">
          Use isto se a escala aparece no site público mas não está no banco (dados só neste celular/PC).
        </p>
        <ol class="muted" style="line-height: 1.6; padding-left: 1.2rem">
          <li>No mesmo aparelho, abra o <a href="../index.html">site</a>.</li>
          <li>Pressione F12 → aba <strong>Console</strong>.</li>
          <li>Cole e execute:<br />
            <code style="display:block;margin:0.5rem 0;padding:0.5rem;background:#f5f5f5;border-radius:6px;word-break:break-all">copy(localStorage.getItem('icm-oracao-v1'))</code>
          </li>
          <li>Cole abaixo o JSON copiado e envie.</li>
        </ol>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= admin_h(admin_csrf_token()) ?>" />
          <input type="hidden" name="action" value="import" />
          <div class="field">
            <label for="json">JSON do localStorage</label>
            <textarea id="json" name="json" rows="10" required placeholder='{"scales":{...},"tokens":{...}}'></textarea>
          </div>
          <button class="btn" type="submit">Importar para o servidor</button>
        </form>
      </div>
    </div>
  </body>
</html>
