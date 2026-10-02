<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
[$done, $total] = readiness($tid, $me_id);
// Each day's first item (by time order entered), for "Your week"
$days = all('SELECT i.day, i.title FROM itinerary i JOIN (SELECT day, MIN(id) AS id FROM itinerary WHERE trip_id = ? GROUP BY day) f ON f.id = i.id ORDER BY i.day LIMIT 4', [$tid]);
$latest = one('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id DESC LIMIT 1', [$tid]);
$docs = all("SELECT * FROM files WHERE trip_id = ? AND visible = 1 AND person_id IS NULL AND kind IN ('doc','link') AND (path IS NOT NULL OR (url IS NOT NULL AND url <> '')) ORDER BY must_ack DESC, id LIMIT 6", [$tid]);
$out = one("SELECT * FROM flights WHERE trip_id = ? AND direction = 'out' ORDER BY departs_at LIMIT 1", [$tid]);
$home = one("SELECT * FROM flights WHERE trip_id = ? AND direction = 'home' ORDER BY departs_at DESC LIMIT 1", [$tid]);
page_open('My trip');
member_header('trip');
?>
<main class="main m" id="main">
  <div class="head"><div class="sub">
    <div class="muted" style="font-size:15px;font-weight:600">Hi <?= e($me['preferred_name'] ?: $me['first_name']) ?></div>
    <h1 class="disp"><?= e($t['name']) ?> mission trip</h1>
    <div class="muted"><?= e(trim($t['city'] . ', ' . $t['country'], ', ')) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?><?= $t['partner'] ? ' · with ' . e($t['partner']) : '' ?></div>
  </div></div>

  <?php if ($latest): ?>
  <a class="cell group" href="/trip/messages.php" style="min-height:64px"><span class="av dark" aria-hidden="true">!</span><div class="grow"><strong><?= e($latest['title'] ?: 'Announcement from your leader') ?></strong><div class="muted small"><?= e(mb_strimwidth($latest['body'], 0, 110, '…')) ?></div></div><span class="muted small"><?= fdate($latest['created_at'], 'M j') ?> ›</span></a>
  <?php endif; ?>

  <?php $photos = trip_photos($tid); ?>
  <?php if ($photos): ?>
  <div class="gallery photos">
    <?php foreach (array_slice($photos, 0, 5) as $i => $ph): ?><div><img src="<?= e($ph['src']) ?>" alt="<?= e($ph['title']) ?>"<?= $i ? ' loading="lazy"' : ' fetchpriority="high"' ?>></div><?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="gallery">
    <div><span class="ghost" aria-hidden="true" style="left:-10px;top:24px;font-size:200px"><?= e(strtoupper($t['name'])) ?></span><span style="position:relative">Trip photos coming soon</span></div>
    <div></div><div></div><div></div><div></div>
  </div>
  <?php endif; ?>

  <div class="split side-400">
    <section class="bento" aria-label="Your trip at a glance">
      <div class="tile dark xl span2" style="position:relative;overflow:hidden;flex-direction:row;align-items:flex-end;justify-content:space-between;min-height:220px">
        <span class="ghost" aria-hidden="true" style="right:-20px;bottom:-50px;font-size:240px;color:rgba(247,244,240,.08)"><?= e(strtoupper(substr($t['name'], 0, 2))) ?></span>
        <div style="position:relative;display:flex;flex-direction:column;gap:6px"><div class="k">Countdown</div><div class="countdown"><?= days_until($t['start_date']) ?></div><div style="font-size:20px;font-weight:600"><?= $t['start_date'] > date('Y-m-d') ? 'days until you fly' : ($t['end_date'] >= date('Y-m-d') ? "You're on the trip!" : 'Welcome home') ?></div></div>
        <div style="position:relative;text-align:right;color:var(--muted-dark);font-size:15px"><?= fdate($t['start_date'], 'l') ?><br><?= fdate($t['start_date'], 'F j, Y') ?></div>
      </div>
      <div class="tile xl"><div class="k">Next team meeting</div>
        <?php if ($next_meeting): ?><div class="disp" style="font-size:44px"><?= fdate($next_meeting['starts_at'], 'M j') ?></div><strong><?= fdate($next_meeting['starts_at'], 'l · g:i A') ?></strong><div class="muted"><?= e(clean_text($next_meeting['location'])) ?></div><a class="btn btn-sm" style="margin-top:auto" href="/trip/schedule.php">See the schedule</a>
        <?php else: ?><div class="muted">Nothing scheduled yet.</div><?php endif; ?></div>
      <div class="tile xl"><div class="k">Your leader</div>
        <?php if ($leader): ?><span class="av dark lg" aria-hidden="true"><?= e(initials(full_name($leader))) ?></span><strong style="font-size:18px"><?= e(full_name($leader)) ?></strong><div class="muted">Trip leader</div><a class="btn btn-sm" style="margin-top:auto" href="/trip/messages.php?th=leaders">Message your leaders</a>
        <?php else: ?><div class="muted">Your leader will show here.</div><?php endif; ?></div>
      <div class="tile xl span2">
        <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="k">Your week in <?= e($t['country'] ?: $t['name']) ?></div><a href="/trip/schedule.php" style="font-size:15px;font-weight:600">Full schedule ›</a></div>
        <?php if ($days): ?><div class="days"><?php foreach ($days as $d): ?><div class="day"><small><?= strtoupper(fdate($d['day'], 'D · M j')) ?></small><strong style="font-size:15px"><?= e($d['title']) ?></strong></div><?php endforeach; ?></div>
        <?php else: ?><div class="muted">Your leader is still planning the day-by-day schedule.</div><?php endif; ?>
      </div>
      <div class="tile xl span2">
        <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="k">Documents and guides</div><a href="/trip/documents.php" style="font-size:15px;font-weight:600">All ›</a></div>
        <?php if ($docs): ?><div class="g2" style="column-gap:32px;row-gap:0"><?php foreach ($docs as $f): ?><a class="row" href="/file.php?id=<?= (int)$f['id'] ?>"><span><?= e($f['title']) ?></span><span class="muted small"><?= $f['must_ack'] ? 'Must read ›' : '›' ?></span></a><?php endforeach; ?></div>
        <?php else: ?><div class="muted">Your leader will post documents here.</div><?php endif; ?>
      </div>
    </section>

    <aside class="panel sticky" style="top:96px">
      <div class="dates"><div><small>Departs</small><?= fdate($t['start_date'], 'D, M j') ?><?= $out && $out['departs_at'] && date('H:i', strtotime($out['departs_at'])) !== '00:00' ? '<div class="muted small">' . fdate($out['departs_at'], 'g:i A') . '</div>' : '' ?></div><div><small>Returns</small><?= fdate($t['end_date'], 'D, M j') ?></div></div>
      <div style="display:flex;gap:18px;align-items:center"><?= ring($done, max(1, $total), 76, false, null, "Checklist: $done of $total done") ?><div><strong style="font-size:18px">Your checklist</strong><div class="muted"><?= $total - $done ? ($total - $done) . ' left to do' : 'All done. See you at the airport.' ?></div></div></div>
      <div class="group" style="box-shadow:none;background:transparent">
        <?php foreach (array_slice($open_tasks, 0, 3) as $k) include dirname(__DIR__) . '/inc/_my_task.php'; ?>
        <?php foreach (array_slice($unread, 0, 2) as $f): ?><a class="cell" href="/trip/documents.php"><span class="check" aria-hidden="true">✓</span><div class="grow"><strong>Read: <?= e($f['title']) ?></strong><div class="muted small">Everyone on the team reads and agrees</div></div></a><?php endforeach; ?>
      </div>
      <?php $nx = $open_tasks[0] ?? null;
        $nxHref = !$nx ? '/trip/guide.php' : ($nx['type'] === 'sign' ? '/sign.php?task=' . (int)$nx['id'] : ($nx['type'] === 'traveler' && !$nx['allow_self'] ? '/trip/messages.php?th=leaders' : '/trip/checklist.php#task-' . (int)$nx['id'])); ?>
      <a class="btn btn-primary btn-wide" href="<?= e($nxHref) ?>"><?= $nx ? 'Next: ' . e(mb_strimwidth($nx['title'], 0, 38, '…')) : 'Read the trip guide' ?></a>
      <?php if (count($open_tasks) > 3): ?><a class="small center" href="/trip/checklist.php">See all <?= count($open_tasks) ?> things left</a><?php endif; ?>
      <div class="note" style="border-radius:var(--r-md);padding:16px;gap:8px">
        <div style="display:flex;justify-content:space-between;align-items:baseline"><strong>Fundraising</strong><a class="small" href="/trip/fundraising.php">Details</a></div>
        <?= bar(pct($raised, $goal)) ?>
        <div style="display:flex;justify-content:space-between;font-size:15px"><span><strong><?= money($raised) ?></strong> <span class="muted">of <?= money($goal) ?></span></span><a href="/trip/fundraising.php">My fundraising page</a></div>
      </div>
    </aside>
  </div>
</main>
<?php page_close(); ?>
