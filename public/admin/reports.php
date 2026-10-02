<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$trips_all = is_staff_session() ? trips('upcoming') : led_trips();
if (!is_staff_session() && !$trips_all) { header('Location: /trip/'); exit; }
$tid = gi('trip') ?: (int)($trips_all[0]['id'] ?? 0);
if (!is_staff_session() && !in_array($tid, array_map('intval', array_column($trips_all, 'id')), true)) $tid = (int)$trips_all[0]['id'];
$t = trip($tid);
$view = g('view');
// Which permission each report needs
const REPORT_AREA = ['readiness' => 'tasks', 'roster' => 'travel', 'emergency' => 'team', 'medical' => 'medical', 'rooming' => 'team', 'shirts' => 'team',
    'signatures' => 'tasks', 'background' => 'staff', 'fundraising' => 'giving', 'budget' => 'budget', 'gifts' => 'giving', 'expenses' => 'budget'];
$may = fn(string $v) => is_staff_session() || (($a = REPORT_AREA[$v] ?? 'staff') !== 'staff' && can($a, $tid));
$reports = [
  'Before the trip' => [
    'readiness' => ['Team readiness', 'Who is ready to go and what each person still owes'],
    'roster' => ['Travel roster', 'Passport names, numbers, dates and birthdays for the airline'],
    'emergency' => ['Emergency sheet', "Every traveler's contacts, for the leader's bag"],
    'medical' => ['Medical and diet', 'Allergies, medications and diets. Leaders and admins only.'],
    'rooming' => ['Rooming and vans', 'Who sleeps where and who rides with whom'],
    'shirts' => ['T-shirt sizes', 'Counts by size, ready for the printer'],
    'signatures' => ['Signed documents', 'Who has signed each waiver and form, and which parents still need to'],
    'background' => ['Background checks', 'Leaders and adults who need a check, and where each one stands'],
  ],
  'Money' => [
    'fundraising' => ['Fundraising by person', 'Raised, goal and progress for every traveler'],
    'budget' => ['Budget', 'Every budget line with its total'],
    'gifts' => ['Gifts', 'Every gift to this trip, with donor and method'],
    'expenses' => ['Expenses', 'What was spent, by whom, and what still needs paying back'],
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
        case 'signatures': $sts = all("SELECT * FROM tasks WHERE trip_id = ? AND type = 'sign' ORDER BY due_date", [$id]);
            return [array_merge(['Traveler'], array_column($sts, 'title')), array_map(function ($m) use ($sts) { $row = [full_name($m)]; foreach ($sts as $k) { [$a, $b, $c] = signature_state($k, (int)$m['person_id']); $row[] = !$a ? 'Not signed' : ($c && !$b ? 'Needs parent' : 'Signed'); } return $row; }, $ms)];
        case 'background': return [['Name', 'Role', 'Status', 'Provider', 'Cleared', 'Expires'], array_map(function ($m) use ($t) { $bg = latest_bg((int)$m['person_id']); return [full_name($m), ucfirst($m['role']), BG_STATE_LABEL[bg_state($bg, $t['end_date'])], $bg['provider'] ?? '', fdate($bg['completed_at'] ?? null, 'm/d/Y'), fdate($bg['expires_on'] ?? null, 'm/d/Y')]; }, bg_needed($t))];
        case 'gifts': return [['Date', 'Donor', 'For', 'Method', 'Amount'], array_map(fn($g) => [fdate($g['gift_date'], 'm/d/Y'), donor_name($g['donor_id'] ? donor((int)$g['donor_id']) : null), gift_for($g), GIFT_METHODS[$g['method']] ?? '', money((float)$g['amount'], 2)], gifts(['trip' => $id], 100000))];
        case 'expenses': return [['Date', 'What', 'Type', 'Paid by', 'USD', 'Pay back'], array_map(fn($x) => [fdate($x['spent_on'], 'm/d/Y'), $x['description'], $x['type'], $x['paid_by'], money((float)$x['usd'], 2), $x['reimburse'] ? ($x['reimbursed_at'] ? 'Paid back' : 'Owed') : ''], all('SELECT * FROM expenses WHERE trip_id = ? ORDER BY spent_on', [$id]))];
        case 'budget': $n = max(1, count($ms)); return [['Line', 'Vendor', 'Each', 'Qty', 'Total'], array_map(fn($b) => [$b['description'], $b['vendor'], money((float)$b['unit_cost']), $b['per_traveler'] ? $n : $b['qty'], money((float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']))], all('SELECT * FROM budget WHERE trip_id = ?', [$id]))];
    }
    return [[], []];
}

if ($view && !$may($view)) { http_response_code(403); $view = ''; flash("You don't have access to that report.", 'error'); }
if ($t && $view) audit(g('csv') !== '' ? 'report_download' : 'report_view', 'reports', $tid, $tid, $view);
if ($t && $view && g('csv') !== '') {
    [$head, $rows] = report_rows($view, $t);
    $out = csv_start($t['name'] . '-' . $view . '.csv'); csv_out($out, $head); foreach ($rows as $r) csv_out($out, $r); exit;
}
page_open('Reports');
admin_header('reports');
?>
<main class="main" id="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Reports</h1><div class="muted">Everything you need before a trip, a board meeting or tax season</div></div>
    <nav class="seg sm" aria-label="Trip"><?php foreach ($trips_all as $x): ?><a class="tab<?= (int)$x['id'] === $tid ? ' on' : '' ?>" href="/admin/reports.php?trip=<?= (int)$x['id'] ?><?= $view ? '&view=' . e($view) : '' ?>"><?= e($x['name']) ?></a><?php endforeach; ?></nav>
  </div>

  <?php if ($t && $view): [$head, $rows] = report_rows($view, $t); $title = ''; foreach ($reports as $g) if (isset($g[$view])) $title = $g[$view][0]; ?>
    <section style="display:flex;flex-direction:column;gap:12px">
      <div class="bar-head"><div><a class="muted small" href="/admin/reports.php?trip=<?= $tid ?>" style="text-decoration:none">‹ All reports</a><h1><?= e($title) ?> · <?= e($t['name']) ?></h1></div>
        <div style="display:flex;gap:10px"><a class="btn" href="/admin/reports.php?trip=<?= $tid ?>&view=<?= e($view) ?>&csv=1">Download spreadsheet</a><button class="btn btn-dark" type="button" data-print>Print</button></div></div>
      <div class="group tbl"><table><thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= (string)$c !== '' ? e((string)$c) : '' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>" class="empty">Nothing to show yet.</td></tr><?php endif; ?></tbody></table></div>
      <?php if (in_array($view, ['roster', 'medical', 'emergency'], true)): ?><div class="muted small">Private information. Print only what the trip needs and keep it with the leader.</div><?php endif; ?>
    </section>
  <?php else: ?>
    <?php foreach ($reports as $group => $items): ?>
    <section>
      <h2 class="gh"><?= e($group) ?></h2>
      <div class="g3">
      <?php foreach ($items as $k => [$name, $desc]): if (!$may($k)) continue; ?>
        <a class="tile link-tile" href="/admin/reports.php?trip=<?= $tid ?>&view=<?= $k ?>">
          <h3 class="card-title"><?= e($name) ?></h3><span class="muted"><?= e($desc) ?></span>
          <span class="muted small strong" style="margin-top:auto">Open ›</span>
        </a>
      <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<?php page_close(); ?>
