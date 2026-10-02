<?php
// Roles and permissions.
// Staff: everything. Trip admin (role 'admin' on a trip): everything on that trip.
// Trip leader (role 'leader'): what Settings → Leader permissions allows, on their own trips only.
// Travelers: only their own information.

const PERM_AREAS = [
    'team' => ['Team and profiles', 'Names, contact info, rooms and seats', 2],
    'tasks' => ['Tasks and goals', 'The checklist, signatures and reminders', 2],
    'meetings' => ['Meetings and attendance', '', 2],
    'documents' => ['Documents and links', '', 2],
    'travel' => ['Flights, itinerary and guide', 'Includes passport details for the airline roster', 1],
    'medical' => ['Medical information', 'Allergies, medications, health concerns', 1],
    'budget' => ['Budget and expenses', '', 1],
    'giving' => ['Gifts and fundraising pages', 'Who gave and how much', 1],
    'messages' => ['Messages and announcements', 'Email and text the team', 2],
    'ontrip' => ['Headcount and incident log', '', 2],
];
const PERM_LEVELS = ['Hidden', 'View', 'Edit'];

function leader_perms(): array {
    $saved = site_settings()['leader_perms'] ?? [];
    $out = [];
    foreach (PERM_AREAS as $k => [, , $default]) $out[$k] = isset($saved[$k]) ? max(0, min(2, (int)$saved[$k])) : $default;
    return $out;
}

// The signed-in person's role on a trip: 'staff', 'admin', 'leader', 'traveler' or null
function trip_role(int $trip_id): ?string {
    if (is_staff_session() && !impersonating()) return 'staff';
    $pid = (int)($_SESSION['auth']['person_id'] ?? 0);
    if (!$pid || !empty($_SESSION['auth']['staff'])) return null;
    $m = member_of($trip_id, $pid);
    return $m ? (in_array($m['role'], ['admin', 'leader'], true) ? $m['role'] : 'traveler') : null;
}
// Can the signed-in person see (1) or change (2) this area of a trip?
function can(string $area, int $trip_id, int $level = 1): bool {
    $r = trip_role($trip_id);
    if ($r === 'staff' || $r === 'admin') return true;
    if ($r !== 'leader') return false;
    return (leader_perms()[$area] ?? 0) >= $level;
}
// Trips the signed-in person leads (for the "Lead your trip" link)
function led_trips(): array {
    $pid = (int)($_SESSION['auth']['person_id'] ?? 0);
    if (!$pid || is_staff_session()) return [];
    return all("SELECT t.*, m.role FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ? AND m.role IN ('leader','admin') AND t.status = 'active' ORDER BY t.start_date", [$pid]);
}
function is_leader_session(): bool { return (bool)led_trips(); }

// For pages inside one trip's workspace: staff, or a leader/admin of that trip with access to the area
function require_trip(int $trip_id, string $area = 'team', int $level = 1): void {
    require_preview();
    if (is_staff_session()) { $_SESSION['view'] = 'staff'; return; }
    if (!can($area, $trip_id, $level)) { http_response_code(403); page_open('No access'); echo '<main class="gate"><div class="card"><h1 class="disp" style="margin:0;font-size:30px">You don\'t have access to this.</h1><p class="muted" style="margin:0">Ask the missions team if you need it.</p><a class="btn" href="/trip/">Go to my trip</a></div></main>'; page_close(); exit; }
}

// ---------- Audit log ----------
// Who did what, from where. Every change and every look at sensitive data lands here.
function audit(string $action, string $entity = '', ?int $entity_id = null, ?int $trip_id = null, $detail = null): void {
    try {
        insert('audit', ['at' => now(), 'actor' => current_actor_name(), 'actor_person_id' => isset($_SESSION['auth']['person_id']) ? (int)$_SESSION['auth']['person_id'] : null,
            'via' => !empty($_SESSION['preview_ok']) ? 'preview password' . (impersonating() ? ' (previewing traveler #' . (int)($_SESSION['person_id'] ?? 0) . ')' : '') : (!empty($_SESSION['auth']) ? 'Planning Center' : (defined('ACTOR') ? ACTOR : 'public')),
            'action' => mb_substr($action, 0, 100), 'entity' => $entity, 'entity_id' => $entity_id, 'trip_id' => $trip_id,
            'detail' => $detail === null ? null : mb_substr(is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 4000), 'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64)]);
    } catch (Throwable $e) { app_log('Audit write failed: ' . $e->getMessage(), 'errors'); }
}

// ---------- Rate limits ----------
// True when this key (an IP, an email…) has done this more than $max times in $seconds
function rate_limited(string $key, int $max, int $seconds): bool {
    $since = time() - $seconds;
    try {
        if (random_int(1, 50) === 1) q('DELETE FROM rate_hits WHERE at < ?', [time() - 86400]);
        $n = (int)val('SELECT COUNT(*) FROM rate_hits WHERE k = ? AND at > ?', [$key, $since]);
        if ($n >= $max) return true;
        insert('rate_hits', ['k' => $key, 'at' => time()]);
    } catch (Throwable $e) { return false; }
    return false;
}
function client_key(): string { return 'ip:' . substr((string)($_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 64); }

// Optional Cloudflare Turnstile check on public forms (config 'turnstile' => ['site' => ..., 'secret' => ...])
function turnstile_on(): bool { global $config; return !empty($config['turnstile']['site']) && !empty($config['turnstile']['secret']); }
function turnstile_widget(): string { global $config; return turnstile_on() ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><div class="cf-turnstile" data-sitekey="' . e($config['turnstile']['site']) . '"></div>' : ''; }
function turnstile_ok(): bool {
    global $config;
    if (!turnstile_on()) return true;
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $config['turnstile']['secret'], 'response' => (string)($_POST['cf-turnstile-response'] ?? ''), 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''])]);
    $r = json_decode((string)curl_exec($ch), true);
    return !empty($r['success']);
}
