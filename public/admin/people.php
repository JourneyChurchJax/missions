<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$q = trim((string)($_GET['q'] ?? ''));
$filter = in_array($_GET['f'] ?? '', ['travelers', 'leaders', 'none'], true) ? $_GET['f'] : 'all';
$rows = all("SELECT p.*, GROUP_CONCAT(t.name, ', ') AS trip_names, MAX(m.role) AS role_any,
             SUM(CASE WHEN m.role IN ('leader','admin') THEN 1 ELSE 0 END) AS leads, COUNT(m.id) AS trip_count
             FROM people p LEFT JOIN members m ON m.person_id = p.id LEFT JOIN trips t ON t.id = m.trip_id
             GROUP BY p.id ORDER BY p.last_name, p.first_name");
if (driver() === 'mysql') $rows = all("SELECT p.*, GROUP_CONCAT(t.name SEPARATOR ', ') AS trip_names, SUM(CASE WHEN m.role IN ('leader','admin') THEN 1 ELSE 0 END) AS leads, COUNT(m.id) AS trip_count FROM people p LEFT JOIN members m ON m.person_id = p.id LEFT JOIN trips t ON t.id = m.trip_id GROUP BY p.id ORDER BY p.last_name, p.first_name");
$rows = array_values(array_filter($rows, function ($p) use ($q, $filter) {
    if ($q !== '' && stripos(full_name($p) . ' ' . $p['email'] . ' ' . $p['trip_names'] . ' ' . $p['tags'], $q) === false) return false;
    return match ($filter) { 'travelers' => $p['trip_count'] > 0, 'leaders' => $p['leads'] > 0, 'none' => (int)$p['trip_count'] === 0, default => true };
}));
page_open('People');
admin_header('people');
?>
<main class="main" id="main">
  <div class="head">
    <div class="sub"><h1 class="disp">People</h1><div class="muted">Everyone who has applied, traveled or led<?= $q ? ' · results for “' . e($q) . '” · <a href="/admin/people.php">clear</a>' : '' ?></div></div>
    <div style="display:flex;gap:12px"><a class="btn" href="/admin/pco-import.php">Add from Planning Center</a><a class="btn btn-primary" href="/admin/person.php">Add a person</a></div>
  </div>
  <div class="seg sm" style="align-self:flex-start">
    <?php foreach (['all' => 'Everyone', 'travelers' => 'On a trip', 'leaders' => 'Leaders', 'none' => 'Not on a trip'] as $k => $l): ?><a class="tab<?= $filter === $k ? ' on' : '' ?>" href="/admin/people.php?f=<?= $k ?><?= $q ? '&q=' . urlencode($q) : '' ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
  <section class="group tbl">
    <table>
      <thead><tr><th>Name</th><th>Trips</th><th>Email</th><th>Passport</th><th>Emergency</th><th>Tags</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $p): ?>
        <tr class="clickable" onclick="location.href='/admin/person.php?id=<?= (int)$p['id'] ?>'">
          <td><a class="who" href="/admin/person.php?id=<?= (int)$p['id'] ?>" style="text-decoration:none"><span class="av"><?= initials(full_name($p)) ?></span><?= e(full_name($p)) ?></a></td>
          <td><?= e($p['trip_names'] ?: 'No trip') ?></td>
          <td class="muted"><?= e($p['email'] ?: 'No email') ?></td>
          <td><span class="pill<?= $p['passport_expires'] ? ' pill-ok' : '' ?>"><?= $p['passport_expires'] ? 'Valid to ' . fdate($p['passport_expires'], 'Y') : 'Missing' ?></span></td>
          <td><span class="pill<?= $p['ec1_name'] ? ' pill-ok' : '' ?>"><?= $p['ec1_name'] ? 'On file' : 'Missing' ?></span></td>
          <td class="muted"><?= e($p['tags'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">No one matches.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="muted small" style="padding:14px 18px;box-shadow:inset 0 1px 0 var(--sand)"><?= count($rows) ?> people</div>
  </section>
</main>
<?php page_close(); ?>
