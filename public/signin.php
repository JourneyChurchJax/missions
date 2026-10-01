<?php
require __DIR__ . '/inc/bootstrap.php';

$next = $_GET['next'] ?? $_POST['next'] ?? '/';
if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/';

$error = '';
$ready = !empty($config['preview_password']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    usleep(400000); // slow down guessing
    if (hash_equals((string)$config['preview_password'], (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['preview_ok'] = true;
        header('Location: ' . $next);
        exit;
    }
    $error = "That password didn't match. Try again.";
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
<link rel="stylesheet" href="/assets/app.css?v=8">
</head>
<body>
<main class="gate">
  <form class="card" method="post">
    <?= logo(260, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:44px">Welcome <em style="font-weight:700;letter-spacing:-.03em">back</em>.</h1>
    <?php if (pco_login_ready()): ?>
      <a class="btn btn-primary btn-wide" href="/auth/pco.php?next=<?= urlencode($next) ?>">Sign in with Planning Center</a>
      <?php if (!empty($_GET['err'])): ?><p style="margin:0;color:var(--ember-small);font-weight:600"><?= e(['nomatch' => "We couldn't find you on a mission trip yet. If you're going on one, ask the missions team to add you.", 'failed' => "Planning Center sign-in didn't finish. Try again."][$_GET['err']] ?? 'Something went wrong. Try again.') ?></p><?php endif; ?>
      <?php if ($ready): ?><details><summary class="muted small" style="cursor:pointer">Staff preview password</summary><div style="display:flex;flex-direction:column;gap:12px;padding-top:12px"><?php endif; ?>
    <?php endif; ?>
    <?php if (!$ready && !pco_login_ready()): ?>
      <p class="muted" style="margin:0">The preview password hasn't been set on the server yet. Add <strong>config.php</strong> next to the public_html folder, then refresh.</p>
    <?php elseif ($ready): ?>
      <p class="muted" style="margin:0">This is an early preview for Journey staff. Enter the preview password.</p>
      <label class="sr" for="pw">Preview password</label>
      <input id="pw" type="password" name="password" placeholder="Preview password" autocomplete="current-password" required autofocus>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?php if ($error): ?><p style="margin:0;color:var(--ember-small);font-weight:600"><?= e($error) ?></p><?php endif; ?>
      <button class="btn<?= pco_login_ready() ? '' : ' btn-primary' ?> btn-wide" type="submit">Sign in</button>
      <?php if (pco_login_ready()): ?></div></details><?php else: ?><p class="muted small" style="margin:0">Planning Center sign-in turns on once it's connected in Settings.</p><?php endif; ?>
    <?php endif; ?>
  </form>
</main>
</body>
</html>
