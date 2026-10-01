<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$t = isset($_GET['id']) ? trip((int)$_GET['id']) : trip_by_slug((string)($_GET['t'] ?? 'belize'));
if (!$t) { header('Location: /admin/'); exit; }
$id = (int)$t['id'];
$tabs = ['overview' => 'Overview', 'team' => 'Team', 'tasks' => 'Tasks & goals', 'meetings' => 'Meetings', 'documents' => 'Documents',
         'travel' => 'Flights & itinerary', 'guide' => 'Trip guide', 'budget' => 'Budget', 'giving' => 'Giving', 'updates' => 'Messages', 'ontrip' => 'On the trip'];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? $_GET['tab'] : 'overview';
$here = "/admin/trip.php?id=$id&tab=$tab";
$team = members($id);
$trav = travelers($id);
page_open($t['name'] . ' · ' . $tabs[$tab]);
admin_header('trips');
?>
<main class="main" style="padding-top:24px">
  <?php $cover = trip_cover($id); ?>
  <section class="hero<?= $cover ? ' has-photo' : '' ?>"<?= $cover ? ' style="--photo:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if (!$cover): ?><span class="ghost" style="right:-20px;bottom:-40px;font-size:170px"><?= e(strtoupper($t['name'])) ?></span><?php endif; ?>
    <a href="/admin/" style="position:relative;color:var(--muted-dark);font-size:14px;text-decoration:none">‹ All trips</a>
    <div style="position:relative;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap">
      <div style="display:flex;flex-direction:column;gap:6px">
        <div style="color:var(--muted-dark);font-size:15px;font-weight:600"><?= e(date_range($t['start_date'], $t['end_date'])) ?> · <?= $t['status'] === 'active' ? days_until($t['start_date']) . ' days away' : e(ucfirst($t['status'])) ?></div>
        <h1 class="disp" style="margin:0;font-size:44px"><?= e($t['name']) ?></h1>
        <div style="color:rgba(247,244,240,.85)"><?= e(trim($t['city'] . ', ' . $t['country'], ', ')) ?><?= $t['partner'] ? ' · with ' . e($t['partner']) : '' ?></div>
      </div>
      <div style="display:flex;gap:12px">
        <a class="btn" style="background:rgba(247,244,240,.14);color:var(--cream)" href="/packet.php?trip=<?= $id ?>" target="_blank">Trip packet</a>
        <a class="btn" style="background:rgba(247,244,240,.14);color:var(--cream)" href="/admin/trip-edit.php?id=<?= $id ?>">Edit trip</a>
        <a class="btn btn-primary" href="/admin/trip.php?id=<?= $id ?>&tab=updates">Message the team</a>
      </div>
    </div>
  </section>

  <div class="subnav"><nav class="seg" aria-label="Trip sections">
    <?php foreach ($tabs as $k => $l): ?><a class="tab<?= $k === $tab ? ' on' : '' ?>" href="/admin/trip.php?id=<?= $id ?>&tab=<?= $k ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
  </nav></div>

  <?php require dirname(__DIR__) . "/inc/admin-tabs/$tab.php"; ?>
</main>
<?php page_close(); ?>
