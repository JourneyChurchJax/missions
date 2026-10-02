<?php
// Privacy: encryption for the most sensitive fields, cleanup after trips, delete and export a person,
// and the once-a-day housekeeping (backup, cleanup, sending queued email).

// Passport numbers and medical details are encrypted in the database. The key lives in config.php
// ('data_key' => base64 of 32 random bytes) or, if that's missing, in data/secret.key (outside the website).
const SENSITIVE_FIELDS = ['passport_number', 'health', 'allergies', 'meds', 'diet', 'other'];

function data_key(): ?string {
    static $key = null;
    if ($key !== null) return $key ?: null;
    global $config;
    if (!function_exists('sodium_crypto_secretbox')) { $key = ''; return null; }
    if (!empty($config['data_key'])) { $k = base64_decode((string)$config['data_key'], true); if ($k !== false && strlen($k) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) return $key = $k; }
    $f = data_dir() . '/secret.key';
    if (!is_file($f)) { file_put_contents($f, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), LOCK_EX); @chmod($f, 0600); }
    $k = base64_decode(trim((string)file_get_contents($f)), true);
    return $key = ($k !== false && strlen($k) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $k : '');
}
function enc(?string $v): ?string {
    if ($v === null || $v === '' || str_starts_with($v, 'enc1:') || !($k = data_key())) return $v;
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'enc1:' . base64_encode($n . sodium_crypto_secretbox($v, $n, $k));
}
function dec(?string $v): ?string {
    if ($v === null || !str_starts_with($v, 'enc1:')) return $v;
    if (!($k = data_key())) return '[locked]';
    $raw = base64_decode(substr($v, 5), true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '[unreadable]';
    $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $k);
    return $out === false ? '[unreadable]' : $out;
}
function encrypt_person_fields(array $row): array { foreach (SENSITIVE_FIELDS as $f) if (array_key_exists($f, $row) && is_string($row[$f])) $row[$f] = enc($row[$f]); return $row; }
function decrypt_person(array $p): array { foreach (SENSITIVE_FIELDS as $f) if (isset($p[$f]) && is_string($p[$f])) $p[$f] = dec($p[$f]); return $p; }
function mask_passport(?string $n): string { $n = (string)$n; return $n === '' ? '' : str_repeat('•', max(0, mb_strlen($n) - 4)) . mb_substr($n, -4); }

// Private links and one-time codes inside saved messages are hidden, so the outbox can't be used to open someone's page
function redact_tokens(string $s): string {
    $s = (string)preg_replace('/([?&](?:t|g|p|k)=)[A-Za-z0-9]{16,}/', '$1[private link]', $s);
    return (string)preg_replace('/(\bcode(?: is|:)?\s*)\d{6}\b/i', '$1••••••', $s);
}

