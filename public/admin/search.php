<?php
// One search box for trips, people and donors
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$q = g('q');
// Every word must match somewhere (so "maya bennett" finds Maya Bennett). Works on SQLite and MySQL.
$words = array_slice(array_filter(preg_split('/\s+/', $q)), 0, 5);
$find = function (string $table, array $cols, string $order, int $limit) use ($words) {
    if (!$words) return [];
    $where = []; $p = [];
    foreach ($words as $w) { $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols)) . ')'; foreach ($cols as $c) $p[] = '%' . $w . '%'; }
    return all("SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY $order LIMIT $limit", $p);
};
$tripsR = $find('trips', ['name', 'public_name', 'country', 'city'], 'start_date DESC', 20);
$peopleR = $find('people', ['first_name', 'last_name', 'preferred_name', 'email', 'phone'], 'last_name, first_name', 40);
$donorsR = $find('donors', ['first_name', 'last_name', 'org', 'email'], 'last_name', 40);
page_open($q !== '' ? 'Search: ' . $q : 'Search');
admin_header('');
?>
<main class="main narrow" id="main">
  <h1 class="page-title"><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'Search' ?></h1>
  <?php if ($q === ''): ?><div class="muted">Type a name, email, phone number or trip in the search box above.</div><?php endif; ?>
  <?php foreach ([['Trips', $tripsR, fn($t) => ['/admin/trip.php?id=' . $t['id'], $t['name'], date_range($t['start_date'], $t['end_date']) . ' · ' . ucfirst($t['status'])]],
                  ['People', $peopleR, fn($p) => ['/admin/person.php?id=' . $p['id'], full_name($p), trim(($p['email'] ?? '') . ' ' . ($p['phone'] ?? ''))]],
                  ['Donors', $donorsR, fn($d) => ['/admin/donor.php?id=' . $d['id'], donor_name($d), (string)$d['email']]]] as [$label, $rows, $fmt]): if (!$rows) continue; ?>
    <section><h2 class="gh"><?= $label ?></h2><div class="group"><?php foreach ($rows as $r): [$href, $title, $sub] = $fmt($r); ?><a class="cell" href="<?= e($href) ?>"><div class="grow"><strong><?= e($title) ?></strong><div class="muted small"><?= e($sub) ?></div></div><span class="chev" aria-hidden="true">›</span></a><?php endforeach; ?></div></section>
  <?php endforeach; ?>
  <?php if ($q !== '' && !$tripsR && !$peopleR && !$donorsR): ?><div class="empty">Nothing found. Try part of a name or an email.</div><?php endif; ?>
</main>
<?php page_close(); ?>
