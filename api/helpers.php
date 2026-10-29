<?php
declare(strict_types=1);

function empty_slot_list(): array
{
    $list = [];
    for ($i = 0; $i < 96; $i++) {
        $start = $i * 15;
        $end = $start + 15;
        $list[sprintf('%s às %s', clock($start), clock($end))] = null;
    }
    return $list;
}

function clock(int $totalMinutes): string
{
    $minutes = $totalMinutes % (24 * 60);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function valid_slot_keys(): array
{
    static $keys = null;
    if ($keys === null) {
        $keys = array_keys(empty_slot_list());
    }
    return $keys;
}

function normalize_slot_list(array $input): array
{
    $merged = empty_slot_list();
    foreach (valid_slot_keys() as $key) {
        $val = $input[$key] ?? null;
        if ($val !== null && $val !== '') {
            $merged[$key] = mb_substr(trim((string) $val), 0, 32);
        }
    }
    return $merged;
}

/** Prefer non-empty values from $local, keep server when local is empty. */
function merge_slot_lists(array $server, array $local): array
{
    $server = normalize_slot_list($server);
    $local = normalize_slot_list($local);
    $out = empty_slot_list();
    foreach (valid_slot_keys() as $key) {
        $l = $local[$key] ?? null;
        $s = $server[$key] ?? null;
        if ($l !== null && $l !== '') {
            $out[$key] = $l;
        } elseif ($s !== null && $s !== '') {
            $out[$key] = $s;
        }
    }
    return $out;
}

function count_filled_slots(array $list): int
{
    $n = 0;
    foreach (valid_slot_keys() as $key) {
        $v = $list[$key] ?? null;
        if ($v !== null && $v !== '') {
            $n += 1;
        }
    }
    return $n;
}

function slot_fill_percentage(int $filled, int $total = 96): string
{
    if ($total <= 0) {
        return '0';
    }
    $pct = round($filled / $total * 1000) / 10;
    return number_format($pct, 1, ',', '.');
}

function slot_names_match(?string $a, ?string $b): bool
{
    return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
}

function format_datetime_br(?string $utc): string
{
    if ($utc === null || trim($utc) === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $dt = $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i:s');
    } catch (Exception) {
        return $utc;
    }
}

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function new_id(): string
{
    return bin2hex(random_bytes(8));
}

function new_token(): string
{
    return bin2hex(random_bytes(16));
}

function valid_date(string $value): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function row_to_record(array $row): array
{
    $list = json_decode($row['current_list'], true);
    if (!is_array($list)) {
        $list = empty_slot_list();
    }

    $ts = (string) $row['atualizado_em'];
    $dt = new DateTimeImmutable($ts, new DateTimeZone('UTC'));
    $iso = $dt->format('Y-m-d\TH:i:s.000\Z');

    return [
        'id' => $row['id'],
        'igreja' => $row['igreja'],
        'motivo' => $row['motivo'],
        'date0' => $row['date0'],
        'date1' => $row['date1'],
        'timestamp' => $iso,
        'currentList' => $list,
    ];
}

function public_record(array $record): array
{
    return [
        'id' => $record['id'],
        'igreja' => $record['igreja'],
        'motivo' => $record['motivo'],
        'date0' => $record['date0'],
        'date1' => $record['date1'],
        'timestamp' => $record['timestamp'],
        'currentList' => $record['currentList'],
    ];
}
