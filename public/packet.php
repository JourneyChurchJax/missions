<?php
// Trip packet: everything a traveler or parent needs, laid out to print or save as a PDF.
// Staff: ?trip=ID (add &full=1 for the leader copy with roster, emergency and medical info).
// Travelers: their own trip. Parents: ?p=their private token.
if (!empty($_GET['p'])) define('REAL_DB', true);
require __DIR__ . '/inc/bootstrap.php';

$full = false; $staff = false;
if (g('p') !== '') {
    $g = one('SELECT * FROM guardians WHERE token = ?', [g('p')]);
    if ($g && !guardian_link_valid($g)) $g = null;
    $t = $g ? trip_for_person((int)$g['person_id']) : null;
    $back = $g ? '/parent/?t=' . $g['token'] : '/';
} else {
    require_preview();
    $t = gi('trip') && can('team', gi('trip')) ? trip(gi('trip')) : trip_for_person((int)acting_person_id());
    $staff = $t && can('team', (int)$t['id']);
    // The leader copy has medical details: only for people allowed to see them
    $full = $staff && g('full') !== '' && can('medical', (int)$t['id']);
    if ($full) audit('packet_leader_copy', 'trips', (int)$t['id'], (int)$t['id']);
    $back = $staff ? '/admin/trip.php?id=' . $t['id'] : '/trip/';
}
if (!$t) { http_response_code(404); exit('Trip not found'); }
$tid = (int)$t['id'];
$gd = guide($tid);
$flights = all("SELECT * FROM flights WHERE trip_id = ? ORDER BY CASE direction WHEN 'out' THEN 0 ELSE 1 END, departs_at", [$tid]);
$byday = []; foreach (all('SELECT * FROM itinerary WHERE trip_id = ? ORDER BY day, id', [$tid]) as $i) $byday[$i['day']][] = $i;
$meet = all('SELECT * FROM meetings WHERE trip_id = ? AND starts_at >= ? ORDER BY starts_at', [$tid, date('Y-m-d')]);
$team = members($tid);
$sec = function (string $k) use ($gd) { $ls = clean_lines($gd[$k]['body'] ?? ''); return $ls ? '<ul>' . implode('', array_map(fn($l) => '<li>' . e($l) . '</li>', $ls)) . '</ul>' : ''; };
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($t['name']) ?> trip packet · Journey Missions</title>
<link rel="stylesheet" href="<?= asset('/assets/app.css') ?>">
<style>
body{background:var(--sand)}
.sheet{background:#fff;max-width:820px;margin:24px auto;padding:48px 56px;border-radius:var(--r-md);box-shadow:var(--sh-md);font-size:14.5px;line-height:1.5}
.sheet h2{font-size:20px;margin:28px 0 8px;letter-spacing:-.01em}.sheet ul{margin:0;padding-left:20px}.sheet li{margin:2px 0}
.sheet table{width:100%;border-collapse:collapse;font-size:13.5px}.sheet td,.sheet th{padding:6px 8px 6px 0;border-bottom:1px solid var(--sand);text-align:left;vertical-align:top}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:8px 32px}
.bar-print{max-width:820px;margin:24px auto 0;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:0 16px}
@media (max-width:700px){.sheet{padding:28px 20px}.cols{grid-template-columns:1fr}}
@media print{body{background:#fff}.bar-print{display:none}.sheet{box-shadow:none;margin:0;max-width:none;padding:0 4px;border-radius:0}.pb{page-break-before:always}}
</style>
</head>
<body>
<div class="bar-print"><a href="<?= e($back) ?>">‹ Back</a><span style="display:flex;gap:10px"><?php if ($staff && !$full && can('medical', $tid)): ?><a class="btn" href="/packet.php?trip=<?= $tid ?>&full=1">Leader copy</a><?php endif; ?><button class="btn btn-primary" onclick="window.print()">Print or save as PDF</button></span></div>
<article class="sheet">
  <?= logo(220, false, '#') ?>
  <h1 class="disp" style="font-size:40px;margin:24px 0 4px"><?= e($t['public_name'] ?: $t['name']) ?></h1>
  <div style="font-size:17px"><?= e(date_range($t['start_date'], $t['end_date'])) ?> · <?= e(trim($t['city'] . ', ' . $t['country'], ', ')) ?><?= $t['partner'] ? ' · with ' . e($t['partner']) : '' ?></div>
  <?= $full ? '<p class="small" style="color:var(--ember-small);font-weight:700">LEADER COPY · contains private medical and contact information</p>' : '' ?>

  <?php if ($flights): ?><h2>Flights</h2><table><thead><tr><th>Flight</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th></tr></thead><tbody>
    <?php foreach ($flights as $f): ?><tr><td><strong><?= e(flight_label($f['flight_no'])) ?></strong><div class="muted small"><?= e($f['airline']) ?></div></td><td><?= e($f['from_code']) ?></td><td><?= e($f['to_code']) ?></td><td><?= $f['departs_at'] ? fdate($f['departs_at'], 'D M j, g:i A') : 'TBD' ?></td><td><?= $f['arrives_at'] ? fdate($f['arrives_at'], 'g:i A') : '' ?><?= $f['notes'] ? '<div class="muted small">' . e($f['notes']) . '</div>' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>

  <?php if ($sec('airport')): ?><h2>Where to meet</h2><?= $sec('airport') ?><?php endif; ?>
  <?php if ($byday): ?><h2>Day by day</h2><table><tbody>
    <?php foreach ($byday as $day => $items): ?><tr><td style="width:120px"><strong><?= fdate($day, 'D, M j') ?></strong></td><td><?php foreach ($items as $i): ?><div><?= $i['time'] ? '<span class="muted">' . e($i['time']) . '</span> · ' : '' ?><?= e($i['title']) ?><?= $i['detail'] ? ' <span class="muted">(' . e($i['detail']) . ')</span>' : '' ?></div><?php endforeach; ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>

  <div class="cols">
    <?php foreach (['contacts' => 'Who to call', 'lodging' => 'Where we stay', 'packing' => 'Packing list', 'wear' => 'What to wear', 'money' => 'Money', 'weather' => 'Weather', 'power' => 'Power and plugs', 'phone' => 'Phone and Wi-Fi', 'health' => 'Health', 'entry' => 'Passport and entry', 'safety' => 'Safety plan'] as $k => $label): if (!$sec($k)) continue; ?>
      <section><h2><?= e($label) ?></h2><?= $sec($k) ?></section>
    <?php endforeach; ?>
  </div>

  <?php if ($meet): ?><h2>Before we go</h2><table><tbody><?php foreach ($meet as $m): ?><tr><td style="width:180px"><strong><?= fdate($m['starts_at'], 'D, M j · g:i A') ?></strong></td><td><?= e($m['title']) ?><?= $m['location'] ? ' <span class="muted">· ' . e($m['location']) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>

  <?php if ($full): ?>
  <h2 class="pb">Team roster</h2>
  <table><thead><tr><th>Name</th><th>Phone</th><th>Emergency contact</th><th>Passport</th><th>Room / seat</th></tr></thead><tbody>
    <?php foreach ($team as $m): ?><tr><td><strong><?= e(full_name($m)) ?></strong><div class="muted small"><?= e(ucfirst($m['role'])) ?></div></td><td><?= e($m['phone']) ?></td><td><?= e($m['ec1_name']) ?><div class="muted small"><?= e($m['ec1_phone']) ?></div></td><td><?= e(mask_passport($m['passport_number'])) ?><div class="muted small"><?= $m['passport_expires'] ? 'exp ' . fdate($m['passport_expires'], 'M Y') : 'none on file' ?></div></td><td><?= e(trim($m['room'] . ' / ' . $m['seat'], ' /')) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <h2>Medical</h2>
  <table><thead><tr><th>Name</th><th>Allergies</th><th>Medications</th><th>Diet</th><th>Other</th></tr></thead><tbody>
    <?php foreach ($team as $m): if (!$m['allergies'] && !$m['meds'] && !$m['diet'] && !$m['health']) continue; ?><tr><td><strong><?= e(full_name($m)) ?></strong></td><td><?= e($m['allergies']) ?></td><td><?= e($m['meds']) ?></td><td><?= e($m['diet']) ?></td><td><?= e($m['health']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
  <p class="muted small" style="margin-top:32px">Printed <?= date('F j, Y') ?>. The latest version is always on the trip page.</p>
</article>
</body>
</html>
