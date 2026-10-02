<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$views = ['gifts' => 'Gifts', 'batches' => 'Checks and cash', 'donors' => 'Donors', 'payments' => 'Traveler payments', 'statements' => 'Statements'];
$v = isset($views[g('v')]) ? g('v') : 'gifts';
$f = ['trip' => gi('trip'), 'method' => isset(GIFT_METHODS[g('method')]) ? g('method') : '', 'year' => gi('year'), 'include_void' => g('void') !== ''];
$year = (int)date('Y');
$page = max(1, gi('page')); $per = 50;

// Spreadsheet of the gifts on screen
if (g('csv') !== '') {
    audit('gifts_download', 'gifts', null, $f['trip'] ?: null);
    $out = csv_start('gifts-' . date('Y-m-d') . '.csv');
    csv_out($out, ['Date', 'Donor', 'Email', 'For', 'Method', 'Check', 'Amount', 'Refunded', 'Fee', 'Fee covered by donor', 'Batch', 'Status', 'Anonymous', 'Note']);
    foreach (gifts($f, 100000) as $g) { $d = $g['donor_id'] ? donor((int)$g['donor_id']) : null; $b = $g['batch_id'] ? batch((int)$g['batch_id']) : null;
        csv_out($out, [$g['gift_date'], donor_name($d), $d['email'] ?? '', gift_for($g), GIFT_METHODS[$g['method']] ?? '', $g['check_no'], $g['amount'], $g['refunded'], $g['fee'], $g['covered_fee'], $b['name'] ?? '', $g['status'], $g['anonymous'] ? 'Yes' : '', $g['note']]); }
    exit;
}

