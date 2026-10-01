<?php
// Sign in with Planning Center (OAuth). Step 1 sends the person to Planning Center; step 2 (?code=) brings them back.
require dirname(__DIR__) . '/inc/bootstrap.php';
global $config;
if (!pco_login_ready()) { header('Location: /signin.php'); exit; }
$redirect = site_url('/auth/pco.php');
if (!empty($config['pco']['redirect_uri'])) $redirect = $config['pco']['redirect_uri'];

if (empty($_GET['code'])) {
    $next = (string)($_GET['next'] ?? '/');
    $_SESSION['pco_next'] = str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/';
    $_SESSION['pco_state'] = new_token();
    header('Location: ' . pco_base() . '/oauth/authorize?' . http_build_query(['client_id' => $config['pco']['client_id'], 'redirect_uri' => $redirect,
        'response_type' => 'code', 'scope' => 'people', 'state' => $_SESSION['pco_state']]));
    exit;
}

$fail = function (string $why) { header('Location: /signin.php?err=' . $why); exit; };
if (empty($_SESSION['pco_state']) || !hash_equals($_SESSION['pco_state'], (string)($_GET['state'] ?? ''))) $fail('failed');
unset($_SESSION['pco_state']);
try {
    $tok = pco_request('POST', '/oauth/token', ['grant_type' => 'authorization_code', 'code' => (string)$_GET['code'], 'client_id' => $config['pco']['client_id'],
        'client_secret' => $config['pco']['client_secret'], 'redirect_uri' => $redirect]);
    $me = pco_request('GET', '/people/v2/me', ['include' => 'emails,phone_numbers,addresses'], (string)$tok['access_token']);
} catch (Throwable $e) { $fail('failed'); }

$pco_id = (string)($me['data']['id'] ?? '');
$fields = pco_person_fields($me['data'] ?? [], $me['included'] ?? []);
$admin = !empty($me['data']['attributes']['site_administrator']);
$emails = array_map(fn($x) => strtolower((string)$x['attributes']['address']), array_filter($me['included'] ?? [], fn($x) => ($x['type'] ?? '') === 'Email'));
// Match: already linked, then by any of their emails
$p = $pco_id ? one('SELECT * FROM people WHERE pco_id = ?', [$pco_id]) : null;
if (!$p) foreach ($emails as $em) if ($p = one('SELECT * FROM people WHERE LOWER(email) = ? ORDER BY id LIMIT 1', [$em])) break;
// Staff who aren't in our people list yet get a record so their actions have a name
if (!$p && ($admin || array_intersect($emails, array_map('strtolower', (array)($config['staff_emails'] ?? []))))) {
    $pid = insert('people', ['first_name' => $fields['first_name'] ?? 'Staff', 'last_name' => $fields['last_name'] ?? '', 'email' => $fields['email'] ?? ($emails[0] ?? null), 'tags' => 'Staff', 'pco_id' => $pco_id, 'created_at' => now()]);
    $p = person($pid);
}
if (!$p) $fail('nomatch');
if (!$p['pco_id'] && $pco_id) update('people', (int)$p['id'], ['pco_id' => $pco_id]);
$staff = is_staff_person($p, $admin);
if (!$staff && !val('SELECT COUNT(*) FROM members WHERE person_id = ?', [(int)$p['id']])) $fail('nomatch');

session_regenerate_id(true);
unset($_SESSION['preview_ok']);
$_SESSION['auth'] = ['person_id' => (int)$p['id'], 'staff' => $staff, 'name' => full_name($p)];
$_SESSION['view'] = $staff ? 'staff' : 'traveler';
if (!$staff) $_SESSION['person_id'] = (int)$p['id'];
$next = $_SESSION['pco_next'] ?? '/'; unset($_SESSION['pco_next']);
header('Location: ' . ($staff ? ($next === '/' ? '/admin/' : $next) : '/trip/'));
