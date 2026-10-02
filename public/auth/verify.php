<?php
// The first time someone signs in with Planning Center, they confirm a code we emailed to the address we have on file.
define('REAL_DB', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$pend = $_SESSION['pco_pending'] ?? null;
if (!$pend) { header('Location: /signin.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $row = one('SELECT * FROM login_codes WHERE person_id = ? ORDER BY id DESC LIMIT 1', [$pend['person_id']]);
    if (!$row || (int)$row['expires'] < time() || (int)$row['tries'] >= 5) $err = 'That code has expired. Sign in with Planning Center again to get a new one.';
    else {
        update('login_codes', (int)$row['id'], ['tries' => (int)$row['tries'] + 1]);
        if (password_verify(preg_replace('/\D/', '', ps('code', 10)), (string)$row['code_hash'])) {
            $p = person((int)$pend['person_id']);
            if (one('SELECT id FROM people WHERE pco_id = ? AND id <> ?', [$row['pco_id'], $p['id']])) $err = 'That Planning Center account is already connected to someone else. Ask the missions team.';
            else {
                update('people', (int)$p['id'], ['pco_id' => $row['pco_id']]);
                q('DELETE FROM login_codes WHERE person_id = ?', [$p['id']]);
                $next = $pend['next']; unset($_SESSION['pco_pending']);
                $p = person((int)$p['id']);
                sign_in_person($p);
                header('Location: ' . (is_staff_person($p) ? ($next === '/' ? '/admin/' : $next) : '/trip/')); exit;
            }
        } else $err = "That code didn't match. Check the email and try again.";
    }
}
?>
<!doctype html>
<html lang="en"><head><?php head_tags('Confirm it\'s you'); ?></head>
<body><main class="gate" id="main"><form class="card" method="post"><?= logo(240, false, '/') ?>
<h1 class="disp" style="margin:0;font-size:32px">Check your email</h1>
<p class="muted" style="margin:0">We sent a 6-digit code to <?= e($pend['email_hint']) ?>. This only happens the first time.</p>
<?php if ($err): ?><p class="error-text" role="alert"><?= e($err) ?></p><?php endif; ?>
<?= csrf() ?><label class="lab">Code<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus></label>
<button class="btn btn-primary btn-wide" type="submit">Continue</button></form></main></body></html>
