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
<link rel="stylesheet" href="/assets/app.css?v=3">
</head>
<body>
<main class="gate">
  <form class="card" method="post">
    <?= logo(260, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:44px">Welcome <em style="font-weight:700;letter-spacing:-.03em">back</em>.</h1>
    <?php if (!$ready): ?>
      <p class="muted" style="margin:0">The preview password hasn't been set on the server yet. Add <strong>config.php</strong> next to the public_html folder, then refresh.</p>
    <?php else: ?>
      <p class="muted" style="margin:0">This is an early preview for Journey staff. Enter the preview password.</p>
      <label class="sr" for="pw">Preview password</label>
      <input id="pw" type="password" name="password" placeholder="Preview password" autocomplete="current-password" required autofocus>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?php if ($error): ?><p style="margin:0;color:var(--ember-small);font-weight:600"><?= e($error) ?></p><?php endif; ?>
      <button class="btn btn-primary btn-wide" type="submit">Sign in</button>
      <p class="muted small" style="margin:0">Soon you'll sign in with your Planning Center account instead.</p>
    <?php endif; ?>
  </form>
</main>
</body>
</html>