// ---------- Cleanup after trips ----------
// Passport numbers, medical details and passport scans are erased this many days after someone's last trip ends.
function retention_days(): int { global $config; return max(30, (int)($config['retention_days'] ?? 120)); }
function purge_old_sensitive(): int {
    $cut = date('Y-m-d', strtotime('-' . retention_days() . ' days'));
    $ids = array_column(all("SELECT p.id FROM people p WHERE EXISTS (SELECT 1 FROM members m WHERE m.person_id = p.id)
        AND NOT EXISTS (SELECT 1 FROM members m JOIN trips t ON t.id = m.trip_id WHERE m.person_id = p.id AND t.end_date >= ?)
        AND (p.passport_number IS NOT NULL OR p.health IS NOT NULL OR p.allergies IS NOT NULL OR p.meds IS NOT NULL OR p.diet IS NOT NULL)", [$cut]), 'id');
    foreach ($ids as $id) {
        update('people', (int)$id, ['passport_number' => null, 'health' => null, 'allergies' => null, 'meds' => null, 'diet' => null, 'other' => null]);
        foreach (all("SELECT * FROM files WHERE person_id = ? AND kind = 'upload'", [$id]) as $f) delete_file_record((int)$f['id']);
    }
    // Applications started but never sent, untouched for 60 days
    foreach (all("SELECT * FROM applications WHERE status = 'draft' AND updated_at < ?", [date('Y-m-d H:i:s', strtotime('-60 days'))]) as $a) {
        delete_row('applications', (int)$a['id']);
        if (!val('SELECT COUNT(*) FROM members WHERE person_id = ?', [$a['person_id']]) && !val('SELECT COUNT(*) FROM applications WHERE person_id = ?', [$a['person_id']])) q('DELETE FROM people WHERE id = ?', [$a['person_id']]);
    }
    if ($ids) audit('privacy_purge', 'people', null, null, ['people' => count($ids)]);
    return count($ids);
}

// Remove a file record and its file on disk (unless another record still points at the same file)
function delete_file_record(int $id): void {
    $f = one('SELECT * FROM files WHERE id = ?', [$id]);
    if (!$f) return;
    delete_row('files', $id);
    q('UPDATE tasks SET file_id = NULL WHERE file_id = ?', [$id]);
    if ($f['path'] && !val('SELECT COUNT(*) FROM files WHERE path = ?', [$f['path']])) {
        foreach ([data_dir() . '/uploads/', data_dir() . '/uploads-demo/'] as $d) if (is_file($d . basename($f['path']))) @unlink($d . basename($f['path']));
    }
}

// ---------- Delete or export one person ----------
// People tied to money or signatures are anonymized (records must be kept); everyone else is fully removed.
function delete_person(int $id): string {
    $p = person($id);
    if (!$p) return 'gone';
    $keep = val('SELECT COUNT(*) FROM gifts WHERE person_id = ?', [$id]) || val('SELECT COUNT(*) FROM payments WHERE person_id = ?', [$id]) || val('SELECT COUNT(*) FROM signatures WHERE person_id = ?', [$id]);
    foreach (all('SELECT id FROM files WHERE person_id = ?', [$id]) as $f) delete_file_record((int)$f['id']);
    foreach (['task_done', 'attendance', 'file_acks', 'guardians', 'checkin_marks', 'login_codes'] as $t) q("DELETE FROM $t WHERE person_id = ?", [$id]);
    q('DELETE FROM chat WHERE person_id = ?', [$id]);
    if ($keep) {
        $blank = array_fill_keys(['preferred_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'state', 'zip', 'shirt', 'passport_name', 'passport_number', 'passport_country',
            'passport_issued', 'passport_expires', 'nationality', 'ec1_name', 'ec1_rel', 'ec1_phone', 'ec1_email', 'ec2_name', 'ec2_rel', 'ec2_phone', 'health', 'diet', 'allergies', 'meds', 'other',
            'notes', 'pco_id', 'tags', 'cal_token'], null);
        update('people', $id, $blank + ['first_name' => 'Removed', 'last_name' => 'person #' . $id, 'is_staff' => 0]);
        q("UPDATE members SET page_status = 'hidden', page_story = NULL, page_photo_id = NULL WHERE person_id = ?", [$id]);
        return 'anonymized';
    }
    q('DELETE FROM members WHERE person_id = ?', [$id]);
    foreach (all('SELECT id FROM applications WHERE person_id = ?', [$id]) as $a) { q('DELETE FROM app_refs WHERE application_id = ?', [$a['id']]); delete_row('applications', (int)$a['id']); }
    q('DELETE FROM background_checks WHERE person_id = ?', [$id]);
    delete_row('people', $id);
    return 'deleted';
}
function export_person(int $id): array {
    $p = person($id); unset($p['cal_token']);
    return [
        'person' => $p,
        'trips' => all('SELECT t.name, t.start_date, t.end_date, m.role, m.room, m.seat FROM members m JOIN trips t ON t.id = m.trip_id WHERE m.person_id = ?', [$id]),
        'checklist_done' => all('SELECT t.title, d.done_at FROM task_done d JOIN tasks t ON t.id = d.task_id WHERE d.person_id = ?', [$id]),
        'signatures' => all('SELECT doc_title, signer_role, signer_name, agreement, signed_at, ip FROM signatures WHERE person_id = ?', [$id]),
        'payments' => all('SELECT amount, kind, method, paid_on, note FROM payments WHERE person_id = ?', [$id]),
        'gifts_received' => all("SELECT amount, gift_date, CASE WHEN anonymous = 1 THEN 'Anonymous' ELSE donor_id END AS donor FROM gifts WHERE person_id = ?", [$id]),
        'applications' => all('SELECT status, answers, submitted_at FROM applications WHERE person_id = ?', [$id]),
        'parents' => all('SELECT name, rel, email, phone FROM guardians WHERE person_id = ?', [$id]),
        'background_checks' => all('SELECT provider, status, completed_at, expires_on FROM background_checks WHERE person_id = ?', [$id]),
        'exported_at' => now(),
    ];
}

// ---------- Housekeeping ----------
// Runs after the page is sent: queued email every request; backup and cleanup once a day.
function housekeeping(): void {
    if (defined('NO_HOUSEKEEPING') || demo_on()) return;
    try {
        process_outbox(15);
        $stamp = data_dir() . '/last-daily';
        if (!is_file($stamp) || filemtime($stamp) < time() - 86400) {
            touch($stamp);
            backup_now('daily');
            purge_old_sensitive();
            q('DELETE FROM rate_hits WHERE at < ?', [time() - 86400]);
            q('DELETE FROM login_codes WHERE expires < ?', [time() - 3600]);
        }
    } catch (Throwable $e) { app_log('Housekeeping: ' . $e->getMessage(), 'errors'); }
}
register_shutdown_function(function () {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    housekeeping();
});
