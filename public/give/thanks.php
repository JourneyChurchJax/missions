<?php
// After Stripe checkout. The gift itself is recorded by the webhook once Stripe confirms it, not here.
define('REAL_DB', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$pg = g('s') !== '' ? page_by_slug(g('s')) : null;
$t = $pg ? trip((int)$pg['trip_id']) : (g('trip') !== '' ? one('SELECT * FROM trips WHERE slug = ?', [g('trip')]) : null);
public_open('Thank you');
?>
<main class="pub-main" id="main">
  <section class="tile xl center-tile">
    <h1 class="disp">Thank you!</h1>
    <p class="lead-text" style="max-width:520px">Your gift<?= $pg ? ' toward ' . e(public_name($pg, $t['start_date'] ?? null)) . "'s trip" : ($t ? ' for the ' . e($t['name']) . ' team' : '') ?> means so much. Stripe is confirming the payment now. Your receipt will arrive by email in a few minutes, and you'll get a year-end statement for your taxes.</p>
    <?php if ($pg): ?><a class="btn" href="/give/?s=<?= e($pg['page_slug']) ?>">Back to <?= e($pg['preferred_name'] ?: $pg['first_name']) ?>'s page</a><?php elseif ($t): ?><a class="btn" href="/give/?trip=<?= e($t['slug']) ?>">Back to the team page</a><?php endif; ?>
  </section>
</main>
<?php public_close(); ?>
