<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/** @var PDO $pdo */

$route = trim((string) ($_GET['route'] ?? ''), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$ownerToken = trim((string) ($_SERVER['HTTP_X_OWNER_TOKEN'] ?? ''));

if ($route === 'health' && $method === 'GET') {
    json_response(200, ['ok' => true]);
}

if ($route === 'scales' && $method === 'GET') {
    $rows = $pdo->query(
        'SELECT id, igreja, motivo, date0, date1, atualizado_em FROM escalas ORDER BY atualizado_em DESC'
    )->fetchAll();
    $scales = [];
    foreach ($rows as $row) {
        $dt = new DateTimeImmutable((string) $row['atualizado_em'], new DateTimeZone('UTC'));
        $scales[] = [
            'id' => $row['id'],
            'igreja' => $row['igreja'],
            'motivo' => $row['motivo'],
            'date0' => $row['date0'],
            'date1' => $row['date1'],
            'timestamp' => $dt->format('Y-m-d\TH:i:s.000\Z'),
        ];
    }
    json_response(200, ['scales' => $scales]);
}

if ($route === 'scales/sync' && $method === 'POST') {
    $body = read_json_body();
    $id = (string) ($body['id'] ?? '');
    $token = (string) ($body['ownerToken'] ?? '');
    $record = $body['record'] ?? null;

    if (!preg_match('/^[a-f0-9]{16}$/', $id) || $token === '' || !is_array($record)) {
        json_response(400, ['error' => 'invalid']);
    }

    $igreja = mb_substr(trim((string) ($record['igreja'] ?? '')), 0, 39);
    $motivo = mb_substr(trim((string) ($record['motivo'] ?? '')), 0, 39);
    $date0 = (string) ($record['date0'] ?? '');
    $date1 = (string) ($record['date1'] ?? '');
    if ($igreja === '' || $motivo === '' || !valid_date($date0) || !valid_date($date1)) {
        json_response(400, ['error' => 'invalid']);
    }

    if (fetch_scale($pdo, $id)) {
        json_response(409, ['error' => 'exists']);
    }

    $list = $record['currentList'] ?? empty_slot_list();
    if (!is_array($list)) {
        $list = empty_slot_list();
    }
    $merged = empty_slot_list();
    foreach (valid_slot_keys() as $key) {
        $val = $list[$key] ?? null;
        if ($val !== null && $val !== '') {
            $merged[$key] = mb_substr(trim((string) $val), 0, 32);
        }
    }

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
        ':list' => json_encode($merged, JSON_UNESCAPED_UNICODE),
    ]);

    $row = fetch_scale($pdo, $id);
    json_response(200, [
        'record' => public_record(row_to_record($row)),
        'synced' => true,
    ]);
}

if ($route === 'scales' && $method === 'POST') {
    $body = read_json_body();
    $igreja = mb_substr(trim((string) ($body['igreja'] ?? '')), 0, 39);
    $motivo = mb_substr(trim((string) ($body['motivo'] ?? '')), 0, 39);
    $date0 = (string) ($body['date0'] ?? '');
    $date1 = (string) ($body['date1'] ?? '');

    if ($igreja === '' || $motivo === '' || !valid_date($date0) || !valid_date($date1)) {
        json_response(400, ['error' => 'invalid']);
    }
    if ($date1 < $date0) {
        json_response(400, ['error' => 'period']);
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

    $row = fetch_scale($pdo, $id);
    json_response(200, [
        'record' => public_record(row_to_record($row)),
        'ownerToken' => $token,
    ]);
}

if (preg_match('#^scales/([a-f0-9]{16})$#', $route, $m) && $method === 'GET') {
    $row = fetch_scale($pdo, $m[1]);
    if (!$row) {
        json_response(404, ['error' => 'missing']);
    }
    json_response(200, public_record(row_to_record($row)));
}

if (preg_match('#^scales/([a-f0-9]{16})$#', $route, $m) && $method === 'DELETE') {
    json_response(403, ['error' => 'admin_only']);
}

if (preg_match('#^scales/([a-f0-9]{16})/slot$#', $route, $m) && $method === 'POST') {
    $body = read_json_body();
    $interval = (string) ($body['interval'] ?? '');
    $hasName = array_key_exists('name', $body);
    $name = $hasName ? $body['name'] : null;

    if (!in_array($interval, valid_slot_keys(), true)) {
        json_response(400, ['error' => 'interval']);
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM escalas WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $m[1]]);
        $row = $lock->fetch();
        if (!$row) {
            $pdo->rollBack();
            json_response(404, ['error' => 'missing']);
        }

        $record = row_to_record($row);
        $public = public_record($record);

        $previousName = array_key_exists('previousName', $body)
            ? mb_substr(trim((string) $body['previousName']), 0, 32)
            : null;
        $current = $record['currentList'][$interval] ?? null;

        if ($name === null) {
            if (
                $previousName === null
                || $previousName === ''
                || !slot_names_match((string) $current, $previousName)
            ) {
                $pdo->rollBack();
                json_response(403, ['error' => 'forbidden', 'record' => $public]);
            }
            $record['currentList'][$interval] = null;
        } else {
            $clean = mb_substr(trim((string) $name), 0, 32);
            if ($clean === '') {
                $pdo->rollBack();
                json_response(400, ['error' => 'name', 'record' => $public]);
            }
            if ($current === null || $current === '') {
                $record['currentList'][$interval] = $clean;
            } elseif (
                $previousName !== null
                && $previousName !== ''
                && slot_names_match((string) $current, $previousName)
            ) {
                $record['currentList'][$interval] = $clean;
            } else {
                $pdo->rollBack();
                json_response(409, ['error' => 'occupied', 'record' => $public]);
            }
        }

        $upd = $pdo->prepare(
            'UPDATE escalas SET current_list = :list, atualizado_em = UTC_TIMESTAMP() WHERE id = :id'
        );
        $upd->execute([
            ':list' => json_encode($record['currentList'], JSON_UNESCAPED_UNICODE),
            ':id' => $m[1],
        ]);
        $pdo->commit();

        $fresh = fetch_scale($pdo, $m[1]);
        json_response(200, ['record' => public_record(row_to_record($fresh))]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(500, ['error' => 'server']);
    }
}

json_response(404, ['error' => 'not_found']);

function fetch_scale(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM escalas WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
