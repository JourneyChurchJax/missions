<?php
// Planning Center: sign in with Planning Center, and pull people's details and background checks.
// Sign-in turns on with config.php 'pco' => ['client_id' => ..., 'client_secret' => ...] (an OAuth app).
// People sync turns on with 'pco' => ['app_id' => ..., 'secret' => ...] (a personal access token). Both can live in the same array.

function pco_login_ready(): bool { global $config; return !empty($config['pco']['client_id']) && !empty($config['pco']['client_secret']); }
function pco_api_ready(): bool { global $config; return !empty($config['pco']['app_id']) && !empty($config['pco']['secret']); }
function pco_base(): string { global $config; return rtrim($config['pco']['base'] ?? 'https://api.planningcenteronline.com', '/'); }

// GET or POST to Planning Center. With $token it acts as the signed-in person; otherwise as the church's access token.
function pco_request(string $method, string $path, array $params = [], ?string $token = null): array {
    global $config;
    $url = (str_starts_with($path, 'http') ? $path : pco_base() . $path) . ($method === 'GET' && $params ? '?' . http_build_query($params) : '');
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    else curl_setopt($ch, CURLOPT_USERPWD, ($config['pco']['app_id'] ?? '') . ':' . ($config['pco']['secret'] ?? ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    if ($method !== 'GET') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); }
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode((string)$res, true) ?: [];
    if ($code < 200 || $code >= 300) throw new RuntimeException($data['errors'][0]['detail'] ?? $data['error_description'] ?? "Planning Center said no ($code)");
    return $data;
}

// Turn a Planning Center person (with included emails, phones, addresses) into our people columns
function pco_person_fields(array $person, array $included = []): array {
    $a = $person['attributes'] ?? [];
    $keys = ['Email' => 'emails', 'PhoneNumber' => 'phone_numbers', 'Address' => 'addresses'];
    $rel = fn(string $type) => array_values(array_filter($included, fn($x) => ($x['type'] ?? '') === $type && in_array($x['id'], array_column($person['relationships'][$keys[$type]]['data'] ?? [], 'id'), true)));
    $pick = function (array $list, string $field) { foreach ($list as $x) if (!empty($x['attributes']['primary'])) return $x['attributes'][$field] ?? null; return $list[0]['attributes'][$field] ?? null; };
    $emails = $rel('Email'); $phones = $rel('PhoneNumber'); $addrs = $rel('Address');
    $addr = null; foreach ($addrs as $x) if (!empty($x['attributes']['primary'])) $addr = $x; $addr = $addr ?? ($addrs[0] ?? null);
    return array_filter([
        'first_name' => $a['first_name'] ?? null, 'last_name' => $a['last_name'] ?? null,
        'preferred_name' => ($a['nickname'] ?? null) ?: null, 'birth_date' => $a['birthdate'] ?? null,
        'gender' => !empty($a['gender']) ? (strtolower(substr($a['gender'], 0, 1)) === 'f' ? 'female' : 'male') : null,
        'email' => $pick($emails, 'address'), 'phone' => $pick($phones, 'number'),
        'address' => $addr['attributes']['street'] ?? ($addr['attributes']['street_line_1'] ?? null), 'city' => $addr['attributes']['city'] ?? null,
        'state' => $addr['attributes']['state'] ?? null, 'zip' => $addr['attributes']['zip'] ?? null,
    ], fn($v) => $v !== null && $v !== '');
}

// Search Planning Center people by name or email
function pco_search(string $q): array {
    $r = pco_request('GET', '/people/v2/people', ['where[search_name_or_email]' => $q, 'include' => 'emails,phone_numbers', 'per_page' => 15]);
    return array_map(fn($p) => ['id' => $p['id'], 'fields' => pco_person_fields($p, $r['included'] ?? [])], $r['data'] ?? []);
}

