<?php
require __DIR__ . '/inc/bootstrap.php';

$next = safe_path(is_string($_GET['next'] ?? null) ? $_GET['next'] : (is_string($_POST['next'] ?? null) ? $_POST['next'] : '/'));
if (signed_in()) { header('Location: ' . $next); exit; }

$error = '';
// The staff preview password can be turned off once everyone signs in with Planning Center ('preview_password' => '' in config.php)
$ready = !empty($config['preview_password']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    check_csrf();
    // 8 tries per 15 minutes from one place, 40 from everywhere: then wait
    if (rate_limited(client_key() . ':signin', 8, 900) || rate_limited('signin:all', 40, 900)) {
        $error = 'Too many tries. Wait 15 minutes and try again.';
        audit('signin_locked', 'session');
    } elseif (hash_equals((string)$config['preview_password'], (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION = ['preview_ok' => true, 'view' => 'staff', 'since' => time(), 'seen' => time(), 'pv' => hash('sha256', 'pv:' . (string)$config['preview_password'])];
        audit('signin', 'session', null, null, 'preview password');
        header('Location: ' . $next);
        exit;
    } else {
        usleep(400000);
        audit('signin_failed', 'session');
        $error = "That password didn't match. Try again.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Journey Missions</title>
<link rel="icon" href="/assets/logo/mark-ember.png">
<link rel="stylesheet" href="<?= asset('/assets/app.css') ?>">
</head>
<body>
<main class="gate">
  <form class="card" method="post">
    <?= logo(260, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:44px">Welcome <em style="font-weight:700;letter-spacing:-.03em">back</em>.</h1>
    <?php if (pco_login_ready()): ?>
      <a class="btn btn-primary btn-wide" href="/auth/pco.php?next=<?= urlencode($next) ?>">Sign in with Planning Center</a>
      <?php if (g('err') !== ''): ?><p style="margin:0;color:var(--ember-small);font-weight:600"><?= e(['nomatch' => "We couldn't sign you in. If you're going on a trip, ask the missions team to connect your account.", 'failed' => "Planning Center sign-in didn't finish. Try again."][g('err')] ?? 'Something went wrong. Try again.') ?></p><?php endif; ?>
      <?php if ($ready): ?><details><summary class="muted small" style="cursor:pointer">Staff password</summary><div style="display:flex;flex-direction:column;gap:12px;padding-top:12px"><?php endif; ?>
    <?php endif; ?>
    <?php if (!$ready && !pco_login_ready()): ?>
      <p class="muted" style="margin:0">Sign-in isn't set up yet. Ask the missions team.</p>
    <?php elseif ($ready): ?>
      <p class="muted" style="margin:0">Journey staff: enter the staff password.</p>
      <label class="sr" for="pw">Staff password</label>
      <?= csrf() ?><input id="pw" type="password" name="password" placeholder="Staff password" autocomplete="current-password" required autofocus>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?php if ($error): ?><p style="margin:0;color:var(--ember-small);font-weight:600"><?= e($error) ?></p><?php endif; ?>
      <button class="btn<?= pco_login_ready() ? '' : ' btn-primary' ?> btn-wide" type="submit">Sign in</button>
      <?php if (pco_login_ready()): ?></div></details><?php endif; ?>
    <?php endif; ?>
  </form>
</main>
</body>
</html>
