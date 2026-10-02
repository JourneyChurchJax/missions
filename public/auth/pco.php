<?php
// Sign in with Planning Center (OAuth). Step 1 sends the person to Planning Center; step 2 (?code=) brings them back.
// People are matched only by their Planning Center ID. The first time, if we find them by email, we email a code to the
// address WE have on file (not one Planning Center reports), so no one can claim another person's account.
define('REAL_DB', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
global $config;
if (!pco_login_ready()) { header('Location: /signin.php'); exit; }
$redirect = (string)($config['pco']['redirect_uri'] ?? site_url('/auth/pco.php'));

if (g('code') === '') {
    $_SESSION['pco_next'] = safe_path(g('next') ?: '/');
    $_SESSION['pco_state'] = new_token();
    header('Location: ' . pco_base() . '/oauth/authorize?' . http_build_query(['client_id' => $config['pco']['client_id'], 'redirect_uri' => $redirect,
        'response_type' => 'code', 'scope' => 'people', 'state' => $_SESSION['pco_state']]));
    exit;
}

$fail = function (string $why) { header('Location: /signin.php?err=' . $why); exit; };
if (empty($_SESSION['pco_state']) || !hash_equals((string)$_SESSION['pco_state'], g('state'))) $fail('failed');
unset($_SESSION['pco_state']);
try {
    $tok = pco_request('POST', '/oauth/token', ['grant_type' => 'authorization_code', 'code' => g('code'), 'client_id' => $config['pco']['client_id'],
        'client_secret' => $config['pco']['client_secret'], 'redirect_uri' => $redirect]);
    $me = pco_request('GET', '/people/v2/me', ['include' => 'emails'], (string)$tok['access_token']);
} catch (Throwable $e) { app_log('PCO sign-in: ' . $e->getMessage(), 'errors'); $fail('failed'); }

$pco_id = (string)($me['data']['id'] ?? '');
if ($pco_id === '') $fail('failed');
$fields = pco_person_fields($me['data'] ?? [], $me['included'] ?? []);
$next = $_SESSION['pco_next'] ?? '/'; unset($_SESSION['pco_next']);

// Already linked: sign in
if ($p = one('SELECT * FROM people WHERE pco_id = ?', [$pco_id])) {
    $p = person((int)$p['id']);
    if (!is_staff_person($p) && !val('SELECT COUNT(*) FROM members WHERE person_id = ?', [(int)$p['id']])) $fail('nomatch');
    sign_in_person($p);
    header('Location: ' . (is_staff_person($p) ? ($next === '/' ? '/admin/' : $next) : '/trip/')); exit;
}

// Not linked yet: find exactly one unlinked person with that email, then prove it with a code sent to the email we have
$emails = array_values(array_unique(array_map(fn($x) => strtolower((string)$x['attributes']['address']), array_filter($me['included'] ?? [], fn($x) => ($x['type'] ?? '') === 'Email'))));
$cands = [];
foreach ($emails as $em) foreach (all('SELECT * FROM people WHERE LOWER(email) = ? AND (pco_id IS NULL OR pco_id = \'\')', [$em]) as $c) $cands[$c['id']] = $c;
$cands = array_values(array_filter($cands, fn($c) => val('SELECT COUNT(*) FROM members WHERE person_id = ?', [$c['id']]) || !empty($c['is_staff'])));
if (count($cands) !== 1 || !mail_ready()) { audit('signin_nomatch', 'people', null, null, ['pco_id' => $pco_id, 'candidates' => count($cands)]); $fail('nomatch'); }
$c = $cands[0];
if (rate_limited('pco_code:' . $c['id'], 5, 3600)) $fail('failed');
$code = (string)random_int(100000, 999999);
q('DELETE FROM login_codes WHERE person_id = ?', [$c['id']]);
insert('login_codes', ['person_id' => (int)$c['id'], 'pco_id' => $pco_id, 'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires' => time() + 900, 'tries' => 0, 'created_at' => now()]);
send_email((string)$c['email'], 'Your Journey Missions sign-in code: ' . $code, "Your code is $code. It works for 15 minutes.\n\nIf you didn't try to sign in, you can ignore this email.", null, (int)$c['id']);
$_SESSION['pco_pending'] = ['person_id' => (int)$c['id'], 'next' => $next, 'email_hint' => preg_replace('/(?<=.).(?=[^@]*@)/', '•', (string)$c['email'])];
header('Location: /auth/verify.php'); exit;
