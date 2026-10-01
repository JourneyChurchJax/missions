<?php
// App helpers: who is acting, form safety, flash messages, and the trip/people queries pages share.

// ---------- Who is acting (preview: staff or a chosen traveler) ----------
function acting_person_id(): ?int {
    if (!empty($_SESSION['person_id'])) return (int)$_SESSION['person_id'];
    $id = val("SELECT p.id FROM people p JOIN members m ON m.person_id = p.id WHERE m.role = 'traveler' ORDER BY p.id LIMIT 1");
    return $id ? (int)$id : null;
}
function current_actor_name(): string {
    if (($_SESSION['view'] ?? 'staff') === 'staff') return 'Adam Hardegree';
    $p = person(acting_person_id() ?? 0);
    return $p ? full_name($p) : 'Someone';
}

// ---------- Forms ----------
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Form expired. Go back and try again.'); }
}
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}
function back(string $fallback = '/'): never {
    $to = $_POST['back'] ?? $_SERVER['HTTP_REFERER'] ?? $fallback;
    if (!is_string($to) || (!str_starts_with($to, '/') && !str_starts_with($to, 'http'))) $to = $fallback;
    header('Location: ' . $to); exit;
}
function post(string $k, $default = null) { $v = $_POST[$k] ?? $default; return is_string($v) ? trim($v) : $v; }
function nn($v) { return ($v === '' || $v === null) ? null : $v; }

// ---------- Dates ----------
function fdate(?string $d, string $fmt = 'M j, Y'): string { return $d ? date($fmt, strtotime($d)) : ''; }
function date_range(string $a, string $b): string {
    $x = strtotime($a); $y = strtotime($b);
    if (date('Y-m', $x) === date('Y-m', $y)) return date('F j', $x) . '–' . date('j, Y', $y);
    return date('M j', $x) . ' – ' . date('M j, Y', $y);
}
function days_until(string $d): int { return max(0, (int)floor((strtotime($d) - strtotime('today')) / 86400)); }

// ---------- People ----------
function person(int $id): ?array { return one('SELECT * FROM people WHERE id = ?', [$id]); }
function full_name(array $p): string { return trim(($p['preferred_name'] ?: $p['first_name']) . ' ' . $p['last_name']); }
function is_minor(array $p, string $on): bool { return !empty($p['birth_date']) && strtotime($p['birth_date'] . ' +18 years') > strtotime($on); }

// ---------- Trips ----------
function trip(int $id): ?array { return one('SELECT * FROM trips WHERE id = ?', [$id]); }
function trip_by_slug(string $slug): ?array { return one('SELECT * FROM trips WHERE slug = ?', [$slug]); }
function trips(string $which = 'upcoming'): array {
    $sql = 'SELECT * FROM trips';
    if ($which === 'upcoming') $sql .= " WHERE status = 'active' AND end_date >= date('now', '-30 day')";
    if ($which === 'past') $sql .= " WHERE end_date < date('now', '-30 day')";
    if ($which === 'cancelled') $sql .= " WHERE status IN ('cancelled','postponed')";
    if (driver() === 'mysql') $sql = str_replace(["date('now', '-30 day')"], ['DATE_SUB(CURDATE(), INTERVAL 30 DAY)'], $sql);
    return all($sql . ' ORDER BY start_date');
}
function members(int $trip_id): array {
    return all("SELECT m.*, p.first_name, p.preferred_name, p.last_name, p.email, p.phone, p.birth_date, p.passport_number, p.passport_expires,
                p.ec1_name, p.ec1_phone, p.health, p.allergies, p.meds, p.diet, p.shirt
                FROM members m JOIN people p ON p.id = m.person_id WHERE m.trip_id = ? ORDER BY CASE m.role WHEN 'admin' THEN 0 WHEN 'leader' THEN 1 ELSE 2 END, p.last_name", [$trip_id]);
}
function member_of(int $trip_id, int $person_id): ?array { return one('SELECT * FROM members WHERE trip_id = ? AND person_id = ?', [$trip_id, $person_id]); }
function trip_for_person(int $person_id): ?array {
    return one("SELECT t.* FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ? AND t.status = 'active' ORDER BY t.start_date LIMIT 1", [$person_id]);
}
function travelers(int $trip_id): array { return array_values(array_filter(members($trip_id), fn($m) => (int)$m['traveling'] === 1)); }
function trip_budget(int $trip_id): float {
    $n = max(1, count(travelers($trip_id)));
    $rows = all('SELECT * FROM budget WHERE trip_id = ?', [$trip_id]);
    return array_sum(array_map(fn($b) => (float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']), $rows));
}
function member_goal(array $trip, array $m): float { return (float)($m['goal'] ?: $trip['cost_per_person']); }
function trip_raised(int $trip_id): float { return (float)val('SELECT COALESCE(SUM(raised),0) FROM members WHERE trip_id = ?', [$trip_id]); }
function trip_goal(array $trip): float {
    $sum = 0; foreach (travelers((int)$trip['id']) as $m) $sum += member_goal($trip, $m); return $sum;
}

// ---------- Tasks and readiness ----------
const TASK_TYPES = ['traveler' => 'Traveler task', 'upload' => 'Upload a document', 'verify' => 'Confirm their info', 'leader' => 'Leader task', 'admin' => 'Admin task', 'staff' => 'Staff task'];
function traveler_tasks(int $trip_id): array {
    return all("SELECT * FROM tasks WHERE trip_id = ? AND type IN ('traveler','upload','verify') ORDER BY due_date", [$trip_id]);
}
function tasks_for(int $trip_id, int $person_id): array {
    $p = person($person_id); $trip = trip($trip_id);
    $rows = all("SELECT t.*, d.done_at, d.file_id FROM tasks t LEFT JOIN task_done d ON d.task_id = t.id AND d.person_id = ?
                 WHERE t.trip_id = ? AND t.type IN ('traveler','upload','verify') ORDER BY t.due_date", [$person_id, $trip_id]);
    return array_values(array_filter($rows, fn($t) => !$t['minors_only'] || ($p && $trip && is_minor($p, $trip['start_date']))));
}
function readiness(int $trip_id, int $person_id): array {
    $t = tasks_for($trip_id, $person_id);
    $done = count(array_filter($t, fn($x) => $x['done_at']));
    return [$done, count($t)];
}
function trip_ready_count(int $trip_id): int {
    $n = 0; foreach (travelers($trip_id) as $m) { [$d, $t] = readiness($trip_id, (int)$m['person_id']); if ($t > 0 && $d >= $t) $n++; } return $n;
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
    foreach (travelers($id) as $m) if (!passport_ok($m, $trip)) $alerts[] = [full_name($m) . ' has no valid passport on file', 'Passport must be valid through ' . fdate($trip['passport_valid_through'] ?: $trip['end_date']), "/admin/trip.php?id=$id&tab=team"];
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
function is_placeholder(?string $s): bool { return $s === null || trim($s) === '' || preg_match('/^\[.*\]$/s', trim($s)) === 1; }
