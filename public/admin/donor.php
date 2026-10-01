<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$d = isset($_GET['id']) ? donor((int)$_GET['id']) : null;
$list = $d ? gifts(['donor' => (int)$d['id']], 1000) : [];
$val = fn($k) => e($d[$k] ?? '');
$years = []; foreach ($list as $g) $years[substr($g['gift_date'], 0, 4)] = ($years[substr($g['gift_date'], 0, 4)] ?? 0) + (float)$g['amount'];
page_open($d ? donor_name($d) : 'Add a donor');
admin_header('giving');
?>
<main class="main" style="max-width:1100px">
  <div class="bar-head"><div style="display:flex;gap:14px;align-items:center"><span class="av dark lg"><?= $d ? initials(donor_name($d)) : '+' ?></span>
    <div><a class="muted small" href="/admin/giving.php?v=donors" style="text-decoration:none">‹ Donors</a><h1><?= $d ? e(donor_name($d)) : 'Add a donor' ?></h1>
    <?php if ($d): ?><div class="muted small"><?= count($list) ?> gifts · <?= money(array_sum(array_column($list, 'amount')), 2) ?> all time</div><?php endif; ?></div></div></div>
  <div class="split">
    <div style="display:flex;flex-direction:column;gap:24px;min-width:0">
      <?php if ($d): ?>
      <section class="group tbl"><table>
        <thead><tr><th>Date</th><th>For</th><th>Method</th><th class="num">Amount</th></tr></thead>
        <tbody><?php foreach ($list as $g): ?><tr><td><?= fdate($g['gift_date'], 'M j, Y') ?></td><td><?= e(gift_for($g)) ?></td><td class="muted"><?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['check_no'] ? ' #' . e($g['check_no']) : '' ?></td><td class="num"><strong><?= money((float)$g['amount'], 2) ?></strong></td></tr><?php endforeach; ?></tbody>
      </table><?= $list ? '' : '<div class="empty">No gifts yet.</div>' ?></section>
      <?php endif; ?>
      <form class="form tile xl" method="post" action="/action.php" style="padding:24px">
        <?= csrf() ?><input type="hidden" name="action" value="donor_save"><input type="hidden" name="id" value="<?= (int)($d['id'] ?? 0) ?>">
        <strong style="font-size:17px">Contact</strong>
        <div class="r3"><label class="lab">First name<input type="text" name="first_name" value="<?= $val('first_name') ?>"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= $val('last_name') ?>"></label><label class="lab">Business or church<input type="text" name="org" value="<?= $val('org') ?>"></label></div>
        <div class="r2"><label class="lab">Email<input type="email" name="email" value="<?= $val('email') ?>"></label><label class="lab">Phone<input type="tel" name="phone" value="<?= $val('phone') ?>"></label></div>
        <div class="r3"><label class="lab">Street<input type="text" name="address" value="<?= $val('address') ?>"></label><label class="lab">City<input type="text" name="city" value="<?= $val('city') ?>"></label><label class="lab">State and ZIP<span style="display:flex;gap:8px"><input type="text" name="state" value="<?= $val('state') ?>" style="width:70px"><input type="text" name="zip" value="<?= $val('zip') ?>"></span></label></div>
        <label class="lab">Notes<textarea name="notes" rows="2"><?= $val('notes') ?></textarea></label>
        <div class="actions"><button class="btn btn-primary" type="submit">Save donor</button></div>
      </form>
    </div>
    <?php if ($d): ?>
    <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
      <section><div class="gh">By year</div><div class="group">
        <?php foreach ($years as $y => $sum): ?><a class="cell" href="/admin/statement.php?year=<?= $y ?>&donor=<?= (int)$d['id'] ?>" target="_blank"><span class="grow"><?= $y ?> statement</span><strong><?= money($sum, 2) ?></strong></a><?php endforeach; ?>
        <?= $years ? '' : empty_state('No gifts yet') ?>
      </div></section>
    </aside>
    <?php endif; ?>
  </div>
</main>
<?php page_close(); ?>
