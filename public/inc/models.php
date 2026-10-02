<?php
// App helpers: who is acting, form safety, flash messages, and the trip/people queries pages share.

// ---------- Who is acting ----------
function acting_person_id(): ?int {
    if (!empty($_SESSION['auth']) && empty($_SESSION['auth']['staff'])) return (int)$_SESSION['auth']['person_id'];
    if (!is_staff_session()) return null;
    if (!empty($_SESSION['person_id']) && val('SELECT COUNT(*) FROM members WHERE person_id = ?', [(int)$_SESSION['person_id']])) return (int)$_SESSION['person_id'];
    $id = val("SELECT p.id FROM people p JOIN members m ON m.person_id = p.id WHERE m.role = 'traveler' ORDER BY p.id LIMIT 1");
    return $id ? (int)$id : null;
}
// The real person behind this session, for records: never the traveler being previewed
function current_actor_name(): string {
    global $config;
    if (!empty($_SESSION['auth']['name'])) return (string)$_SESSION['auth']['name'];
    if (!empty($_SESSION['preview_ok'])) return (string)($config['preview_name'] ?? 'Staff (preview password)');
    return defined('ACTOR') ? ACTOR : 'Public';
}
function current_actor_initials(): string { return initials(preg_replace('/\s*\(.*\)$/', '', current_actor_name())) ?: 'S'; }

// ---------- Forms ----------
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('This form expired. Go back, refresh the page, and try again.'); }
}
// One-time form token: stops a double click from saving or sending twice
function once(): string { $t = bin2hex(random_bytes(8)); return '<input type="hidden" name="once" value="' . $t . '">'; }
function check_once(): bool {
    $t = (string)($_POST['once'] ?? '');
    if ($t === '') return true;
    $used = $_SESSION['once_used'] ?? [];
    if (in_array($t, $used, true)) return false;
    $used[] = $t; $_SESSION['once_used'] = array_slice($used, -100);
    return true;
}
function flash(?string $msg = null, string $type = 'ok'): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; $_SESSION['flash_type'] = $type; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}
function flash_type(): string { $t = $_SESSION['flash_type'] ?? 'ok'; unset($_SESSION['flash_type']); return $t; }
function fail(string $msg): never { flash($msg, 'error'); back(); }
// Return to the page the form came from (same site only)
function back(string $fallback = '/'): never {
    $to = is_string($_POST['back'] ?? null) && $_POST['back'] !== '' ? $_POST['back'] : null;
    if (!$to && !empty($_SERVER['HTTP_REFERER'])) {
        $r = parse_url((string)$_SERVER['HTTP_REFERER']);
        if (($r['host'] ?? '') === ($_SERVER['HTTP_HOST'] ?? '') || ($r['host'] ?? '') === ($_SERVER['SERVER_NAME'] ?? '')) $to = ($r['path'] ?? '/') . (isset($r['query']) ? '?' . $r['query'] : '');
    }
    header('Location: ' . safe_path($to, $fallback)); exit;
}
function post(string $k, $default = null) { $v = $_POST[$k] ?? $default; return is_string($v) ? trim($v) : $v; }
function ps(string $k, int $max = 2000): string { $v = $_POST[$k] ?? ''; return is_scalar($v) ? mb_substr(trim((string)$v), 0, $max) : ''; }
function nn($v) { return ($v === '' || $v === null) ? null : $v; }

// ---------- Dates ----------
function fdate(?string $d, string $fmt = 'M j, Y'): string { return $d ? date($fmt, strtotime($d)) : ''; }
function date_range(string $a, string $b): string {
    $x = strtotime($a); $y = strtotime($b);
    if (date('Y-m', $x) === date('Y-m', $y)) return date('F j', $x) . '–' . date('j, Y', $y);
    return date('M j', $x) . ' – ' . date('M j, Y', $y);
}
function days_until(string $d): int {
    $a = new DateTime('today'); $b = new DateTime(substr($d, 0, 10));
    return $b > $a ? (int)$a->diff($b)->days : 0;
}

