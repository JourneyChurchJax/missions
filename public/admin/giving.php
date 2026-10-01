<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$views = ['gifts' => 'Gifts', 'batches' => 'Checks and cash', 'donors' => 'Donors', 'payments' => 'Traveler payments', 'statements' => 'Statements'];
$v = array_key_exists($_GET['v'] ?? '', $views) ? $_GET['v'] : 'gifts';
$f = ['trip' => (int)($_GET['trip'] ?? 0), 'method' => array_key_exists($_GET['method'] ?? '', GIFT_METHODS) ? $_GET['method'] : '', 'year' => (int)($_GET['year'] ?? 0)];
$year = (int)date('Y');

// Spreadsheet of the gifts on screen
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gifts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Donor', 'Email', 'For', 'Method', 'Check', 'Amount', 'Fee', 'Batch', 'Anonymous', 'Note'], ',', '"', '\\');
    foreach (gifts($f, 100000) as $g) { $d = $g['donor_id'] ? donor((int)$g['donor_id']) : null; $b = $g['batch_id'] ? batch((int)$g['batch_id']) : null;
        fputcsv($out, [$g['gift_date'], donor_name($d), $d['email'] ?? '', gift_for($g), GIFT_METHODS[$g['method']] ?? '', $g['check_no'], $g['amount'], $g['fee'], $b['name'] ?? '', $g['anonymous'] ? 'Yes' : '', $g['note']], ',', '"', '\\'); }
    exit;
}

