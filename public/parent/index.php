<?php
// Parent view: a private, read-only page about their child's trip. The ?t= token in the link is the key.
require dirname(__DIR__) . '/inc/bootstrap.php';
$g = !empty($_GET['t']) ? one('SELECT * FROM guardians WHERE token = ?', [(string)$_GET['t']]) : null;
$kid = $g ? person((int)$g['person_id']) : null;
$t = $kid ? trip_for_person((int)$kid['id']) : null;
public_open($t ? $t['name'] . ' trip' : 'Trip');
if (!$g || !$kid || !$t): ?>
<main class="pub-main"><section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">We couldn't find that page.</h1><p class="muted" style="margin:0">The link may have changed. Ask the trip leader for a new one.</p></section></main>
<?php else:
$tid = (int)$t['id']; $name = $kid['preferred_name'] ?: $kid['first_name'];
$gd = guide($tid);
$flights = all("SELECT * FROM flights WHERE trip_id = ? ORDER BY CASE direction WHEN 'out' THEN 0 ELSE 1 END, departs_at", [$tid]);
$byday = []; foreach (all('SELECT * FROM itinerary WHERE trip_id = ? ORDER BY day, id', [$tid]) as $i) $byday[$i['day']][] = $i;
$posts = all('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id DESC LIMIT 10', [$tid]);
$tasks = tasks_for($tid, (int)$kid['id']); $done = count(array_filter($tasks, fn($k) => $k['done_at']));
$meet = all('SELECT * FROM meetings WHERE trip_id = ? AND starts_at >= ? ORDER BY starts_at LIMIT 4', [$tid, date('Y-m-d')]);
$cover = trip_cover($tid);
$block = function (string $k, string $label) use ($gd) { $b = $gd[$k]['body'] ?? ''; if (is_placeholder($b)) return;
    echo '<section class="tile" style="gap:8px"><strong>' . e($label) . '</strong><div style="display:flex;flex-direction:column;gap:4px">' . implode('', array_map(fn($l) => '<div>' . e($l) . '</div>', lines($b))) . '</div></section>'; };
?>
<main class="pub-main" style="max-width:880px">
  <section class="hero<?= $cover ? ' has-photo' : '' ?>"<?= $cover ? ' style="--photo:url(\'' . e($cover) . '\')"' : '' ?>>
    <div style="position:relative;display:flex;flex-direction:column;gap:6px">
      <div style="color:var(--muted-dark);font-weight:600"><?= e(date_range($t['start_date'], $t['end_date'])) ?><?= $t['start_date'] >= date('Y-m-d') ? ' · ' . days_until($t['start_date']) . ' days away' : '' ?></div>
      <h1 class="disp" style="margin:0;font-size:40px;color:var(--cream)"><?= e($name) ?>'s trip to <?= e($t['city'] ?: $t['name']) ?></h1>
      <div style="color:rgba(247,244,240,.85)">For <?= e($g['name']) ?> · from Journey Church Missions</div>
    </div>
  </section>

  <section class="g3">
    <div class="tile"><div class="k">Checklist</div><div class="disp v"><?= $done ?>/<?= count($tasks) ?></div><div class="muted small"><?= $done >= count($tasks) ? 'All done' : e($name) . ' has ' . (count($tasks) - $done) . ' left' ?></div></div>
    <div class="tile"><div class="k">Fundraising</div><div class="disp v"><?= pct((float)(member_of($tid, (int)$kid['id'])['raised'] ?? 0), member_goal($t, member_of($tid, (int)$kid['id']))) ?>%</div><div class="muted small">of <?= money(member_goal($t, member_of($tid, (int)$kid['id']))) ?></div></div>
    <div class="tile"><div class="k">Next meeting</div><div class="disp v" style="font-size:22px"><?= $meet ? fdate($meet[0]['starts_at'], 'M j') : '—' ?></div><div class="muted small"><?= $meet ? e($meet[0]['title']) . ' · ' . fdate($meet[0]['starts_at'], 'g:i A') : 'None scheduled' ?></div></div>
  </section>
  <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-primary" href="/packet.php?p=<?= e($g['token']) ?>" target="_blank">Trip packet (print or PDF)</a><a class="btn" href="<?= e(cal_url('t', cal_token_for_trip($tid))) ?>">Add to my calendar</a></div>

  <?php if ($tasks && $done < count($tasks)): ?><section><div class="gh">Still to do</div><div class="group"><?php foreach ($tasks as $k) if (!$k['done_at']): ?><div class="cell"><?= date_box_for($k['due_date']) ?><div class="grow"><strong><?= e($k['title']) ?></strong><?= $k['description'] ? '<div class="muted small">' . e($k['description']) . '</div>' : '' ?></div></div><?php endif; ?></div></section><?php endif; ?>

  <?php if ($flights): ?><section><div class="gh">Flights</div><div class="group"><?php foreach ($flights as $f): ?><div class="cell"><div class="grow"><strong><?= e($f['flight_no']) ?> · <?= e($f['from_code']) ?> → <?= e($f['to_code']) ?></strong><div class="muted small"><?= $f['departs_at'] ? fdate($f['departs_at'], 'l, M j · g:i A') : 'Time to be announced' ?><?= $f['notes'] ? ' · ' . e($f['notes']) : '' ?></div></div></div><?php endforeach; ?></div></section><?php endif; ?>

  <?php if ($byday): ?><section><div class="gh">Day by day</div><div class="group"><?php foreach ($byday as $day => $items): ?><div class="cell" style="align-items:flex-start"><strong style="width:110px;flex-shrink:0"><?= fdate($day, 'D, M j') ?></strong><div class="grow"><?php foreach ($items as $i): ?><div><?= $i['time'] ? '<span class="muted">' . e($i['time']) . '</span> · ' : '' ?><?= e($i['title']) ?></div><?php endforeach; ?></div></div><?php endforeach; ?></div></section><?php endif; ?>

  <div class="g2" style="gap:16px;align-items:start">
    <?php $block('contacts', 'Who to call'); $block('airport', 'Where to meet'); $block('lodging', 'Where they stay'); $block('phone', 'Staying in touch'); $block('packing', 'Packing list'); $block('safety', 'Safety plan'); ?>
  </div>

  <section><div class="gh">Updates from the leaders</div><div class="group">
    <?php foreach ($posts as $p): ?><div class="post"><div style="display:flex;justify-content:space-between;gap:12px"><strong><?= e($p['title'] ?: 'Update') ?></strong><span class="muted small"><?= fdate($p['created_at'], 'M j') ?></span></div><div><?= soft($p['body']) ?></div></div><?php endforeach; ?>
    <?= $posts ? '' : '<div class="empty">No updates yet.</div>' ?>
  </div></section>
  <p class="muted small" style="text-align:center">This page is private to you. Please don't share the link. Medical and passport details are never shown here.</p>
</main>
<?php endif; ?>
<script src="/assets/app.js?v=5"></script>
</body>
</html>
