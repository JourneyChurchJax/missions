<?php
// E-signatures and background checks.

// ---------- E-signatures ----------
// A "sign" task is done when the traveler has signed, plus a parent when the traveler is under 18 and the task asks for one.
function task_signatures(int $task_id, int $person_id): array {
    return all('SELECT * FROM signatures WHERE task_id = ? AND person_id = ? ORDER BY id', [$task_id, $person_id]);
}
function needs_parent_signature(array $task, int $person_id): bool {
    if (empty($task['parent_sign'])) return false;
    $p = person($person_id); $t = trip((int)$task['trip_id']);
    return $p && $t && is_minor($p, $t['start_date']);
}
// [traveler signed?, parent signed?, parent needed?]. A paper form a leader recorded counts for both.
function signature_state(array $task, int $person_id): array {
    $roles = array_column(task_signatures((int)$task['id'], $person_id), 'signer_role');
    $paper = in_array('paper', $roles, true);
    return [$paper || in_array('traveler', $roles, true), $paper || in_array('parent', $roles, true), needs_parent_signature($task, $person_id)];
}
// Kept for older callers: signature tasks are now done when the signatures exist (see tasks_for)
function sync_signature_task(array $task, int $person_id): void {}

// The document being signed: its file id and a fingerprint (SHA-256) of the exact file, so we can prove which version was signed
function doc_fingerprint(?int $file_id): array {
    if (!$file_id || !($f = one('SELECT * FROM files WHERE id = ?', [$file_id]))) return [null, null, null];
    $hash = $f['sha256'];
    if (!$hash && $f['path'] && is_file($p = data_dir() . '/uploads/' . basename($f['path']))) { $hash = hash_file('sha256', $p); update('files', $file_id, ['sha256' => $hash]); }
    if (!$hash && $f['url']) $hash = 'link:' . hash('sha256', (string)$f['url']);
    return [$file_id, $hash, $f['title']];
}
function document_has_signatures(int $file_id): bool { return (bool)val('SELECT COUNT(*) FROM signatures WHERE doc_file_id = ?', [$file_id]); }

