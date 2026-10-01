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
// [traveler signed?, parent signed?, parent needed?]
function signature_state(array $task, int $person_id): array {
    $sigs = task_signatures((int)$task['id'], $person_id);
    $roles = array_column($sigs, 'signer_role');
    return [in_array('traveler', $roles, true), in_array('parent', $roles, true), needs_parent_signature($task, $person_id)];
}
function sync_signature_task(array $task, int $person_id): void {
    [$me, $parent, $need] = signature_state($task, $person_id);
    $done = one('SELECT id FROM task_done WHERE task_id = ? AND person_id = ?', [$task['id'], $person_id]);
    if ($me && (!$need || $parent)) { if (!$done) insert('task_done', ['task_id' => $task['id'], 'person_id' => $person_id, 'done_at' => now()]); }
}
// Accepts a PNG data URL from the signature pad; returns it only if it really is a small PNG
function clean_signature_image(?string $data): ?string {
    if (!$data || !str_starts_with($data, 'data:image/png;base64,')) return null;
    $bin = base64_decode(substr($data, 22), true);
    if ($bin === false || strlen($bin) > 300000 || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;
    return $data;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }

// ---------- Background checks ----------
const BG_STATUS = ['requested' => 'Requested', 'clear' => 'Clear', 'review' => 'Needs review', 'expired' => 'Expired'];
const BG_RULES = ['none' => 'Not required', 'leaders' => 'Leaders and admins', 'adults' => 'Every adult on the trip'];
function latest_bg(int $person_id): ?array { return one('SELECT * FROM background_checks WHERE person_id = ? ORDER BY COALESCE(completed_at, requested_at) DESC, id DESC LIMIT 1', [$person_id]); }
// 'ok', 'expiring' (within 60 days of the trip ending), 'pending', 'missing', 'review'
function bg_state(?array $bg, ?string $needed_through = null): string {
    if (!$bg) return 'missing';
    if ($bg['status'] === 'review') return 'review';
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
