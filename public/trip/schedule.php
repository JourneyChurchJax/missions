<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$t = $trips[$me['trip']];
page_open('Schedule');
member_header('schedule');
$before = [
  'November' => [['NOV', '9', 'Team meeting', 'Sunday · 12:30 PM · [Location]', 'Meeting'], ['NOV', '15', 'Passport copy due', 'Upload it in Documents', 'Task']],
  'December' => [['DEC', '1', 'Code of conduct due', 'Sign it in Documents', 'Task'], ['DEC', '19', '10% of fundraising', '$190 · you are already past it', 'Goal']],
  '2027' => [['FEB', '7', '50% of fundraising', '$950', 'Goal'], ['MAR', '14', 'Fully funded', '$1,900', 'Goal'], ['JUN', '19', 'Fly to Belize', 'Departure', 'Trip']],
];
?>
<main class="main m">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e($t['dates']) ?></div><h1 class="disp">Schedule</h1></div>
    <a class="btn btn-primary" href="#" data-say="Calendar subscription is coming next">Add it all to my calendar</a>
  </div>

  <section style="display:flex;flex-direction:column;gap:14px">
    <div style="display:flex;justify-content:space-between;align-items:baseline;padding:0 4px"><h2 style="margin:0;font-size:22px">Trip week</h2><span class="muted small">[Times fill in from the ministry schedule]</span></div>
    <div class="days seven">
    <?php foreach ($week as [$dow, $d, $evs]): ?>
      <div class="day card"><div><small><?= $dow ?></small><div class="disp" style="font-size:28px"><?= $d ?></div></div>
      <?php foreach ($evs as [$what, $sub]): ?><div class="ev"><strong><?= e($what) ?></strong><?= $sub ? '<br>' . e($sub) : '' ?></div><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <div class="split" style="grid-template-columns:minmax(0,1fr) 360px;gap:40px">
    <section style="display:flex;flex-direction:column;gap:24px">
    <?php foreach ($before as $label => $items): ?>
      <div><div class="gh"><?= $label === 'November' ? 'Before the trip · November' : e($label) ?></div><div class="group">
      <?php foreach ($items as [$m, $d, $what, $sub, $kind]): ?>
        <div class="cell" style="min-height:64px"><?= date_box($m, $d) ?><div class="grow"><strong><?= e($what) ?></strong><div class="muted small"><?= e($sub) ?></div></div><span class="pill<?= $kind === 'Trip' ? ' pill-ok' : '' ?>"><?= e($kind) ?></span></div>
      <?php endforeach; ?>
      </div></div>
    <?php endforeach; ?>
    </section>
    <aside class="sticky" style="top:96px;display:flex;flex-direction:column;gap:20px">
      <section class="panel" style="gap:10px"><strong style="font-size:18px">Flights</strong><div class="dates"><div><small>Out</small>Sat, Jun 19</div><div><small>Home</small>Fri, Jun 25</div></div><div class="muted">Flight numbers and times show here once AFC Travel books the group.</div></section>
      <section class="note"><strong>Your calendar, always current</strong><div class="muted">Subscribe once and every meeting and deadline shows up in Apple, Google or Outlook Calendar. Changes sync on their own.</div></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
