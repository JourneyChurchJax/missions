<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
// Trip leaders go straight to the trip they lead; everything else here is for staff
if (!is_staff_session() && ($lt = led_trips())) { header('Location: /admin/trip.php?id=' . $lt[0]['id']); exit; }
require_staff();
$which = in_array(g('show'), ['past', 'cancelled'], true) ? g('show') : 'upcoming';
$list = trips($which);
$upcoming = trips('upcoming');
$total_travelers = 0; $total_ready = 0; $done = 0; $total = 0; $raised = 0; $alerts = [];
foreach ($upcoming as $t) {
    $id = (int)$t['id'];
    $total_travelers += count(travelers($id)); $total_ready += trip_ready_count($id);
    [$d, $tt] = trip_task_pct($id); $done += $d; $total += $tt; $raised += trip_raised($id);
    foreach (trip_alerts($t) as $al) $alerts[] = [$t['name'], ...$al];
}
$coming = all("SELECT m.*, t.name AS trip FROM meetings m JOIN trips t ON t.id = m.trip_id WHERE m.starts_at >= ? ORDER BY m.starts_at LIMIT 4", [date('Y-m-d')]);
$due = all("SELECT k.*, t.name AS trip FROM tasks k JOIN trips t ON t.id = k.trip_id WHERE k.due_date >= ? ORDER BY k.due_date LIMIT 4", [date('Y-m-d')]);
$feed = array_merge(
    array_map(fn($m) => ['when' => $m['starts_at'], 'title' => $m['title'], 'sub' => date('g:i A', strtotime($m['starts_at'])) . ($m['location'] ? ' · ' . $m['location'] : ''), 'trip' => $m['trip'], 'link' => '/admin/trip.php?id=' . $m['trip_id'] . '&tab=meetings'], $coming),
    array_map(fn($k) => ['when' => $k['due_date'], 'title' => $k['title'] . ' due', 'sub' => TASK_TYPES[$k['type']] ?? 'Task', 'trip' => $k['trip'], 'link' => '/admin/trip.php?id=' . $k['trip_id'] . '&tab=tasks'], $due));
usort($feed, fn($a, $b) => strcmp($a['when'], $b['when']));
$activity = all("SELECT a.*, t.name AS trip FROM activity a LEFT JOIN trips t ON t.id = a.trip_id ORDER BY a.id DESC LIMIT 5");
page_open('Trips');
admin_header('trips');
?>
<main class="main" id="main">
  <div class="bar-head">
    <div>
      <h1><?= greeting() ?><?= !empty($_SESSION['auth']['name']) ? ', ' . e(strtok($_SESSION['auth']['name'], ' ')) : '' ?></h1>
      <div class="muted small"><?= date('l, F j') ?> · <?= count($upcoming) ?> trips coming up · <?= $total_travelers ?> people traveling</div>
    </div>
    <a class="btn btn-primary" href="/admin/trip-edit.php">New trip</a>
  </div>

  <?php if ($alerts): ?>
  <div class="chips" aria-label="Needs your attention" style="margin-top:-8px">
    <?php foreach (array_slice($alerts, 0, 5) as [$trip, $title, $sub, $link]): ?><a class="chip" href="<?= e($link) ?>"><strong><?= e($trip) ?></strong> <?= e($title) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <nav class="seg sm" aria-label="Show" style="align-self:flex-start">
    <a class="tab<?= $which === 'upcoming' ? ' on' : '' ?>" href="/admin/">Upcoming</a><a class="tab<?= $which === 'past' ? ' on' : '' ?>" href="/admin/?show=past">Past</a><a class="tab<?= $which === 'cancelled' ? ' on' : '' ?>" href="/admin/?show=cancelled">Cancelled or postponed</a>
  </nav>

  <section class="g3">
  <?php foreach ($list as $t): $id = (int)$t['id']; $n = count(travelers($id)); $ready = trip_ready_count($id); $g = trip_goal($t); ?>
    <a class="trip" href="/admin/trip.php?id=<?= $id ?>">
      <div class="media<?= ($cv = trip_cover($id)) ? ' has-photo' : '' ?>"><?php if ($cv): ?><img src="<?= e($cv) ?>" alt="" loading="lazy"><?php else: ?><span class="ghost"><?= e(strtoupper($t['name'])) ?></span><?php endif; ?><span class="badge"><?= $t['status'] !== 'active' ? e(ucfirst($t['status'])) : days_until($t['start_date']) . ' days' ?></span><?php if (!$cv): ?><span class="small" style="position:relative;color:var(--muted-dark)">Add photos in Edit trip</span><?php endif; ?></div>
      <div class="facts">
        <div style="display:flex;justify-content:space-between;gap:12px"><strong style="font-size:18px"><?= e($t['name']) ?></strong><strong><?= pct(trip_raised($id), $g) ?>% raised</strong></div>
        <div class="muted"><?= e(date_range($t['start_date'], $t['end_date'])) ?> · <?= $n ?> traveling</div>
        <div style="display:flex;align-items:center;gap:10px"><div style="flex-grow:1"><?= bar(max(2, pct($ready, max(1, $n)))) ?></div><span class="muted small"><?= $ready ?> of <?= $n ?> ready</span></div>
      </div>
    </a>
  <?php endforeach; ?>
    <a class="trip" href="/admin/trip-edit.php"><div class="new"><span class="disp" style="font-size:26px;font-weight:700;letter-spacing:-.02em">Plan a trip</span><span class="muted">Start fresh or copy a past trip</span></div></a>
  </section>

  <section class="split wide-left">
    <div style="display:flex;flex-direction:column;gap:28px">
      <div>
        <div class="gh">Coming up</div>
        <div class="group">
        <?php foreach (array_slice($feed, 0, 5) as $f): ?>
          <a class="cell" href="<?= e($f['link']) ?>"><?= date_box_for($f['when']) ?><div class="grow"><strong><?= e($f['title']) ?></strong><div class="muted small"><?= e($f['sub']) ?></div></div><span class="pill"><?= e($f['trip']) ?></span></a>
        <?php endforeach; ?>
        <?= $feed ? '' : empty_state('Nothing scheduled yet') ?>
        </div>
      </div>
      <div>
        <div class="gh">Recent activity</div>
        <div class="group">
        <?php foreach ($activity as $x): ?>
          <div class="cell"><div class="grow"><strong><?= e($x['who']) ?></strong> <span class="muted"><?= e(lcfirst($x['what'])) ?></span><div class="muted small"><?= e($x['trip'] ?? '') ?> · <?= fdate($x['created_at'], 'M j, g:i A') ?></div></div></div>
        <?php endforeach; ?>
        <?= $activity ? '' : empty_state('Nothing yet. Changes your team makes show up here.') ?>
        </div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:20px">
      <div class="tile dark" style="padding:28px;gap:8px"><div class="k">Raised for upcoming trips</div><div class="disp" style="font-size:64px"><?= money($raised) ?></div><div style="color:rgba(247,244,240,.85)">Gifts and traveler payments for upcoming trips</div></div>
      <div class="g2">
        <div class="tile"><div class="k">Travelers ready</div><div class="disp v"><?= $total_ready ?> of <?= $total_travelers ?></div><div class="muted small">Every step done</div></div>
        <div class="tile"><div class="k">Tasks done</div><div class="disp v"><?= pct($done, max(1, $total)) ?>%</div><div class="muted small"><?= $done ?> of <?= $total ?></div></div>
      </div>
    </div>
  </section>
</main>
<?php page_close(); ?>