// Save a signature, then email the signer a copy of what they agreed to
function record_signature(array $task, int $person_id, string $role, string $name, ?string $image, string $mode, ?array $guardian = null, string $agreement = ''): int {
    [$fid, $hash, $title] = doc_fingerprint($task['file_id'] ? (int)$task['file_id'] : null);
    $agreement = $agreement !== '' ? $agreement : ((string)$task['description'] ?: 'I have read and agree to this document.');
    $id = insert('signatures', ['task_id' => $task['id'], 'person_id' => $person_id, 'guardian_id' => $guardian['id'] ?? null, 'signer_role' => $role, 'signer_name' => mb_substr($name, 0, 120),
        'sig_image' => $image, 'sig_mode' => $mode, 'agreement' => $agreement, 'doc_title' => $title ?? $task['title'], 'doc_file_id' => $fid, 'doc_hash' => $hash,
        'ip' => client_ip(), 'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 'recorded_by' => $role === 'paper' ? current_actor_name() : null, 'signed_at' => now()]);
    audit('signature', 'signatures', $id, (int)$task['trip_id'], ['task' => $task['id'], 'person' => $person_id, 'role' => $role, 'mode' => $mode, 'doc_hash' => $hash]);
    $to = $role === 'parent' ? ($guardian['email'] ?? null) : ($role === 'traveler' ? val('SELECT email FROM people WHERE id = ?', [$person_id]) : null);
    if ($to) queue_email((string)$to, 'Your signed copy: ' . ($title ?? $task['title']), "This is your record of what you signed.\n\nDocument: " . ($title ?? $task['title']) . "\nSigned by: $name" . ($role === 'parent' ? ' (parent or guardian of ' . full_name(person_basic($person_id)) . ')' : '')
        . "\nDate: " . date('F j, Y \a\t g:i A T') . "\nWhat you agreed to: $agreement" . ($hash ? "\nDocument fingerprint: " . substr($hash, 0, 16) : '') . "\n\nQuestions? Reply to this email.", (int)$task['trip_id'], $person_id);
    return $id;
}

// ---------- Parent sign-in code ----------
// Before a parent signs, we send a 6-digit code to the email (or phone) we have for them. It proves the right person is signing.
function guardian_send_code(array $g): bool {
    if (rate_limited('otp:' . $g['id'], 5, 3600)) return false;
    $code = (string)random_int(100000, 999999);
    update('guardians', (int)$g['id'], ['otp_hash' => password_hash($code, PASSWORD_DEFAULT), 'otp_expires' => time() + 900, 'otp_tries' => 0]);
    $msg = "Your Journey Missions code is $code. It works for 15 minutes.";
    if ($g['email'] && mail_ready()) return send_email((string)$g['email'], 'Your signing code: ' . $code, $msg . "\n\nIf you didn't ask for this, you can ignore it.", null, (int)$g['person_id']);
    if ($g['phone'] && text_ready()) return send_text((string)$g['phone'], $msg, null, (int)$g['person_id'], true);
    return false;
}
function guardian_check_code(array $g, string $code): bool {
    if ((int)$g['otp_tries'] >= 5 || (int)$g['otp_expires'] < time() || !$g['otp_hash']) return false;
    update('guardians', (int)$g['id'], ['otp_tries' => (int)$g['otp_tries'] + 1]);
    if (!password_verify(preg_replace('/\D/', '', $code), (string)$g['otp_hash'])) return false;
    update('guardians', (int)$g['id'], ['otp_hash' => null, 'verified_at' => now()]);
    $_SESSION['guardian_ok'][(int)$g['id']] = time();
    return true;
}
// A verified parent stays verified in this browser for 2 hours
function guardian_verified(array $g): bool { return (int)($_SESSION['guardian_ok'][(int)$g['id']] ?? 0) > time() - 7200; }

// Accepts a PNG data URL from the signature pad; returns it only if it really is a small PNG
function clean_signature_image(?string $data): ?string {
    if (!$data || !str_starts_with($data, 'data:image/png;base64,')) return null;
    $bin = base64_decode(substr($data, 22), true);
    if ($bin === false || strlen($bin) > 300000 || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;
    return $data;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }
// A minor's birth date (and legal name) can't be changed by the traveler once they're on a trip: staff review the change
function locked_person_fields(int $person_id): array {
    if (!val('SELECT COUNT(*) FROM members WHERE person_id = ?', [$person_id])) return [];
    return ['first_name', 'last_name', 'birth_date', 'passport_name'];
}

// ---------- Background checks ----------
const BG_STATUS = ['requested' => 'Requested', 'clear' => 'Clear', 'review' => 'Needs review', 'expired' => 'Expired'];
const BG_RULES = ['none' => 'Not required', 'leaders' => 'Leaders and admins', 'adults' => 'Every adult on the trip'];
function latest_bg(int $person_id): ?array { return one('SELECT * FROM background_checks WHERE person_id = ? ORDER BY COALESCE(completed_at, requested_at) DESC, id DESC LIMIT 1', [$person_id]); }
// 'ok', 'expiring' (within 60 days of the trip ending), 'pending', 'missing', 'review'
function bg_state(?array $bg, ?string $needed_through = null): string {
    if (!$bg) return 'missing';
    if ($bg['status'] === 'review') return 'review';
    if ($bg['status'] === 'expired') return 'missing';
    if ($bg['status'] === 'requested') return 'pending';
    if ($bg['status'] !== 'clear' || ($bg['expires_on'] && $bg['expires_on'] < date('Y-m-d'))) return 'missing';
    if ($bg['expires_on'] && $needed_through && $bg['expires_on'] < $needed_through) return 'expiring';
    return 'ok';
}
const BG_STATE_LABEL = ['ok' => 'Clear', 'expiring' => 'Expires before the trip ends', 'pending' => 'In progress', 'missing' => 'Needed', 'review' => 'Needs review'];
// Members of a trip who need a check under the trip's rule
function bg_needed(array $trip): array {
    $rule = $trip['bg_required'] ?: 'leaders';
    if ($rule === 'none') return [];
    return array_values(array_filter(members((int)$trip['id']), function ($m) use ($rule, $trip) {
        if (in_array($m['role'], ['leader', 'admin'], true)) return true;
        return $rule === 'adults' && $m['birth_date'] && !is_minor(['birth_date' => $m['birth_date']], $trip['start_date']);
    }));
}
