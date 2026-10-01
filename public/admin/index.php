<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
page_open('Trips');
admin_header('trips');
$total_raised = array_sum(array_column($trips, 'raised'));
$total_travelers = array_sum(array_column($trips, 'travelers'));
$total_ready = array_sum(array_column($trips, 'ready'));
?>
<main class="main">
  <div class="bar-head">
    <div>
      <h1><?= greeting() ?>, Adam</h1>
      <div class="muted small"><?= (new DateTimeImmutable())->format('l, F j') ?> · <?= count($trips) ?> trips coming up · <?= $total_travelers ?> people traveling</div>
    </div>
    <a class="btn btn-primary" href="#" data-say="New trip setup is coming next">New trip</a>
  </div>

  <div class="chips" aria-label="Needs your attention" style="margin-top:-8px">
    <a class="chip" href="/admin/applications.php"><strong>4</strong> applications to review</a>
    <a class="chip" href="/admin/trip.php?t=belize"><strong>2</strong> pages to approve</a>
    <a class="chip" href="/admin/trip.php?t=belize">Belize goal vs budget</a>
    <a class="chip" href="/admin/trip.php?t=belize">1 missing passport</a>
  </div>

  <section class="g3">
  <?php foreach ([$trips['israel'], $trips['belize']] as $t): ?>
    <a class="trip" href="/admin/trip.php?t=<?= $t['slug'] ?>">
      <div class="media"><span class="ghost"><?= e($t['ghost']) ?></span><span class="badge"><?= $t['days_away'] ?> days</span><span class="small" style="position:relative;color:var(--muted-dark)">[Trip photo]</span></div>
      <div class="facts">
        <div style="display:flex;justify-content:space-between;gap:12px"><strong style="font-size:18px"><?= e($t['name']) ?></strong><strong><?= pct($t['raised'], $t['goal']) ?>% raised</strong></div>
        <div class="muted"><?= e($t['dates']) ?> · <?= $t['travelers'] ?> travelers</div>
        <div style="display:flex;align-items:center;gap:10px"><div style="flex-grow:1"><?= bar(max(2, pct($t['ready'], $t['travelers']))) ?></div><span class="muted small"><?= $t['ready'] ?> of <?= $t['travelers'] ?> ready</span></div>
      </div>
    </a>
  <?php endforeach; ?>
    <a class="trip" href="#" data-say="New trip setup is coming next"><div class="new"><span class="disp" style="font-size:28px;font-weight:700;letter-spacing:-.02em">Plan a trip</span><span class="muted">Start fresh or copy Belize</span></div></a>
  </section>

  <section class="split wide-left">
    <div style="display:flex;flex-direction:column;gap:28px">
      <div>
        <div class="gh">Coming up</div>
        <div class="group">
        <?php foreach ($coming_up as [$m, $d, $what, $when, $trip]): ?>
          <div class="cell"><?= date_box($m, $d) ?><div class="grow"><strong><?= e($what) ?></strong><div class="muted small"><?= e($when) ?></div></div><span class="pill"><?= e($trip) ?></span></div>
        <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div class="gh"><span>Applications to review</span><a href="/admin/applications.php">See all</a></div>
        <div class="group">
        <?php foreach (array_slice($applicants, 0, 3) as $a): ?>
          <a class="cell" href="/admin/applications.php"><span class="av"><?= initials($a['name']) ?></span><div class="grow"><strong><?= e($a['name']) ?></strong><div class="muted small"><?= e($a['note']) ?></div></div><span class="muted small"><?= e($a['ago']) ?> ›</span></a>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:20px">
      <div class="tile dark" style="padding:28px;gap:8px"><div class="k">Giving this season</div><div class="disp" style="font-size:72px"><?= money($total_raised) ?></div><div style="color:rgba(247,244,240,.85)">$1,420 this week · 11 monthly givers</div></div>
      <div class="g2">
        <div class="tile"><div class="k">Travelers ready</div><div class="disp v"><?= $total_ready ?> of <?= $total_travelers ?></div><div class="muted small">Every step done</div></div>
        <div class="tile"><div class="k">Tasks done</div><div class="disp v">58%</div><div class="muted small">Across both trips</div></div>
      </div>
      <div>
        <div class="gh"><span>Recent gifts</span><a href="/admin/giving.php">All giving</a></div>
        <div class="group">
        <?php foreach (array_slice($gifts, 0, 3) as [$d, $who, $for, $how, $fee, $amt]): ?>
          <div class="cell"><div class="grow"><strong><?= e($who) ?></strong><div class="muted small"><?= e($for) ?> · <?= e($how) ?></div></div><strong><?= money($amt) ?></strong></div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</main>
<?php page_close(); ?>
