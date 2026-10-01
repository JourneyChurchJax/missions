<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$trips_all = trips('upcoming');
$tid = (int)($_GET['trip'] ?? ($trips_all[0]['id'] ?? 0));
$t = trip($tid);
$view = $_GET['view'] ?? '';
$reports = [
  'Before the trip' => [
    'readiness' => ['Team readiness', 'Who is ready to go and what each person still owes'],
    'roster' => ['Travel roster', 'Passport names, numbers, dates and birthdays for the airline'],
    'emergency' => ['Emergency sheet', "Every traveler's contacts, for the leader's bag"],
    'medical' => ['Medical and diet', 'Allergies, medications and diets. Leaders and admins only.'],
    'rooming' => ['Rooming and vans', 'Who sleeps where and who rides with whom'],
    'shirts' => ['T-shirt sizes', 'Counts by size, ready for the printer'],
  ],
  'Money' => [
    'fundraising' => ['Fundraising by person', 'Raised, goal and progress for every traveler'],
    'budget' => ['Budget', 'Every budget line with its total'],
  ],
];

// Build the selected report as header + rows (used for the screen and for CSV)
function report_rows(string $view, array $t): array {
    $id = (int)$t['id']; $ms = travelers($id);
    switch ($view) {
        case 'readiness': return [['Name', 'Role', 'Done', 'Of', 'Passport', 'Emergency contact'], array_map(function ($m) use ($id, $t) { [$d, $n] = readiness($id, (int)$m['person_id']); return [full_name($m), ucfirst($m['role']), $d, $n, passport_ok($m, $t) ? 'Valid' : 'Missing', $m['ec1_name'] ? 'On file' : 'Missing']; }, $ms)];
        case 'roster': return [['Name on passport', 'Passport number', 'Expires', 'Birth date', 'Gender', 'Flight confirmation'], array_map(function ($m) { $p = person((int)$m['person_id']); return [$p['passport_name'] ?: full_name($p), $p['passport_number'], fdate($p['passport_expires'], 'm/d/Y'), fdate($p['birth_date'], 'm/d/Y'), ucfirst((string)$p['gender']), $m['confirmation']]; }, $ms)];
        case 'emergency': return [['Traveler', 'Mobile', 'Contact', 'Relationship', 'Phone', 'Second contact', 'Phone'], array_map(function ($m) { $p = person((int)$m['person_id']); return [full_name($p), $p['phone'], $p['ec1_name'], $p['ec1_rel'], $p['ec1_phone'], $p['ec2_name'], $p['ec2_phone']]; }, $ms)];
        case 'medical': return [['Traveler', 'Allergies', 'Medications', 'Diet', 'Health concerns'], array_map(function ($m) { $p = person((int)$m['person_id']); return [full_name($p), $p['allergies'], $p['meds'], $p['diet'], $p['health']]; }, $ms)];
        case 'rooming': return [['Traveler', 'Gender', 'Room', 'Van or seat'], array_map(function ($m) { $p = person((int)$m['person_id']); return [full_name($p), ucfirst((string)$p['gender']), $m['room'], $m['seat']]; }, $ms)];
        case 'shirts': $c = []; foreach ($ms as $m) { $s = person((int)$m['person_id'])['shirt'] ?: 'Not given'; $c[$s] = ($c[$s] ?? 0) + 1; } ksort($c); return [['Size', 'Count'], array_map(fn($k, $v) => [$k, $v], array_keys($c), $c)];
        case 'fundraising': return [['Traveler', 'Goal', 'Raised', 'Percent'], array_map(fn($m) => [full_name($m), money(member_goal($t, $m)), money((float)$m['raised']), pct((float)$m['raised'], member_goal($t, $m)) . '%'], $ms)];
        case 'budget': $n = max(1, count($ms)); return [['Line', 'Vendor', 'Each', 'Qty', 'Total'], array_map(fn($b) => [$b['description'], $b['vendor'], money((float)$b['unit_cost']), $b['per_traveler'] ? $n : $b['qty'], money((float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']))], all('SELECT * FROM budget WHERE trip_id = ?', [$id]))];
    }
    return [[], []];
}

if ($t && $view && isset($_GET['csv'])) {
    [$head, $rows] = report_rows($view, $t);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/i', '-', $t['name'] . '-' . $view) . '.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, $head, ',', '"', '\\'); foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\'); exit;
}
page_open('Reports');
admin_header('reports');
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Reports</h1><div class="muted">Everything you need before a trip, a board meeting or tax season</div></div>
    <nav class="seg sm" aria-label="Trip"><?php foreach ($trips_all as $x): ?><a class="tab<?= (int)$x['id'] === $tid ? ' on' : '' ?>" href="/admin/reports.php?trip=<?= (int)$x['id'] ?><?= $view ? '&view=' . e($view) : '' ?>"><?= e($x['name']) ?></a><?php endforeach; ?></nav>
  </div>

  <?php if ($t && $view): [$head, $rows] = report_rows($view, $t); $title = ''; foreach ($reports as $g) if (isset($g[$view])) $title = $g[$view][0]; ?>
    <section style="display:flex;flex-direction:column;gap:12px">
      <div class="bar-head"><div><a class="muted small" href="/admin/reports.php?trip=<?= $tid ?>" style="text-decoration:none">‹ All reports</a><h1><?= e($title) ?> · <?= e($t['name']) ?></h1></div>
        <div style="display:flex;gap:10px"><a class="btn" href="/admin/reports.php?trip=<?= $tid ?>&view=<?= e($view) ?>&csv=1">Download CSV</a><button class="btn btn-dark" type="button" onclick="window.print()">Print</button></div></div>
      <div class="group tbl"><table><thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e((string)$c) ?: '<span class="muted">—</span>' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>" class="empty">Nothing to show yet.</td></tr><?php endif; ?></tbody></table></div>
      <?php if (in_array($view, ['roster', 'medical', 'emergency'], true)): ?><div class="muted small">Private information. Print only what the trip needs and keep it with the leader.</div><?php endif; ?>
    </section>
  <?php else: ?>
    <?php foreach ($reports as $group => $items): ?>
    <section>
      <div class="gh"><?= e($group) ?></div>
      <div class="g3" style="gap:20px">
      <?php foreach ($items as $k => [$name, $desc]): ?>
        <a class="tile" href="/admin/reports.php?trip=<?= $tid ?>&view=<?= $k ?>" style="gap:8px;text-decoration:none">
          <strong style="font-size:18px"><?= e($name) ?></strong><span class="muted"><?= e($desc) ?></span>
          <span style="display:flex;gap:8px;margin-top:auto;padding-top:8px"><span class="pill">View</span><span class="pill">Print</span><span class="pill">CSV</span></span>
        </a>
      <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<?php page_close(); ?>
