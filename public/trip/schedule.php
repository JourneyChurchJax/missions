<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$itin = all('SELECT * FROM itinerary WHERE trip_id = ? ORDER BY day, id', [$tid]);
$byday = []; foreach ($itin as $i) $byday[$i['day']][] = $i;
$flights = all("SELECT * FROM flights WHERE trip_id = ? ORDER BY CASE direction WHEN 'out' THEN 0 ELSE 1 END, departs_at", [$tid]);
// Everything before the trip: meetings, your tasks, fundraising goals
$events = [];
foreach (all('SELECT * FROM meetings WHERE trip_id = ? AND starts_at >= ? ORDER BY starts_at', [$tid, date('Y-m-d')]) as $m) $events[] = [$m['starts_at'], $m['title'], fdate($m['starts_at'], 'l · g:i A') . ($m['location'] ? ' · ' . $m['location'] : ''), 'Meeting'];
foreach ($my_tasks as $k) if ($k['due_date'] && !$k['done_at']) $events[] = [$k['due_date'], $k['title'] . ' due', $k['description'] ?: 'On your checklist', 'Task'];
foreach (all('SELECT * FROM goals WHERE trip_id = ? AND due_date >= ? ORDER BY due_date', [$tid, date('Y-m-d')]) as $g) {
    $need = $g['kind'] === 'percent' ? $goal * $g['amount'] / 100 : (float)$g['amount'];
    $events[] = [$g['due_date'], ($g['kind'] === 'percent' ? (int)$g['amount'] . '% of fundraising' : money((float)$g['amount'])) . ' (' . money($need) . ')', $raised >= $need ? "You're already past it" : money($need - $raised) . ' to go', 'Goal'];
}
$events[] = [$t['start_date'], 'Fly to ' . ($t['city'] ?: $t['name']), 'Departure day', 'Trip'];
usort($events, fn($a, $b) => strcmp($a[0], $b[0]));
$months = []; foreach ($events as $ev) $months[fdate($ev[0], 'F Y')][] = $ev;
page_open('Schedule');
member_header('schedule');
?>
<main class="main m">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Schedule</h1></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="/packet.php" target="_blank">Trip packet</a><a class="btn btn-primary" href="<?= e(cal_url('p', cal_token_for_person($me_id))) ?>">Add it all to my calendar</a></div>
  </div>

  <section style="display:flex;flex-direction:column;gap:14px">
    <div style="display:flex;justify-content:space-between;align-items:baseline;padding:0 4px"><h2 style="margin:0;font-size:22px">Trip week</h2><span class="muted small">Your leader keeps this current</span></div>
    <div class="days seven">
    <?php foreach ($byday as $day => $items): ?>
      <div class="day card"><div><small><?= strtoupper(fdate($day, 'D')) ?></small><div class="disp" style="font-size:28px"><?= fdate($day, 'j') ?></div></div>
      <?php foreach ($items as $i): ?><div class="ev"><div class="muted" style="font-size:12px;font-weight:600"><?= soft($i['time']) ?></div><strong><?= e($i['title']) ?></strong><?= $i['detail'] ? '<br>' . soft($i['detail']) : '' ?></div><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?= $byday ? '' : '<div class="group"><div class="empty">The day-by-day plan will show here.</div></div>' ?>
  </section>

  <div class="split" style="grid-template-columns:minmax(0,1fr) 380px;gap:40px">
    <section style="display:flex;flex-direction:column;gap:24px">
    <?php foreach ($months as $label => $items): ?>
      <div><div class="gh"><?= e($label) ?></div><div class="group">
      <?php foreach ($items as [$when, $what, $sub, $kind]): ?>
        <div class="cell" style="min-height:64px"><?= date_box_for($when) ?><div class="grow"><strong><?= e($what) ?></strong><div class="muted small"><?= soft($sub) ?></div></div><span class="pill<?= $kind === 'Trip' ? ' pill-ok' : '' ?>"><?= e($kind) ?></span></div>
      <?php endforeach; ?>
      </div></div>
    <?php endforeach; ?>
    </section>
    <aside class="sticky" style="top:96px;display:flex;flex-direction:column;gap:20px">
      <section class="panel" style="gap:12px"><strong style="font-size:18px">Your flights</strong>
        <?php foreach ($flights as $f): ?>
          <div style="display:flex;flex-direction:column;gap:2px;padding:10px 0;box-shadow:inset 0 -1px 0 var(--sand)">
            <div class="small muted" style="font-weight:600"><?= $f['direction'] === 'out' ? 'Going' : 'Coming home' ?> · <?= $f['departs_at'] ? fdate($f['departs_at'], 'D, M j') : 'Date TBD' ?></div>
            <div class="disp" style="font-size:26px;letter-spacing:-.03em"><?= e($f['from_code']) ?> → <?= e($f['to_code']) ?></div>
            <div class="small"><?= e($f['airline']) ?> <?= soft($f['flight_no']) ?><?= $f['departs_at'] && date('H:i', strtotime($f['departs_at'])) !== '00:00' ? ' · ' . fdate($f['departs_at'], 'g:i A') : '' ?></div>
            <?php if ($f['notes']): ?><div class="muted small"><?= soft($f['notes']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($mem['confirmation']): ?><div><span class="muted small">Your confirmation</span><div class="disp" style="font-size:24px;letter-spacing:.02em"><?= e($mem['confirmation']) ?></div></div><?php else: ?><div class="muted small">Your confirmation number shows here once you're ticketed.</div><?php endif; ?>
        <a class="small" href="/trip/guide.php#airport">Where to meet at the airport ›</a>
      </section>
      <section class="note"><strong>Your calendar, always current</strong><div class="muted">Soon you'll subscribe once and every meeting and deadline will show up in Apple, Google or Outlook Calendar.</div></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
