<?php
// One gift: edit it, or void it (gifts are never deleted, so the books always add up)
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$g = gi('id') ? one('SELECT * FROM gifts WHERE id = ?', [gi('id')]) : null;
if (!$g) { header('Location: /admin/giving.php'); exit; }
$locked = gift_locked($g);
$d = $g['donor_id'] ? donor((int)$g['donor_id']) : null;
page_open('Gift');
admin_header('giving');
?>
<main class="main narrow" id="main">
  <div class="bar-head"><div><a class="muted small back-link" href="/admin/giving.php">‹ Giving</a><h1 class="page-title"><?= money((float)$g['amount'], 2) ?> from <?= e(donor_name($d)) ?></h1>
    <div class="muted small"><?= fdate($g['gift_date'], 'F j, Y') ?> · <?= e(gift_for($g)) ?> · <?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['source'] === 'stripe' ? ' · online' : '' ?> · <?= e(ucfirst($g['status'])) ?></div></div></div>
  <?php if ($g['status'] === 'void'): ?><div class="note"><strong>Voided</strong><div class="muted small"><?= e($g['void_reason']) ?> · by <?= e($g['voided_by']) ?> on <?= fdate($g['voided_at'], 'M j, Y') ?></div></div><?php endif; ?>
  <section class="group">
    <?php if ((float)$g['refunded'] > 0): ?><div class="cell"><span class="grow">Refunded</span><strong><?= money((float)$g['refunded'], 2) ?></strong></div><?php endif; ?>
    <?php if ((float)$g['covered_fee'] > 0): ?><div class="cell"><span class="grow">Card fee the donor covered (not credited to the traveler)</span><strong><?= money((float)$g['covered_fee'], 2) ?></strong></div><?php endif; ?>
    <div class="cell"><span class="grow">Counts toward the trip</span><strong><?= money(gift_credit($g), 2) ?></strong></div>
    <?php if ($g['message']): ?><div class="cell"><span class="grow">Note from the donor</span><span><?= e($g['message']) ?></span></div><?php endif; ?>
    <?php if ($g['stripe_id']): ?><div class="cell"><span class="grow">Stripe reference</span><code class="small"><?= e($g['stripe_id']) ?></code></div><?php endif; ?>
  </section>
  <?php if ($locked): ?>
    <div class="note"><strong>This gift can't be changed</strong><div class="muted small"><?= e($locked) ?></div></div>
  <?php else: ?>
    <section class="tile xl"><h2 class="card-title">Edit</h2><?php include dirname(__DIR__) . '/inc/_gift_form.php'; ?></section>
    <details class="add"><summary>Void this gift</summary><div class="body">
      <form class="form" method="post" action="/action.php" data-confirm="Void this gift? It stays in the history but stops counting."><?= csrf() ?><input type="hidden" name="action" value="gift_void"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
        <label class="lab">Why?<input type="text" name="reason" required placeholder="Entered twice, check bounced…" maxlength="200"></label><button class="btn btn-danger" type="submit">Void gift</button></form></div></details>
  <?php endif; ?>
</main>
<?php page_close(); ?>