$week = (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE status = 'cleared' AND gift_date >= ?", [date('Y-m-d', strtotime('-7 days'))]);
$week_n = (int)val("SELECT COUNT(*) FROM gifts WHERE status = 'cleared' AND gift_date >= ?", [date('Y-m-d', strtotime('-7 days'))]);
$donors_n = (int)val("SELECT COUNT(DISTINCT donor_id) FROM gifts WHERE status = 'cleared' AND donor_id IS NOT NULL AND gift_date >= ?", ["$year-01-01"]);
$card = (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE status = 'cleared' AND method IN ('card','bank') AND gift_date >= ?", ["$year-01-01"]);
$fees = (float)val("SELECT COALESCE(SUM(fee),0) FROM gifts WHERE status = 'cleared' AND gift_date >= ?", ["$year-01-01"]);
$season = (float)val('SELECT COALESCE(SUM(raised),0) FROM members m JOIN trips t ON t.id = m.trip_id WHERE t.status = \'active\'') + (float)val("SELECT COALESCE(SUM(g.amount),0) FROM gifts g JOIN trips t ON t.id = g.trip_id WHERE g.person_id IS NULL AND g.status = 'cleared' AND t.status = 'active'");
$active = trips('upcoming');
$qs = fn(array $x) => '/admin/giving.php?' . http_build_query(array_filter($x + ['v' => $v] + $f));
page_open('Giving');
admin_header('giving');
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Giving</h1><div class="muted">Every gift, check and cash deposit, credited to a traveler or a team</div></div>
    <div style="display:flex;gap:12px"><a class="btn" href="<?= e($qs(['csv' => 1])) ?>">Download</a><a class="btn btn-primary" href="/admin/giving.php?v=batches">Enter checks and cash</a></div>
  </div>

  <section class="g4 lead">
    <div class="tile dark" style="padding:26px"><div class="k">Raised for upcoming trips</div><div class="disp" style="font-size:64px"><?= money($season) ?></div><div style="color:rgba(247,244,240,.85);font-size:15px"><?= e(implode(' and ', array_column($active, 'name'))) ?: 'No upcoming trips' ?> · gifts and traveler payments</div></div>
    <div class="tile"><div class="k">Last 7 days</div><div class="disp v"><?= money($week) ?></div><div class="muted small"><?= $week_n ?> gift<?= $week_n === 1 ? '' : 's' ?></div></div>
    <div class="tile"><div class="k">Donors in <?= $year ?></div><div class="disp v"><?= $donors_n ?></div><div class="muted small">gave at least once</div></div>
    <div class="tile"><div class="k">Card and bank fees</div><div class="disp v"><?= money($fees) ?></div><div class="muted small"><?= $card > 0 ? number_format($fees / $card * 100, 1) . '% of online gifts' : 'No online gifts yet' ?></div></div>
  </section>

  <nav class="seg" aria-label="View" style="align-self:flex-start"><?php foreach ($views as $k => $l): ?><a class="tab<?= $v === $k ? ' on' : '' ?>" href="/admin/giving.php?v=<?= $k ?>"><?= e($l) ?></a><?php endforeach; ?></nav>

<?php if ($v === 'gifts'): $list = gifts($f, 300); ?>
  <form class="chips" method="get" style="gap:8px;align-items:center">
    <input type="hidden" name="v" value="gifts">
    <select name="trip" class="pill" onchange="this.form.submit()"><option value="0">All trips</option><?php foreach (all('SELECT id, name, start_date FROM trips ORDER BY start_date DESC') as $t): ?><option value="<?= (int)$t['id'] ?>"<?= $f['trip'] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> <?= fdate($t['start_date'], 'Y') ?></option><?php endforeach; ?></select>
    <select name="method" class="pill" onchange="this.form.submit()"><option value="">Any method</option><?php foreach (GIFT_METHODS as $k => $l): ?><option value="<?= $k ?>"<?= $f['method'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="year" class="pill" onchange="this.form.submit()"><option value="0">Any year</option><?php for ($y = $year; $y >= $year - 3; $y--): ?><option<?= $f['year'] === $y ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
    <span class="muted small"><?= count($list) ?> gifts · <?= money(gifts_total($f), 2) ?></span>
  </form>
  <div class="split" style="grid-template-columns:minmax(0,1fr) 380px">
    <section class="group tbl"><table>
      <thead><tr><th>Date</th><th>Donor</th><th>For</th><th>Method</th><th class="num">Amount</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $g): $d = $g['donor_id'] ? donor((int)$g['donor_id']) : null; ?>
        <tr><td><?= fdate($g['gift_date'], 'M j') ?></td>
          <td class="wrap"><?php if ($d): ?><a href="/admin/donor.php?id=<?= (int)$d['id'] ?>"><strong><?= e(donor_name($d)) ?></strong></a><?php else: ?><strong>No name</strong><?php endif; ?><?= $g['anonymous'] ? '<div class="muted small">Hidden from traveler</div>' : '' ?></td>
          <td class="wrap"><?= e(gift_for($g)) ?></td>
          <td class="muted"><?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['check_no'] ? ' #' . e($g['check_no']) : '' ?></td>
          <td class="num"><strong><?= money((float)$g['amount'], 2) ?></strong><?= (float)$g['fee'] > 0 ? '<div class="muted small">fee ' . money((float)$g['fee'], 2) . '</div>' : '' ?></td>
          <td><details class="edit"><summary>Edit</summary><div style="min-width:320px;padding-top:8px"><?php $g_saved = $g; include dirname(__DIR__) . '/inc/_gift_form.php'; $g = $g_saved; ?>
            <form method="post" action="/action.php" onsubmit="return confirm('Remove this gift? The traveler\'s total goes down.')" style="text-align:right"><?= csrf() ?><input type="hidden" name="action" value="gift_delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn danger">Remove gift</button></form></div></details></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?= $list ? '' : '<div class="empty">No gifts yet.</div>' ?>
    </section>
    <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
      <details class="add" open><summary>Record a gift</summary><div class="body"><?php $g = []; include dirname(__DIR__) . '/inc/_gift_form.php'; ?></div></details>
      <section class="note"><strong>Online gifts</strong><div class="muted small">Card and bank gifts will flow in from Stripe automatically in phase 5. For now, record them here.</div></section>
    </aside>
  </div>

<?php elseif ($v === 'batches'): $bid = (int)($_GET['batch'] ?? 0); $sel = $bid ? batch($bid) : null; $batches = all('SELECT * FROM batches ORDER BY deposit_date DESC, id DESC'); ?>
  <div class="split left-rail">
    <section style="display:flex;flex-direction:column;gap:16px">
      <details class="add"<?= $batches ? '' : ' open' ?>><summary>Start a deposit batch</summary><div class="body">
        <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="batch_save">
          <label class="lab">Name<input type="text" name="name" placeholder="Sunday offering, <?= date('M j') ?>"></label><label class="lab">Bank deposit date<input type="date" name="deposit_date" value="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-dark" type="submit">Start batch</button></form></div></details>
      <div class="group">
      <?php foreach ($batches as $b): $n = (int)val('SELECT COUNT(*) FROM gifts WHERE batch_id = ?', [$b['id']]); $sum = (float)val('SELECT COALESCE(SUM(amount),0) FROM gifts WHERE batch_id = ?', [$b['id']]); ?>
        <a class="cell" href="/admin/giving.php?v=batches&batch=<?= (int)$b['id'] ?>"<?= $bid === (int)$b['id'] ? ' style="background:var(--tint)"' : '' ?>><div class="grow"><strong><?= e($b['name']) ?></strong><div class="muted small"><?= fdate($b['deposit_date'], 'M j, Y') ?> · <?= $n ?> gifts · <?= money($sum, 2) ?></div></div><span class="pill<?= $b['status'] === 'closed' ? ' pill-ok' : '' ?>"><?= $b['status'] === 'closed' ? 'Closed' : 'Open' ?></span></a>
      <?php endforeach; ?>
      <?= $batches ? '' : '<div class="empty">No batches yet.</div>' ?>
      </div>
    </section>
    <?php if ($sel): $bg = gifts(['batch' => $bid], 1000); $by = []; foreach ($bg as $g) $by[$g['method']] = ($by[$g['method']] ?? 0) + (float)$g['amount']; ?>
    <section class="tile xl" style="gap:18px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div><strong style="font-size:22px"><?= e($sel['name']) ?></strong><div class="muted small">Deposit <?= fdate($sel['deposit_date'], 'F j, Y') ?> · started by <?= e($sel['created_by']) ?></div></div>
        <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="batch_close"><input type="hidden" name="id" value="<?= $bid ?>"><button class="btn<?= $sel['status'] === 'open' ? ' btn-dark' : '' ?>" type="submit"><?= $sel['status'] === 'open' ? 'Close batch' : 'Reopen' ?></button></form>
      </div>
      <div class="g4" style="gap:12px">
        <div class="tile" style="background:var(--cream);box-shadow:none"><div class="k">Total</div><div class="disp v"><?= money(array_sum($by), 2) ?></div><div class="muted small"><?= count($bg) ?> gifts</div></div>
        <?php foreach (['check' => 'Checks', 'cash' => 'Cash'] as $k => $l): ?><div class="tile" style="background:var(--cream);box-shadow:none"><div class="k"><?= $l ?></div><div class="disp v"><?= money($by[$k] ?? 0, 2) ?></div></div><?php endforeach; ?>
        <div class="tile" style="background:var(--cream);box-shadow:none"><div class="k">Other</div><div class="disp v"><?= money(array_sum($by) - ($by['check'] ?? 0) - ($by['cash'] ?? 0), 2) ?></div></div>
      </div>
      <div class="group" style="box-shadow:none;background:var(--cream)">
      <?php foreach ($bg as $g): ?><div class="cell"><div class="grow"><strong><?= e(donor_name($g['donor_id'] ? donor((int)$g['donor_id']) : null)) ?></strong><div class="muted small"><?= e(gift_for($g)) ?> · <?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['check_no'] ? ' #' . e($g['check_no']) : '' ?></div></div><strong><?= money((float)$g['amount'], 2) ?></strong></div><?php endforeach; ?>
      <?= $bg ? '' : '<div class="empty">No gifts in this batch yet.</div>' ?>
      </div>
      <?php if ($sel['status'] === 'open'): ?><details class="add" open><summary>Add a check or cash gift</summary><div class="body"><?php $g = []; $batch_preset = $bid; include dirname(__DIR__) . '/inc/_gift_form.php'; ?></div></details>
      <?php else: ?><div class="muted small">Closed <?= fdate($sel['closed_at'], 'M j, g:i A') ?>. Reopen it to add or fix a gift.</div><?php endif; ?>
    </section>
    <?php else: ?><section class="tile xl"><strong style="font-size:18px">How batches work</strong><div class="muted">Start a batch for each bank deposit. Enter every check and cash gift, credit it to a traveler or team, then close the batch when the total matches your deposit slip.</div></section><?php endif; ?>
  </div>

<?php elseif ($v === 'donors'): $q = trim((string)($_GET['q'] ?? ''));
  $rows = all("SELECT d.*, COUNT(g.id) AS n, COALESCE(SUM(CASE WHEN g.gift_date >= ? THEN g.amount END),0) AS this_year, COALESCE(SUM(g.amount),0) AS total, MAX(g.gift_date) AS last_gift
               FROM donors d LEFT JOIN gifts g ON g.donor_id = d.id AND g.status = 'cleared'" . ($q !== '' ? " WHERE d.first_name LIKE ? OR d.last_name LIKE ? OR d.org LIKE ? OR d.email LIKE ?" : '') . " GROUP BY d.id ORDER BY total DESC",
               $q !== '' ? ["$year-01-01", "%$q%", "%$q%", "%$q%", "%$q%"] : ["$year-01-01"]); ?>
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <form method="get" class="chips" style="gap:8px"><input type="hidden" name="v" value="donors"><input class="pill" style="min-width:260px" type="search" name="q" value="<?= e($q) ?>" placeholder="Find a donor"><button class="btn" type="submit" style="height:36px">Search</button></form>
    <a class="btn" href="/admin/donor.php">Add a donor</a>
  </div>
  <section class="group tbl"><table>
    <thead><tr><th>Donor</th><th>Email</th><th>Gifts</th><th>Last gift</th><th class="num"><?= $year ?></th><th class="num">All time</th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?><tr><td><a href="/admin/donor.php?id=<?= (int)$d['id'] ?>"><strong><?= e(donor_name($d)) ?></strong></a></td><td class="muted"><?= e($d['email']) ?></td><td><?= (int)$d['n'] ?></td><td class="muted"><?= fdate($d['last_gift'], 'M j, Y') ?></td><td class="num"><?= money((float)$d['this_year'], 2) ?></td><td class="num"><strong><?= money((float)$d['total'], 2) ?></strong></td></tr><?php endforeach; ?></tbody>
  </table><?= $rows ? '' : '<div class="empty">No donors yet. They are added when you record a gift.</div>' ?></section>

<?php elseif ($v === 'payments'): $rows = all('SELECT y.*, p.first_name, p.preferred_name, p.last_name, t.name AS trip_name FROM payments y JOIN people p ON p.id = y.person_id JOIN trips t ON t.id = y.trip_id ORDER BY y.paid_on DESC, y.id DESC LIMIT 300'); ?>
  <div class="split" style="grid-template-columns:minmax(0,1fr) 380px">
    <section class="group tbl"><table>
      <thead><tr><th>Date</th><th>Traveler</th><th>Trip</th><th>Type</th><th>Method</th><th class="num">Amount</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $y): ?><tr><td><?= fdate($y['paid_on'], 'M j') ?></td><td><strong><?= e(full_name($y)) ?></strong><?= $y['note'] ? '<div class="muted small">' . e($y['note']) . '</div>' : '' ?></td><td><?= e($y['trip_name']) ?></td><td><?= e(PAYMENT_KINDS[$y['kind']] ?? '') ?></td><td class="muted"><?= e(GIFT_METHODS[$y['method']] ?? '') ?></td>
        <td class="num"><strong><?= $y['kind'] === 'refund' ? '−' : '' ?><?= money((float)$y['amount'], 2) ?></strong></td>
        <td><form method="post" action="/action.php" onsubmit="return confirm('Remove this payment?')"><?= csrf() ?><input type="hidden" name="action" value="payment_delete"><input type="hidden" name="id" value="<?= (int)$y['id'] ?>"><button class="link-btn danger">Remove</button></form></td></tr><?php endforeach; ?></tbody>
    </table><?= $rows ? '' : '<div class="empty">No traveler payments yet.</div>' ?></section>
    <aside class="sticky"><details class="add" open><summary>Record a traveler payment</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="payment_save">
        <label class="lab">Traveler<select name="for" required><option value="">Choose</option><?php foreach ($active as $t): ?><optgroup label="<?= e($t['name']) ?>"><?php foreach (travelers((int)$t['id']) as $m): ?><option value="m<?= (int)$t['id'] ?>-<?= (int)$m['person_id'] ?>"><?= e(full_name($m)) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
        <div class="r2"><label class="lab">Amount<input type="number" step="0.01" min="0.01" name="amount" required></label><label class="lab">Date<input type="date" name="paid_on" value="<?= date('Y-m-d') ?>"></label></div>
        <div class="r2"><label class="lab">Type<select name="kind"><?php foreach (PAYMENT_KINDS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">Method<select name="method"><?php foreach (GIFT_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label></div>
        <label class="lab">Note<input type="text" name="note"></label>
        <button class="btn btn-dark" type="submit">Record payment</button>
        <div class="muted small">Money the traveler pays toward their own trip. It counts toward their total, but isn't a tax-deductible gift.</div>
      </form></div></details></aside>
  </div>

<?php else: $sy = (int)($_GET['year'] ?? ((int)date('n') <= 3 ? $year - 1 : $year));
  $rows = all("SELECT d.*, COUNT(g.id) AS n, SUM(g.amount) AS total FROM donors d JOIN gifts g ON g.donor_id = d.id WHERE g.status = 'cleared' AND g.gift_date BETWEEN ? AND ? GROUP BY d.id ORDER BY d.last_name, d.first_name", ["$sy-01-01", "$sy-12-31"]);
  $no_email = count(array_filter($rows, fn($d) => !$d['email'])); ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <form method="get" class="chips" style="gap:8px;align-items:center"><input type="hidden" name="v" value="statements"><select class="pill" name="year" onchange="this.form.submit()"><?php for ($y = $year; $y >= $year - 4; $y--): ?><option<?= $sy === $y ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select><span class="muted small"><?= count($rows) ?> donors · <?= money(array_sum(array_column($rows, 'total')), 2) ?></span></form>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="/admin/statement.php?year=<?= $sy ?>" target="_blank">Print all</a>
      <form method="post" action="/action.php" onsubmit="return confirm('Email <?= count($rows) - $no_email ?> statements now?')"><?= csrf() ?><input type="hidden" name="action" value="statements_email"><input type="hidden" name="year" value="<?= $sy ?>"><button class="btn btn-primary" type="submit">Email statements</button></form>
    </div>
  </div>
  <?php if ($no_email): ?><div class="note"><strong><?= $no_email ?> donor<?= $no_email === 1 ? ' has' : 's have' ?> no email</strong><div class="muted small">Print theirs and mail it. "Print all" includes everyone.</div></div><?php endif; ?>
  <section class="group tbl"><table>
    <thead><tr><th>Donor</th><th>Email</th><th>Gifts</th><th class="num">Total</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?><tr><td><strong><?= e(donor_name($d)) ?></strong></td><td class="muted"><?= e($d['email'] ?: 'No email: print') ?></td><td><?= (int)$d['n'] ?></td><td class="num"><strong><?= money((float)$d['total'], 2) ?></strong></td><td><a href="/admin/statement.php?year=<?= $sy ?>&donor=<?= (int)$d['id'] ?>" target="_blank">View</a></td></tr><?php endforeach; ?></tbody>
  </table><?= $rows ? '' : '<div class="empty">No gifts with a donor name in ' . $sy . '.</div>' ?></section>
  <div class="muted small">Statements list gifts only. Traveler payments toward their own trip aren't tax-deductible, so they're left out.</div>
<?php endif; ?>
</main>
<?php page_close(); ?>