// ---------- People ----------
function person(int $id): ?array { $p = one('SELECT * FROM people WHERE id = ?', [$id]); return $p ? decrypt_person($p) : null; }
function full_name(?array $p): string { return $p ? trim((($p['preferred_name'] ?? '') ?: ($p['first_name'] ?? '')) . ' ' . ($p['last_name'] ?? '')) : 'Someone'; }
function is_minor(array $p, string $on): bool { return !empty($p['birth_date']) && strtotime($p['birth_date'] . ' +18 years') > strtotime($on); }

// ---------- Trips ----------
function trip(int $id): ?array { return one('SELECT * FROM trips WHERE id = ?', [$id]); }
function trip_by_slug(string $slug): ?array { return one('SELECT * FROM trips WHERE slug = ?', [$slug]); }
function trips(string $which = 'upcoming'): array {
    $cut = date('Y-m-d', strtotime('-30 days'));   // trips stay "upcoming" until 30 days after they end
    return match ($which) {
        'upcoming' => all("SELECT * FROM trips WHERE status = 'active' AND end_date >= ? ORDER BY start_date", [$cut]),
        'past' => all("SELECT * FROM trips WHERE status = 'active' AND end_date < ? ORDER BY start_date DESC", [$cut]),
        'cancelled' => all("SELECT * FROM trips WHERE status IN ('cancelled','postponed') ORDER BY start_date"),
        default => all('SELECT * FROM trips ORDER BY start_date'),
    };
}
function members(int $trip_id): array {
    static $cache = [];
    $key = $trip_id . ':' . ($GLOBALS['_dbv'] ?? 0) . ':' . (demo_on() ? 'd' : 'r');
    if (isset($cache[$key])) return $cache[$key];
    return $cache[$key] = array_map('decrypt_person', all("SELECT m.*, p.first_name, p.preferred_name, p.last_name, p.email, p.phone, p.birth_date, p.passport_number, p.passport_expires,
                p.ec1_name, p.ec1_phone, p.health, p.allergies, p.meds, p.diet, p.shirt
                FROM members m JOIN people p ON p.id = m.person_id WHERE m.trip_id = ? ORDER BY CASE m.role WHEN 'admin' THEN 0 WHEN 'leader' THEN 1 ELSE 2 END, p.last_name", [$trip_id]));
}
function member_of(int $trip_id, int $person_id): ?array { return one('SELECT * FROM members WHERE trip_id = ? AND person_id = ?', [$trip_id, $person_id]); }
// The trip a traveler sees: their next or current trip; otherwise their most recent one
function trip_for_person(int $person_id): ?array {
    return one("SELECT t.* FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ? AND t.status = 'active' AND t.end_date >= ? ORDER BY t.start_date LIMIT 1", [$person_id, date('Y-m-d', strtotime('-14 days'))])
        ?? one("SELECT t.* FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ? AND t.status = 'active' ORDER BY t.start_date DESC LIMIT 1", [$person_id]);
}
function travelers(int $trip_id): array { return array_values(array_filter(members($trip_id), fn($m) => (int)$m['traveling'] === 1)); }
function trip_budget(int $trip_id): float {
    $n = max(1, count(travelers($trip_id)));
    $rows = all('SELECT * FROM budget WHERE trip_id = ?', [$trip_id]);
    return array_sum(array_map(fn($b) => (float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']), $rows));
}
function member_goal(array $trip, ?array $m): float { return (float)(($m['goal'] ?? 0) > 0 ? $m['goal'] : ($trip['cost_per_person'] ?? 0)); }
// Everything credited to a trip, straight from the ledger: gifts (traveler and team) plus traveler payments
function trip_raised(int $trip_id): float {
    return (float)val("SELECT COALESCE(SUM(amount - COALESCE(refunded,0) - COALESCE(covered_fee,0)),0) FROM gifts WHERE trip_id = ? AND status IN ('cleared','refunded','disputed')", [$trip_id])
        + (float)val("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END),0) FROM payments WHERE trip_id = ? AND COALESCE(status,'ok') <> 'void'", [$trip_id]);
}
function trip_goal(array $trip): float {
    $sum = 0; foreach (travelers((int)$trip['id']) as $m) $sum += member_goal($trip, $m); return $sum;
}

// ---------- Tasks and readiness ----------
const TASK_TYPES = ['traveler' => 'Traveler task', 'sign' => 'Sign a document', 'upload' => 'Upload a document', 'verify' => 'Confirm their info', 'leader' => 'Leader task', 'admin' => 'Admin task', 'staff' => 'Staff task'];
function traveler_tasks(int $trip_id): array {
    return all("SELECT * FROM tasks WHERE trip_id = ? AND type IN ('traveler','sign','upload','verify') ORDER BY due_date", [$trip_id]);
}
// One person's checklist. Loaded once per trip per request, so a team page doesn't run hundreds of queries.
// Signature tasks count as done only when the signatures exist (traveler, plus a parent for under-18s when the task asks).
function tasks_for(int $trip_id, int $person_id): array {
    static $cache = [];
    $k = $trip_id . ':' . ($GLOBALS['_dbv'] ?? 0) . ':' . (demo_on() ? 'd' : 'r');
    if (!isset($cache[$k])) {
        $tasks = all("SELECT t.*, t.file_id AS doc_id FROM tasks t WHERE t.trip_id = ? AND t.type IN ('traveler','sign','upload','verify') ORDER BY t.due_date, t.id", [$trip_id]);
        $done = []; foreach (all('SELECT d.* FROM task_done d JOIN tasks t ON t.id = d.task_id WHERE t.trip_id = ?', [$trip_id]) as $d) $done[$d['task_id']][$d['person_id']] = $d;
        $sigs = []; foreach (all('SELECT s.task_id, s.person_id, s.signer_role, s.signed_at FROM signatures s JOIN tasks t ON t.id = s.task_id WHERE t.trip_id = ?', [$trip_id]) as $sg) $sigs[$sg['task_id']][$sg['person_id']][$sg['signer_role']] = $sg['signed_at'];
        $cache = [$k => [$tasks, $done, $sigs, trip($trip_id)]];
    }
    [$tasks, $done, $sigs, $trip] = $cache[$k];
    $p = person_basic($person_id);
    $minor = $p && $trip && is_minor($p, $trip['start_date']);
    $out = [];
    foreach ($tasks as $t) {
        if ($t['minors_only'] && !$minor) continue;
        $d = $done[$t['id']][$person_id] ?? null;
        $t['done_at'] = $d['done_at'] ?? null; $t['file_id'] = $d['file_id'] ?? null;
        if ($t['type'] === 'sign') {
            $s = $sigs[$t['id']][$person_id] ?? [];
            $me = $s['traveler'] ?? $s['paper'] ?? null;
            $parent = $s['parent'] ?? $s['paper'] ?? null;
            $t['done_at'] = $me && (!($t['parent_sign'] && $minor) || $parent) ? max($me, $parent ?? $me) : null;
        }
        $out[] = $t;
    }
    return $out;
}
// Name and birth date only (cheap, cached): used by checklists
function person_basic(int $id): ?array {
    static $c = [];
    $k = $id . ':' . ($GLOBALS['_dbv'] ?? 0);
    return $c[$k] ??= one('SELECT id, first_name, preferred_name, last_name, birth_date, email FROM people WHERE id = ?', [$id]);
}
function readiness(int $trip_id, int $person_id): array {
    $t = tasks_for($trip_id, $person_id);
    $done = count(array_filter($t, fn($x) => $x['done_at']));
    return [$done, count($t)];
}
function trip_ready_count(int $trip_id): int {
    $n = 0; foreach (travelers($trip_id) as $m) { [$d, $t] = readiness($trip_id, (int)$m['person_id']); if ($d >= $t) $n++; } return $n;
}
function trip_task_pct(int $trip_id): array {
    $done = 0; $total = 0;
    foreach (travelers($trip_id) as $m) { [$d, $t] = readiness($trip_id, (int)$m['person_id']); $done += $d; $total += $t; }
    return [$done, $total];
}
function passport_ok(array $m, array $trip): bool {
    return !empty($m['passport_expires']) && (empty($trip['passport_valid_through']) || $m['passport_expires'] >= $trip['passport_valid_through']);
}

// ---------- Attention items for a trip ----------
function trip_alerts(array $trip): array {
    $alerts = []; $id = (int)$trip['id'];
    $n = max(1, count(travelers($id)));
    $per = trip_budget($id) / $n;
    if ($per > 0 && abs($per - (float)$trip['cost_per_person']) > 1) $alerts[] = ['Goal is ' . money((float)$trip['cost_per_person']) . ' a person', 'The budget works out to ' . money($per), "/admin/trip.php?id=$id&tab=budget"];
    $missing = array_values(array_filter(travelers($id), fn($m) => !passport_ok($m, $trip)));
    if (count($missing) === 1) $alerts[] = [full_name($missing[0]) . ' has no valid passport on file', 'Must be valid through ' . fdate($trip['passport_valid_through'] ?: $trip['end_date']), "/admin/trip.php?id=$id&tab=team"];
    elseif ($missing) $alerts[] = [count($missing) . ' travelers need a valid passport', implode(', ', array_map('full_name', array_slice($missing, 0, 3))) . (count($missing) > 3 ? ' and more' : ''), "/admin/reports.php?trip=$id&view=readiness"];
    $bgmiss = array_values(array_filter(bg_needed($trip), fn($m) => !in_array(bg_state(latest_bg((int)$m['person_id']), $trip['end_date']), ['ok'], true)));
    if ($bgmiss) $alerts[] = [count($bgmiss) === 1 ? full_name($bgmiss[0]) . ' needs a background check' : count($bgmiss) . ' people need background checks', implode(', ', array_map('full_name', array_slice($bgmiss, 0, 3))), "/admin/reports.php?trip=$id&view=background"];
    $unflown = val("SELECT COUNT(*) FROM flights WHERE trip_id = ? AND (flight_no IS NULL OR flight_no = '' OR flight_no LIKE '[%')", [$id]);
    if ($unflown) $alerts[] = ['Flights not booked yet', 'Add flight numbers when the group is ticketed', "/admin/trip.php?id=$id&tab=travel"];
    return $alerts;
}

// ---------- Guide sections ----------
const GUIDE_SECTIONS = [
    'airport' => 'Airport and meeting spot', 'lodging' => 'Where we stay', 'contacts' => 'Who to call', 'packing' => 'Packing list',
    'wear' => 'What to wear', 'money' => 'Money', 'weather' => 'Weather', 'power' => 'Power and plugs', 'phone' => 'Phone and Wi-Fi',
    'health' => 'Health and insurance', 'entry' => 'Passport and entry', 'safety' => 'Safety plan',
];
function guide(int $trip_id): array {
    $out = []; foreach (all('SELECT * FROM guide WHERE trip_id = ?', [$trip_id]) as $g) $out[$g['section']] = $g; return $out;
}
function lines(?string $text): array { return array_values(array_filter(array_map('trim', explode("\n", (string)$text)), 'strlen')); }
// Guide text for travelers and parents: a leader's [notes to self] are removed, and lines left empty are dropped
function clean_lines(?string $text): array {
    $out = [];
    foreach (lines($text) as $l) { $c = trim(preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $l), " \t,;·-"); if ($c !== '' && !preg_match('/:$/', $c)) $out[] = $c; }
    return $out;
}
function clean_text(?string $text): string { return implode("\n", clean_lines($text)); }
function flight_label(?string $no): string { return is_placeholder($no) || !$no ? 'Flight to be announced' : (string)$no; }
function is_placeholder(?string $s): bool { return $s === null || trim($s) === '' || preg_match('/^\[.*\]$/s', trim($s)) === 1; }

// ---------- Trip photos ----------
function trip_photos(int $trip_id): array {
    return array_map(fn($f) => ['id' => (int)$f['id'], 'src' => $f['url'] ?: '/file.php?id=' . $f['id'], 'title' => $f['title']],
        all("SELECT * FROM files WHERE trip_id = ? AND kind = 'photo' ORDER BY COALESCE(sort, 999), id", [$trip_id]));
}
function trip_cover(int $trip_id): ?string { $p = trip_photos($trip_id); return $p[0]['src'] ?? null; }
