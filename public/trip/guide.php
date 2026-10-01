<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
// Staff can preview a trip's guide; travelers see their own
if (($_SESSION['view'] ?? 'staff') === 'staff') {
    $t = isset($_GET['trip']) ? trip((int)$_GET['trip']) : (trips('upcoming')[0] ?? null);
    if (!$t) { header('Location: /admin/'); exit; }
    $tid = (int)$t['id']; $me_id = null;
} else {
    require dirname(__DIR__) . '/inc/member.php';
}
$g = guide($tid);
$qual = lines($t['qualifications']);
$order = ['airport', 'lodging', 'contacts', 'packing', 'wear', 'money', 'weather', 'power', 'phone', 'health', 'entry', 'safety'];
page_open('Trip guide');
($_SESSION['view'] ?? 'staff') === 'staff' ? admin_header('trips') : member_header('guide');
?>
<main class="main m">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Trip guide</h1><div class="muted">Everything you need to know before you go. Your leader keeps it up to date.</div></div>
    <button class="btn" type="button" onclick="window.print()">Print or save as PDF</button>
  </div>

  <nav class="chips" aria-label="Jump to">
    <?php foreach ($order as $k): ?><a class="chip" href="#<?= $k ?>" style="min-height:38px;font-size:14px"><?= e(GUIDE_SECTIONS[$k]) ?></a><?php endforeach; ?>
  </nav>

  <div class="guide-grid">
  <?php foreach ($order as $k): $body = $g[$k]['body'] ?? ''; $ls = lines($body); ?>
    <section class="guide-card" id="<?= $k ?>">
      <h3><?= e(GUIDE_SECTIONS[$k]) ?></h3>
      <?php if (!$ls): ?><div class="muted">Coming soon from your leader.</div>
      <?php elseif (count($ls) > 2 && in_array($k, ['packing', 'contacts', 'wear', 'health', 'entry', 'safety'], true)): ?><ul><?php foreach ($ls as $l): ?><li><?= soft($l) ?></li><?php endforeach; ?></ul>
      <?php else: ?><div><?= soft($body) ?></div><?php endif; ?>
      <?php if (!empty($g[$k]['updated_at'])): ?><div class="muted small" style="margin-top:auto">Updated <?= fdate($g[$k]['updated_at'], 'M j') ?></div><?php endif; ?>
    </section>
  <?php endforeach; ?>
    <?php if ($qual): ?>
    <section class="guide-card"><h3>Who can go</h3><ul><?php foreach ($qual as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <?php if ($t['description']): ?><section class="guide-card"><h3>Why we're going</h3><div><?= e($t['description']) ?></div></section><?php endif; ?>
  </div>
  <div class="note"><strong>Something missing?</strong><div class="muted">Ask your leader in <a href="/trip/messages.php">Messages</a>. Anything in [brackets] is still being finalized.</div></div>
</main>
<?php page_close(); ?>
