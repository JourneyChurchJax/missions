<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$t = $trips[$me['trip']];
page_open('My trip');
member_header('trip');
?>
<main class="main m">
  <div class="head"><div class="sub">
    <div class="muted" style="font-size:15px;font-weight:600">Hi <?= e($me['first']) ?></div>
    <h1 class="disp"><?= e($t['name']) ?> mission trip</h1>
    <div class="muted"><?= e($t['city']) ?> · <?= e($t['dates']) ?> · Journey Church with <?= e($t['partner']) ?></div>
  </div></div>

  <div class="gallery">
    <div><span class="ghost" style="left:-10px;top:30px;font-size:220px"><?= e($t['ghost']) ?></span><span style="position:relative">[Trip photo]</span></div>
    <div>[Ministry photo]</div><div>[Team photo]</div><div>[Partner photo]</div><div>[Last year's trip]</div>
  </div>

  <div class="split" style="grid-template-columns:minmax(0,1fr) 400px;gap:48px">
    <section class="bento" aria-label="Your trip at a glance">
      <div class="tile dark xl span2" style="position:relative;overflow:hidden;flex-direction:row;align-items:flex-end;justify-content:space-between;min-height:240px">
        <span class="ghost" style="right:-20px;bottom:-50px;font-size:260px;color:rgba(247,244,240,.08)"><?= e($t['code']) ?></span>
        <div style="position:relative;display:flex;flex-direction:column;gap:6px"><div class="k">Countdown</div><div class="disp" style="font-size:140px;letter-spacing:-.05em;line-height:.85"><?= $t['days_away'] ?></div><div style="font-size:20px;font-weight:600">days until you fly</div></div>
        <div style="position:relative;text-align:right;color:var(--muted-dark);font-size:15px">Saturday<br>June 19, 2027</div>
      </div>
      <div class="tile xl"><div class="k">Next team meeting</div><div class="disp" style="font-size:48px">Nov 9</div><strong>Sunday · 12:30 PM</strong><div class="muted">[Location]</div><a class="btn" style="margin-top:auto;height:40px" href="#" data-say="Calendar invite downloaded (preview only)">Add to calendar</a></div>
      <div class="tile xl"><div class="k">Your leader</div><span class="av dark" style="width:56px;height:56px">CR</span><strong style="font-size:18px">Corey Rees</strong><div class="muted">Trip leader · Partner: <?= e($t['partner']) ?></div><a class="btn" style="margin-top:auto;height:40px" href="/trip/messages.php">Message Corey</a></div>
      <div class="tile xl span2">
        <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="k">Your week in Belize</div><a href="/trip/schedule.php" style="font-size:15px;font-weight:600">Full schedule ›</a></div>
        <div class="days"><?php foreach (array_slice($week, 0, 4) as [$dow, $d, $evs]): ?><div class="day"><small><?= $dow ?> · JUN <?= $d ?></small><strong style="font-size:15px"><?= e($evs[count($evs) - 1][0]) ?></strong></div><?php endforeach; ?></div>
        <div class="muted small">[From the tentative ministry schedule]</div>
      </div>
      <div class="tile xl span2">
        <div class="k">Documents and guides</div>
        <div class="g2" style="column-gap:32px;row-gap:0">
          <div><a class="row" href="/trip/documents.php"><span>Tentative ministry schedule</span><span class="muted small">PDF ›</span></a><a class="row" href="/trip/documents.php"><span>Missions code of conduct</span><span class="muted small">Sign ›</span></a><a class="row" href="/trip/documents.php"><span>Liability waiver</span><span class="muted small">Signed</span></a></div>
          <div><?php foreach (array_slice($guides, 0, 3) as [$g]): ?><a class="row" href="/trip/documents.php"><span><?= e($g) ?></span><span class="muted small">›</span></a><?php endforeach; ?></div>
        </div>
      </div>
    </section>

    <aside class="panel sticky" style="top:96px">
      <div class="dates"><div><small>Departs</small>Sat, Jun 19</div><div><small>Returns</small>Fri, Jun 25</div></div>
      <div style="display:flex;gap:18px;align-items:center"><?= ring(2, 5, 76) ?><div><strong style="font-size:18px">Ready to go</strong><div class="muted">3 steps left before June</div></div></div>
      <div>
        <a class="row" href="/trip/documents.php"><span>Upload a passport copy</span><span class="muted small">Nov 15 ›</span></a>
        <a class="row" href="/trip/documents.php"><span>Sign the code of conduct</span><span class="muted small">Dec 1 ›</span></a>
        <a class="row" href="#" data-say="Confirmation is coming next"><span>Confirm name and birth date</span><span class="muted small">›</span></a>
      </div>
      <a class="btn btn-primary btn-wide" href="/trip/documents.php">Finish your next step</a>
      <div class="note" style="border-radius:var(--r-md);padding:16px;gap:8px">
        <div style="display:flex;justify-content:space-between;align-items:baseline"><strong>Fundraising</strong><span class="muted small">50% due Feb 7</span></div>
        <?= bar(pct($me['raised'], $me['goal'])) ?>
        <div style="display:flex;justify-content:space-between;font-size:15px"><span><strong><?= money($me['raised']) ?></strong> <span class="muted">of <?= money($me['goal']) ?></span></span><a href="/trip/fundraising.php">Share my page</a></div>
      </div>
    </aside>
  </div>
</main>
<?php page_close(); ?>
