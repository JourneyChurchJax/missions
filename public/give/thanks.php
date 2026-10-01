<?php
// After Stripe checkout. The gift itself is recorded by the webhook, not here.
require dirname(__DIR__) . '/inc/bootstrap.php';
$pg = !empty($_GET['s']) ? page_by_slug((string)$_GET['s']) : null;
$t = $pg ? trip((int)$pg['trip_id']) : (!empty($_GET['trip']) ? one('SELECT * FROM trips WHERE slug = ?', [(string)$_GET['trip']]) : null);
public_open('Thank you');
?>
<main class="pub-main">
  <section class="tile xl" style="padding:40px;gap:14px;text-align:center;align-items:center">
    <span class="pill pill-ok">Gift received</span>
    <h1 class="disp">Thank you!</h1>
    <p style="margin:0;font-size:18px;line-height:1.55;max-width:520px">Your gift<?= $pg ? ' for ' . e($pg['preferred_name'] ?: $pg['first_name']) : '' ?><?= $t ? ' and the ' . e($t['name']) . ' team' : '' ?> means so much. A receipt is on its way to your email, and you'll get a year-end statement for your taxes.</p>
    <?php if ($pg): ?><a class="btn" href="/give/?s=<?= e($pg['page_slug']) ?>">Back to <?= e($pg['preferred_name'] ?: $pg['first_name']) ?>'s page</a><?php elseif ($t): ?><a class="btn" href="/give/?trip=<?= e($t['slug']) ?>">Back to the team page</a><?php endif; ?>
  </section>
  <p class="muted small" style="text-align:center">Journey Church Missions</p>
</main>
</body>
</html>
