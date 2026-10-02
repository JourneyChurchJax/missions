<?php
// Sign out. Only a button press (POST) signs you out, so another site can't do it to you.
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (signed_in()) audit('signout', 'session');
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    header('Location: /signin.php');
    exit;
}
if (!signed_in()) { header('Location: /signin.php'); exit; }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Sign out · Journey Missions</title><link rel="stylesheet" href="<?= asset('/assets/app.css') ?>"></head>
<body><main class="gate"><form class="card" method="post"><?= logo(240, false, '/') ?><h1 class="disp" style="margin:0;font-size:32px">Sign out?</h1><?= csrf() ?>
<div class="actions"><a class="btn" href="/">Stay signed in</a><button class="btn btn-primary" type="submit">Sign out</button></div></form></main></body></html>
