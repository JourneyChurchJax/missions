<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$t = gi('id') ? trip(gi('id')) : null;
if (!$t) { header('Location: /admin/'); exit; }
$id = (int)$t['id'];
$role = trip_role($id);
if (!in_array($role, ['staff', 'admin', 'leader'], true)) { header('Location: ' . (is_staff_session() ? '/admin/' : '/trip/')); exit; }
if (is_staff_session()) $_SESSION['view'] = 'staff';

// Tabs, grouped, each tied to the permission that controls it
$groups = [
    'Plan' => ['overview' => ['Overview', null], 'team' => ['Team', 'team'], 'tasks' => ['Checklist and goals', 'tasks'], 'meetings' => ['Meetings', 'meetings']],
    'Logistics' => ['travel' => ['Flights and itinerary', 'travel'], 'guide' => ['Trip guide', 'travel'], 'documents' => ['Documents', 'documents']],
    'Money' => ['budget' => ['Budget and spending', 'budget'], 'giving' => ['Giving', 'giving']],
    'Communicate' => ['updates' => ['Messages', 'messages']],
    'During the trip' => ['ontrip' => ['Headcount and incidents', 'ontrip']],
];
$tabs = []; $area = [];
foreach ($groups as $gname => $items) foreach ($items as $k => [$label, $perm]) { if ($perm && !can($perm, $id)) { unset($groups[$gname][$k]); continue; } $tabs[$k] = $label; $area[$k] = $perm; }
$groups = array_filter($groups);
$tab = isset($tabs[g('tab')]) ? g('tab') : 'overview';
$readonly = $area[$tab] && !can($area[$tab], $id, 2);
$here = "/admin/trip.php?id=$id&tab=$tab";
$team = members($id);
$trav = travelers($id);
page_open($t['name'] . ' · ' . $tabs[$tab]);
admin_header('trips');
$cover = trip_cover($id);
?>
<main class="main" id="main" style="padding-top:24px">
  <?php if ($tab === 'overview'): ?>
  <section class="hero<?= $cover ? ' has-photo' : '' ?>"<?= $cover ? ' style="--photo:url(\'' . e($cover) . '\')"' : '' ?>>
    <?php if (!$cover): ?><span class="ghost" aria-hidden="true" style="right:-20px;bottom:-40px;font-size:170px"><?= e(strtoupper($t['name'])) ?></span><?php endif; ?>
    <?php if (is_staff_session()): ?><a href="/admin/" class="hero-back">‹ All trips</a><?php endif; ?>
    <div class="hero-row">
      <div class="stack-6">
        <div class="hero-kicker"><?= e(date_range($t['start_date'], $t['end_date'])) ?> · <?= $t['status'] === 'active' ? ($t['end_date'] < date('Y-m-d') ? 'Finished' : days_until($t['start_date']) . ' days away') : e(ucfirst($t['status'])) ?></div>
        <h1 class="disp hero-title"><?= e($t['name']) ?></h1>
        <div class="on-dark-2"><?= e(trim($t['city'] . ', ' . $t['country'], ', ')) ?><?= $t['partner'] ? ' · with ' . e($t['partner']) : '' ?></div>
      </div>
      <div class="hero-actions">
        <a class="btn btn-glass" href="/packet.php?trip=<?= $id ?>" target="_blank" rel="noopener">Trip packet</a>
        <?php if (is_staff_session()): ?><a class="btn btn-glass" href="/admin/trip-edit.php?id=<?= $id ?>">Edit trip</a><?php endif; ?>
        <?php if (can('messages', $id, 2)): ?><a class="btn btn-primary" href="/admin/trip.php?id=<?= $id ?>&tab=updates">Message the team</a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php else: ?>
  <div class="trip-bar">
    <div><?php if (is_staff_session()): ?><a class="muted small back-link" href="/admin/">‹ All trips</a><?php endif; ?><h1 class="page-title"><?= e($t['name']) ?> <span class="muted">· <?= e($tabs[$tab]) ?></span></h1></div>
    <span class="muted small"><?= e(date_range($t['start_date'], $t['end_date'])) ?></span>
  </div>
  <?php endif; ?>

  <div class="workspace">
    <nav class="rail" aria-label="Trip sections">
      <?php foreach ($groups as $gname => $items): ?>
        <div class="rail-group"><div class="rail-label"><?= e($gname) ?></div>
          <?php foreach ($items as $k => [$label]): ?><a class="rail-link<?= $k === $tab ? ' on' : '' ?>" href="/admin/trip.php?id=<?= $id ?>&tab=<?= $k ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <form class="rail-select" method="get" action="/admin/trip.php"><input type="hidden" name="id" value="<?= $id ?>">
      <label class="sr" for="tabsel">Trip section</label>
      <select id="tabsel" name="tab" onchange="this.form.submit()"><?php foreach ($groups as $gname => $items): ?><optgroup label="<?= e($gname) ?>"><?php foreach ($items as $k => [$label]): ?><option value="<?= $k ?>"<?= $k === $tab ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
      <noscript><button class="btn btn-sm" type="submit">Go</button></noscript>
    </form>
    <div class="workspace-body<?= $readonly ? ' readonly' : '' ?>">
      <?php if ($readonly): ?><div class="note small-note">You can view this section. Ask staff if you need to make changes.</div><?php endif; ?>
      <?php require dirname(__DIR__) . "/inc/admin-tabs/$tab.php"; ?>
    </div>
  </div>
</main>
<?php page_close(); ?>