// Pull the latest details (and background check) for one of our people who is linked to Planning Center.
// Only fills empty fields unless $overwrite, so edits made here aren't lost.
function pco_pull(int $person_id, bool $overwrite = false): array {
    $p = person($person_id);
    if (!$p || !$p['pco_id']) throw new RuntimeException('Link this person to Planning Center first.');
    $r = pco_request('GET', '/people/v2/people/' . rawurlencode($p['pco_id']), ['include' => 'emails,phone_numbers,addresses']);
    $fields = pco_person_fields($r['data'] ?? [], $r['included'] ?? []);
    $set = [];
    foreach ($fields as $k => $v) if ($overwrite || empty($p[$k])) $set[$k] = $v;
    $set['pco_synced_at'] = now();
    update('people', $person_id, $set);
    $bg = pco_pull_background($person_id, $p['pco_id']);
    return [count($set) - 1, $bg];
}
function pco_pull_background(int $person_id, string $pco_id): bool {
    try { $r = pco_request('GET', '/people/v2/people/' . rawurlencode($pco_id) . '/background_checks', ['order' => '-completed_at', 'per_page' => 1]); }
    catch (Throwable $e) { return false; }
    $b = $r['data'][0] ?? null;
    if (!$b) return false;
    $a = $b['attributes'] ?? [];
    $status = strtolower((string)($a['status'] ?? $a['result'] ?? ''));
    // Exact values only, so "incomplete" or "not_clear" can never be read as clear
    $mapped = in_array($status, ['report_clear', 'clear', 'passed', 'pass', 'approved'], true) ? 'clear'
        : (in_array($status, ['manual_review', 'needs_review', 'review', 'report_flagged', 'flagged', 'failed', 'fail', 'not_clear', 'declined'], true) ? 'review' : 'requested');
    if (isset($a['current']) && $a['current'] === false && $mapped === 'clear') $mapped = 'expired';
    $row = ['person_id' => $person_id, 'provider' => 'Planning Center', 'status' => $mapped, 'requested_at' => substr((string)($a['created_at'] ?? date('Y-m-d')), 0, 10),
            'completed_at' => isset($a['completed_at']) ? substr((string)$a['completed_at'], 0, 10) : null, 'expires_on' => isset($a['expires_on']) ? substr((string)$a['expires_on'], 0, 10) : null,
            'note' => nn($a['note'] ?? null), 'pco_id' => (string)$b['id']];
    if ($have = one('SELECT id FROM background_checks WHERE pco_id = ?', [(string)$b['id']])) update('background_checks', (int)$have['id'], $row);
    else insert('background_checks', $row + ['created_by' => 'Planning Center', 'created_at' => now()]);
    return true;
}

// ---------- Signed-in users (Planning Center sign-in) ----------
// $_SESSION['auth'] = ['person_id' => int, 'staff' => bool, 'name' => string]
// Staff are only ever decided here, from settings a traveler can't touch: the "Staff access" switch on a person's page
// (staff only), or Planning Center IDs listed in config.php ('staff_pco_ids' => ['12345']). Never from an email address.
function auth_user(): ?array { return $_SESSION['auth'] ?? null; }
function is_staff_person(?array $p): bool {
    global $config;
    if (!$p) return false;
    return !empty($p['is_staff']) || ($p['pco_id'] && in_array((string)$p['pco_id'], array_map('strval', (array)($config['staff_pco_ids'] ?? [])), true));
}
// Sign someone in after Planning Center (or an email code) proved who they are
function sign_in_person(array $p): void {
    session_regenerate_id(true);
    $staff = is_staff_person($p);
    $_SESSION = ['auth' => ['person_id' => (int)$p['id'], 'staff' => $staff, 'name' => full_name($p)], 'view' => $staff ? 'staff' : 'traveler', 'since' => time(), 'seen' => time()];
    if (!$staff) $_SESSION['person_id'] = (int)$p['id'];
    audit('signin', 'people', (int)$p['id'], null, 'Planning Center');
}