$counts = "status IN ('cleared','refunded','disputed')";
$week = (float)val("SELECT COALESCE(SUM(amount - COALESCE(refunded,0)),0) FROM gifts WHERE $counts AND gift_date >= ?", [date('Y-m-d', strtotime('-7 days'))]);
$week_n = (int)val("SELECT COUNT(*) FROM gifts WHERE $counts AND gift_date >= ?", [date('Y-m-d', strtotime('-7 days'))]);
$donors_n = (int)val("SELECT COUNT(DISTINCT donor_id) FROM gifts WHERE $counts AND donor_id IS NOT NULL AND gift_date >= ?", ["$year-01-01"]);
$card = (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE $counts AND method IN ('card','bank') AND gift_date >= ?", ["$year-01-01"]);
$fees = (float)val("SELECT COALESCE(SUM(fee),0) FROM gifts WHERE $counts AND gift_date >= ?", ["$year-01-01"]);
$season = season_raised();
$active = trips('upcoming');
$qs = fn(array $x) => '/admin/giving.php?' . http_build_query(array_filter($x + ['v' => $v, 'trip' => $f['trip'], 'method' => $f['method'], 'year' => $f['year'], 'void' => $f['include_void'] ? 1 : 0]));
page_open('Giving');
admin_header('giving');
?>
<main class="main" id="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Giving</h1><div class="muted">Every gift, check and cash deposit, credited to a traveler or a team</div></div>
    <div class="row gap-12"><a class="btn" href="<?= e($qs(['csv' => 1])) ?>">Download spreadsheet</a><a class="btn btn-primary" href="/admin/giving.php?v=batches">Enter checks and cash</a></div>
  </div>

  <section class="g4 lead">
    <div class="tile dark" style="padding:26px"><div class="k">Raised for upcoming trips</div><div class="disp" style="font-size:64px"><?= money($season) ?></div><div style="color:rgba(247,244,240,.85);font-size:15px"><?= e(implode(' and ', array_column($active, 'name'))) ?: 'No upcoming trips' ?> · gifts and traveler payments</div></div>
    <div class="tile"><div class="k">Last 7 days</div><div class="disp v"><?= money($week) ?></div><div class="muted small"><?= $week_n ?> gift<?= $week_n === 1 ? '' : 's' ?></div></div>
    <div class="tile"><div class="k">Donors in <?= $year ?></div><div class="disp v"><?= $donors_n ?></div><div class="muted small">gave at least once</div></div>
    <div class="tile"><div class="k">Card and bank fees</div><div class="disp v"><?= money($fees) ?></div><div class="muted small"><?= $card > 0 ? number_format($fees / $card * 100, 1) . '% of online gifts' : 'No online gifts yet' ?></div></div>
  </section>

  <nav class="seg" aria-label="View" style="align-self:flex-start"><?php foreach ($views as $k => $l): ?><a class="tab<?= $v === $k ? ' on' : '' ?>" href="/admin/giving.php?v=<?= $k ?>"><?= e($l) ?></a><?php endforeach; ?></nav>

<?php if ($v === 'gifts'): $total_n = gifts_count($f); $list = gifts($f, $per, ($page - 1) * $per); $pages = max(1, (int)ceil($total_n / $per)); ?>
  <form class="chips gap-8" method="get">
    <input type="hidden" name="v" value="gifts">
    <label class="sr" for="ft">Trip</label><select id="ft" name="trip" class="pill-input" onchange="this.form.submit()"><option value="0">All trips</option><?php foreach (all('SELECT id, name, start_date FROM trips ORDER BY start_date DESC') as $t): ?><option value="<?= (int)$t['id'] ?>"<?= $f['trip'] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> <?= fdate($t['start_date'], 'Y') ?></option><?php endforeach; ?></select>
    <label class="sr" for="fm">Method</label><select id="fm" name="method" class="pill-input" onchange="this.form.submit()"><option value="">Any method</option><?php foreach (GIFT_METHODS as $k => $l): ?><option value="<?= $k ?>"<?= $f['method'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <label class="sr" for="fy">Year</label><select id="fy" name="year" class="pill-input" onchange="this.form.submit()"><option value="0">Any year</option><?php for ($y = $year; $y >= $year - 3; $y--): ?><option<?= $f['year'] === $y ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
    <label class="chk small"><input type="checkbox" name="void" value="1"<?= $f['include_void'] ? ' checked' : '' ?> onchange="this.form.submit()"> Show voided</label>
    <span class="muted small"><?= $total_n ?> gifts · <?= money(gifts_total($f), 2) ?></span>
  </form>
  <div class="split side-380">
    <section class="group tbl"><table>
      <thead><tr><th>Date</th><th>Donor</th><th>For</th><th>Method</th><th class="num">Amount</th><th><span class="sr">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($list as $g): $d = $g['donor_id'] ? donor((int)$g['donor_id']) : null; ?>
        <tr<?= $g['status'] === 'void' ? ' class="voided"' : '' ?>><td><?= fdate($g['gift_date'], 'M j, Y') ?></td>
          <td class="wrap"><?php if ($d): ?><a href="/admin/donor.php?id=<?= (int)$d['id'] ?>"><strong><?= e(donor_name($d)) ?></strong></a><?php else: ?><strong>No name</strong><?php endif; ?><?= $g['anonymous'] ? '<div class="muted small">Hidden from traveler</div>' : '' ?></td>
          <td class="wrap"><?= e(gift_for($g)) ?><?= $g['status'] !== 'cleared' ? ' <span class="pill tag">' . e(ucfirst($g['status'])) . '</span>' : '' ?></td>
          <td class="muted"><?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['check_no'] ? ' #' . e($g['check_no']) : '' ?></td>
          <td class="num"><strong><?= money((float)$g['amount'], 2) ?></strong><?= (float)$g['refunded'] > 0 ? '<div class="muted small">refunded ' . money((float)$g['refunded'], 2) . '</div>' : '' ?><?= (float)$g['fee'] > 0 ? '<div class="muted small">fee ' . money((float)$g['fee'], 2) . '</div>' : '' ?></td>
          <td><a class="small" href="/admin/gift.php?id=<?= (int)$g['id'] ?>">Open</a></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?= $list ? '' : '<div class="empty">No gifts yet.</div>' ?>
      <?php if ($pages > 1): ?><nav class="pager" aria-label="Pages"><?php if ($page > 1): ?><a href="<?= e($qs(['page' => $page - 1])) ?>">‹ Newer</a><?php endif; ?><span class="muted small">Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="<?= e($qs(['page' => $page + 1])) ?>">Older ›</a><?php endif; ?></nav><?php endif; ?>
    </section>
    <aside class="sticky stack-20">
      <details class="add" open><summary>Record a gift</summary><div class="body"><?php $g = []; include dirname(__DIR__) . '/inc/_gift_form.php'; ?></div></details>
      <section class="note"><strong>Online gifts</strong><div class="muted small"><?= stripe_ready() ? 'Card and bank gifts arrive from Stripe by themselves. Refunds in Stripe show up here too.' : 'Once Stripe is connected, card and bank gifts arrive here by themselves.' ?></div></section>
    </aside>
  </div>

<?php elseif ($v === 'batches'): $bid = gi('batch'); $sel = $bid ? batch($bid) : null; $batches = all('SELECT * FROM batches ORDER BY deposit_date DESC, id DESC'); ?>
  <div class="split left-rail">
    <section style="display:flex;flex-direction:column;gap:16px">
      <details class="add"<?= $batches ? '' : ' open' ?>><summary>Start a deposit batch</summary><div class="body">
        <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="batch_save">
          <label class="lab">Name<input type="text" name="name" placeholder="Sunday offering, <?= date('M j') ?>"></label><label class="lab">Bank deposit date<input type="date" name="deposit_date" value="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-dark" type="submit">Start batch</button></form></div></details>
      <div class="group">
      <?php foreach ($batches as $b): $n = (int)val("SELECT COUNT(*) FROM gifts WHERE batch_id = ? AND status <> 'void'", [$b['id']]); $sum = (float)val("SELECT COALESCE(SUM(amount - COALESCE(refunded,0)),0) FROM gifts WHERE batch_id = ? AND status <> 'void'", [$b['id']]); ?>
        <a class="cell" href="/admin/giving.php?v=batches&batch=<?= (int)$b['id'] ?>"<?= $bid === (int)$b['id'] ? ' style="background:var(--tint)"' : '' ?>><div class="grow"><strong><?= e($b['name']) ?></strong><div class="muted small"><?= fdate($b['deposit_date'], 'M j, Y') ?> · <?= $n ?> gifts · <?= money($sum, 2) ?></div></div><span class="pill<?= $b['status'] === 'closed' ? ' pill-ok' : '' ?>"><?= $b['status'] === 'closed' ? 'Closed' : 'Open' ?></span></a>
      <?php endforeach; ?>
      <?= $batches ? '' : '<div class="empty">No batches yet.</div>' ?>
      </div>
    </section>
    <?php if ($sel): $bg = gifts(['batch' => $bid], 1000); $by = []; foreach ($bg as $g) $by[$g['method']] = ($by[$g['method']] ?? 0) + (float)$g['amount']; ?>
    <section class="tile xl" style="gap:18px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div><strong style="font-size:22px"><?= e($sel['name']) ?></strong><div class="muted small">Deposit <?= fdate($sel['deposit_date'], 'F j, Y') ?> · started by <?= e($sel['created_by']) ?></div></div>
        <?php if ($sel['status'] === 'open'): ?><form method="post" action="/action.php" data-confirm="Close this batch? Its gifts get locked to match the bank deposit."><?= csrf() ?><input type="hidden" name="action" value="batch_close"><input type="hidden" name="id" value="<?= $bid ?>"><button class="btn btn-dark" type="submit">Close batch</button></form>
        <?php else: ?><details class="edit"><summary>Reopen</summary><form class="form" method="post" action="/action.php" style="padding-top:8px;min-width:240px"><?= csrf() ?><input type="hidden" name="action" value="batch_close"><input type="hidden" name="id" value="<?= $bid ?>"><label class="lab">Why reopen it?<input type="text" name="reason" required></label><button class="btn btn-sm" type="submit">Reopen batch</button></form></details><?php endif; ?>
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

<?php elseif ($v === 'donors'): $q = g('q');
  $rows = all("SELECT d.*, COUNT(g.id) AS n, COALESCE(SUM(CASE WHEN g.gift_date >= ? THEN g.amount END),0) AS this_year, COALESCE(SUM(g.amount),0) AS total, MAX(g.gift_date) AS last_gift
               FROM donors d LEFT JOIN gifts g ON g.donor_id = d.id AND g.status = 'cleared'" . ($q !== '' ? " WHERE d.first_name LIKE ? OR d.last_name LIKE ? OR d.org LIKE ? OR d.email LIKE ?" : '') . " GROUP BY d.id ORDER BY total DESC",
               $q !== '' ? ["$year-01-01", "%$q%", "%$q%", "%$q%", "%$q%"] : ["$year-01-01"]); ?>
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <form method="get" class="chips" style="gap:8px"><input type="hidden" name="v" value="donors"><label class="sr" for="dq">Find a donor</label><input id="dq" class="pill-input" style="min-width:260px" type="search" name="q" value="<?= e($q) ?>" placeholder="Find a donor"><button class="btn" type="submit" style="height:36px">Search</button></form>
    <a class="btn" href="/admin/donor.php">Add a donor</a>
  </div>
  <section class="group tbl"><table>
    <thead><tr><th>Donor</th><th>Email</th><th>Gifts</th><th>Last gift</th><th class="num"><?= $year ?></th><th class="num">All time</th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?><tr><td><a href="/admin/donor.php?id=<?= (int)$d['id'] ?>"><strong><?= e(donor_name($d)) ?></strong></a></td><td class="muted"><?= e($d['email']) ?></td><td><?= (int)$d['n'] ?></td><td class="muted"><?= fdate($d['last_gift'], 'M j, Y') ?></td><td class="num"><?= money((float)$d['this_year'], 2) ?></td><td class="num"><strong><?= money((float)$d['total'], 2) ?></strong></td></tr><?php endforeach; ?></tbody>
  </table><?= $rows ? '' : '<div class="empty">No donors yet. They are added when you record a gift.</div>' ?></section>

<?php elseif ($v === 'payments'): $rows = all('SELECT y.*, p.first_name, p.preferred_name, p.last_name, t.name AS trip_name FROM payments y JOIN people p ON p.id = y.person_id JOIN trips t ON t.id = y.trip_id ORDER BY y.paid_on DESC, y.id DESC LIMIT 300'); ?>
  <div class="split side-380">
    <section class="group tbl"><table>
      <thead><tr><th>Date</th><th>Traveler</th><th>Trip</th><th>Type</th><th>Method</th><th class="num">Amount</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $y): ?><tr<?= ($y['status'] ?? 'ok') === 'void' ? ' class="voided"' : '' ?>><td><?= fdate($y['paid_on'], 'M j, Y') ?></td><td><strong><?= e(full_name($y)) ?></strong><?= $y['note'] ? '<div class="muted small">' . e($y['note']) . '</div>' : '' ?></td><td><?= e($y['trip_name']) ?></td><td><?= e(PAYMENT_KINDS[$y['kind']] ?? '') ?></td><td class="muted"><?= e(GIFT_METHODS[$y['method']] ?? '') ?></td>
        <td class="num"><strong><?= $y['kind'] === 'refund' ? '−' : '' ?><?= money((float)$y['amount'], 2) ?></strong></td>
        <td><?php if (($y['status'] ?? 'ok') === 'void'): ?><span class="muted small" title="<?= e($y['void_reason']) ?>">Voided</span><?php elseif (!$y['stripe_id']): ?><details class="edit"><summary>Void</summary><form class="form" method="post" action="/action.php" style="padding-top:8px;min-width:220px"><?= csrf() ?><input type="hidden" name="action" value="payment_void"><input type="hidden" name="id" value="<?= (int)$y['id'] ?>"><label class="lab">Why?<input type="text" name="reason" required placeholder="Entered twice"></label><button class="btn btn-sm">Void payment</button></form></details><?php else: ?><span class="muted small">Online</span><?php endif; ?></td></tr><?php endforeach; ?></tbody>
    </table><?= $rows ? '' : '<div class="empty">No traveler payments yet.</div>' ?></section>
    <aside class="sticky"><details class="add" open><summary>Record a traveler payment</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="payment_save">
        <label class="lab">Traveler<select name="for" required><option value="">Choose</option><?php foreach ($active as $t): ?><optgroup label="<?= e($t['name']) ?>"><?php foreach (travelers((int)$t['id']) as $m): ?><option value="m<?= (int)$t['id'] ?>-<?= (int)$m['person_id'] ?>"><?= e(full_name($m)) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
        <div class="r2"><label class="lab">Amount<input type="number" step="0.01" min="0.01" name="amount" required></label><label class="lab">Date<input type="date" name="paid_on" value="<?= date('Y-m-d') ?>"></label></div>
        <div class="r2"><label class="lab">Type<select name="kind"><?php foreach (PAYMENT_KINDS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">Method<select name="method"><?php foreach (GIFT_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label></div>
        <label class="lab">Note<input type="text" name="note"></label>
        <?= once() ?><button class="btn btn-dark" type="submit">Record payment</button>
        <div class="muted small">Money the traveler pays toward their own trip. It counts toward their total, but isn't a tax-deductible gift.</div>
      </form></div></details></aside>
  </div>

<?php else: $sy = gi('year') ?: ((int)date('n') <= 3 ? $year - 1 : $year); $locked = in_array($sy, locked_years(), true);
  $rows = all("SELECT d.*, COUNT(g.id) AS n, SUM(g.amount - COALESCE(g.refunded,0)) AS total FROM donors d JOIN gifts g ON g.donor_id = d.id WHERE g.status IN ('cleared','refunded','disputed') AND g.gift_date BETWEEN ? AND ? GROUP BY d.id ORDER BY d.last_name, d.first_name", ["$sy-01-01", "$sy-12-31"]);
  $no_email = count(array_filter($rows, fn($d) => !$d['email'])); ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <form method="get" class="chips" style="gap:8px;align-items:center"><input type="hidden" name="v" value="statements"><label class="sr" for="sy">Year</label><select id="sy" class="pill-input" name="year" onchange="this.form.submit()"><?php for ($y = $year; $y >= $year - 4; $y--): ?><option<?= $sy === $y ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select><span class="muted small"><?= count($rows) ?> donors · <?= money(array_sum(array_column($rows, 'total')), 2) ?></span></form>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="/admin/statement.php?year=<?= $sy ?>" target="_blank">Print all</a>
      <form method="post" action="/action.php" data-confirm="Email <?= count($rows) - $no_email ?> statements for <?= $sy ?>? After this, <?= $sy ?> gifts are locked so statements can't change."><?= csrf() ?><?= once() ?><input type="hidden" name="action" value="statements_email"><input type="hidden" name="year" value="<?= $sy ?>"><button class="btn btn-primary" type="submit"><?= $locked ? 'Email statements again' : 'Email statements' ?></button></form>
    </div>
  </div>
  <?php if (!church_legal()['address']): ?><div class="note error"><strong>Statements need the church's mailing address</strong><div class="small">Ask whoever manages the server to add church_address to config.php before sending statements.</div></div><?php endif; ?>
  <?php if ($locked): ?><div class="note"><strong><?= $sy ?> is locked</strong><div class="muted small">Statements went out, so these gifts can't change. Unlock it in Settings → Privacy and backups if you must fix something.</div></div><?php endif; ?>
  <?php if ($no_email): ?><div class="note"><strong><?= $no_email ?> donor<?= $no_email === 1 ? ' has' : 's have' ?> no email</strong><div class="muted small">Print theirs and mail it. "Print all" includes everyone.</div></div><?php endif; ?>
  <section class="group tbl"><table>
    <thead><tr><th>Donor</th><th>Email</th><th>Gifts</th><th class="num">Total</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?><tr><td><strong><?= e(donor_name($d)) ?></strong></td><td class="muted"><?= e($d['email'] ?: 'No email: print') ?></td><td><?= (int)$d['n'] ?></td><td class="num"><strong><?= money((float)$d['total'], 2) ?></strong></td><td><a href="/admin/statement.php?year=<?= $sy ?>&donor=<?= (int)$d['id'] ?>" target="_blank">View</a></td></tr><?php endforeach; ?></tbody>
  </table><?= $rows ? '' : '<div class="empty">No gifts with a donor name in ' . $sy . '.</div>' ?></section>
  <div class="muted small">Statements list gifts only. Traveler payments toward their own trip aren't tax-deductible, so they're left out.</div>
<?php endif; ?>
</main>
<?php page_close(); ?>
