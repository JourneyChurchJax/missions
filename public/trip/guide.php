<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
// Staff and leaders can preview a trip's guide; travelers see their own
if (gi('trip') && can('travel', gi('trip'))) {
    $t = trip(gi('trip'));
    if (!$t) { header('Location: /admin/'); exit; }
    $tid = (int)$t['id']; $me_id = null;
} else {
    require dirname(__DIR__) . '/inc/member.php';
}
$g = guide($tid);
$qual = lines($t['qualifications']);
$order = ['airport', 'lodging', 'contacts', 'packing', 'wear', 'money', 'weather', 'power', 'phone', 'health', 'entry', 'safety'];
page_open('Trip guide');
!empty($me_id) ? member_header('guide') : admin_header('trips');
?>
<main class="main m" id="main">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Trip guide</h1><div class="muted">Everything you need to know before you go. Your leader keeps it up to date.</div></div>
    <a class="btn" href="/packet.php<?= empty($me_id) ? '?trip=' . $tid : '' ?>" target="_blank" rel="noopener">Trip packet (print or PDF)</a>
  </div>

  <nav class="chips" aria-label="Jump to">
    <?php foreach ($order as $k): ?><a class="chip" href="#<?= $k ?>" style="min-height:38px;font-size:14px"><?= e(GUIDE_SECTIONS[$k]) ?></a><?php endforeach; ?>
  </nav>

  <div class="guide-grid">
  <?php foreach ($order as $k): $ls = clean_lines($g[$k]['body'] ?? ''); ?>
    <section class="guide-card" id="<?= $k ?>">
      <h2 class="card-title"><?= e(GUIDE_SECTIONS[$k]) ?></h2>
      <?php if (!$ls): ?><div class="muted">Your leader is still adding this.</div>
      <?php elseif (count($ls) > 2 && in_array($k, ['packing', 'contacts', 'wear', 'health', 'entry', 'safety'], true)): ?><ul><?php foreach ($ls as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
      <?php else: ?><div><?= nl2br(e(implode("\n", $ls))) ?></div><?php endif; ?>
      <?php if (!empty($g[$k]['updated_at'])): ?><div class="muted small" style="margin-top:auto">Updated <?= fdate($g[$k]['updated_at'], 'M j') ?></div><?php endif; ?>
    </section>
  <?php endforeach; ?>
    <?php if ($qual): ?>
    <section class="guide-card"><h2 class="card-title">Who can go</h2><ul><?php foreach ($qual as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <?php if ($t['description']): ?><section class="guide-card"><h2 class="card-title">Why we're going</h2><div><?= e($t['description']) ?></div></section><?php endif; ?>
  </div>
  <div class="note"><strong>Something missing?</strong><div class="muted"><?php if (!empty($me_id)): ?>Ask your leaders in <a href="/trip/messages.php?th=leaders">Messages</a>.<?php else: ?>Edit the guide on the trip's Trip guide tab.<?php endif; ?></div></div>
</main>
<?php page_close(); ?>
