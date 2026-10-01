<?php
// Trip workspace · Budget → What we spent. Expects $id, $t, $rows (budget lines), $n (travelers), $total (budget).
$ex = all('SELECT * FROM expenses WHERE trip_id = ? ORDER BY spent_on DESC, id DESC', [$id]);
$spent = trip_spent($id);
$raised = trip_raised($id);
$planned = []; foreach ($rows as $b) $planned[$b['type']] = ($planned[$b['type']] ?? 0) + (float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']);
$actual = []; foreach ($ex as $x) $actual[$x['type']] = ($actual[$x['type']] ?? 0) + (float)$x['usd'];
$owed = array_sum(array_map(fn($x) => $x['reimburse'] && !$x['reimbursed_at'] ? (float)$x['usd'] : 0, $ex));
?>
<section class="g4">
  <div class="tile dark" style="padding:24px"><div class="k">Spent</div><div class="disp" style="font-size:48px"><?= money($spent) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money($total) ?> budgeted</div></div>
  <div class="tile"><div class="k">Left in the budget</div><div class="disp v"><?= money($total - $spent) ?></div></div>
  <div class="tile"><div class="k">Raised minus spent</div><div class="disp v"><?= money($raised - $spent) ?></div><div class="muted small">cash on hand for this trip</div></div>
  <div class="tile"><div class="k">To pay back</div><div class="disp v"><?= money($owed) ?></div><div class="muted small">people who paid out of pocket</div></div>
</section>
<div class="split">
  <div style="display:flex;flex-direction:column;gap:24px;min-width:0">
    <section><div class="gh">Budget vs. actual</div><div class="group tbl"><table>
      <thead><tr><th>Type</th><th class="num">Planned</th><th class="num">Spent</th><th style="min-width:160px"></th></tr></thead>
      <tbody><?php foreach (array_unique(array_merge(array_keys($planned), array_keys($actual))) as $type): $pl = $planned[$type] ?? 0; $ac = $actual[$type] ?? 0; ?>
        <tr><td><strong><?= e($type) ?></strong></td><td class="num"><?= money($pl) ?></td><td class="num"><?= money($ac) ?><?= $pl > 0 && $ac > $pl ? ' <span class="pill" style="height:22px">Over</span>' : '' ?></td><td><?= bar(min(100, pct($ac, $pl ?: max($ac, 1)))) ?></td></tr>
      <?php endforeach; ?></tbody></table></div></section>
    <section><div class="gh">Expenses</div><div class="group">
    <?php foreach ($ex as $x): ?>
      <div class="cell" style="flex-wrap:wrap">
        <div class="grow"><strong><?= e($x['description']) ?></strong><div class="muted small"><?= e($x['type']) ?><?= $x['vendor'] ? ' · ' . e($x['vendor']) : '' ?> · <?= fdate($x['spent_on'], 'M j') ?><?= $x['paid_by'] ? ' · paid by ' . e($x['paid_by']) : '' ?><?= $x['currency'] !== 'USD' ? ' · ' . number_format((float)$x['amount'], 2) . ' ' . e($x['currency']) : '' ?>
          <?php if ($x['reimburse']): ?> · <?= $x['reimbursed_at'] ? 'paid back' : '<strong>needs paying back</strong>' ?><?php endif; ?><?php if ($x['receipt_file_id']): ?> · <a href="/file.php?id=<?= (int)$x['receipt_file_id'] ?>">Receipt</a><?php endif; ?></div></div>
        <strong><?= money((float)$x['usd'], 2) ?></strong>
        <details class="edit"><summary>More</summary><div style="padding-top:8px;display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap">
          <?php if ($x['reimburse']): ?><form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="expense_reimbursed"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="link-btn"><?= $x['reimbursed_at'] ? 'Mark not paid back' : 'Mark paid back' ?></button></form><?php endif; ?>
          <form method="post" action="/action.php" onsubmit="return confirm('Remove this expense?')"><?= csrf() ?><input type="hidden" name="action" value="expense_delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="link-btn danger">Remove</button></form>
        </div></details>
      </div>
    <?php endforeach; ?>
    <?= $ex ? '' : '<div class="empty">Nothing logged yet.</div>' ?>
    </div></section>
  </div>
  <aside class="sticky"><details class="add" open><summary>Log an expense</summary><div class="body">
    <form class="form" method="post" action="/action.php" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="action" value="expense_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
      <label class="lab">What<input type="text" name="description" required placeholder="Team dinner in Belize City"></label>
      <div class="r2"><label class="lab">Type<select name="type"><?php foreach (EXPENSE_TYPES as $ty): ?><option><?= $ty ?></option><?php endforeach; ?></select></label><label class="lab">Vendor<input type="text" name="vendor"></label></div>
      <div class="r3"><label class="lab">Amount<input type="number" step="0.01" min="0.01" name="amount" required></label><label class="lab">Currency<input type="text" name="currency" value="USD" maxlength="3" style="text-transform:uppercase"></label><label class="lab">Rate to USD<input type="number" step="0.0001" name="rate" placeholder="1"></label></div>
      <div class="r2"><label class="lab">Date<input type="date" name="spent_on" value="<?= date('Y-m-d') ?>"></label><label class="lab">Paid by<input type="text" name="paid_by" placeholder="Church card, or a name"></label></div>
      <label class="lab">Receipt photo<input type="file" name="receipt" accept="image/*,application/pdf"></label>
      <label class="chk"><input type="checkbox" name="reimburse" value="1"> They paid out of pocket and need paying back</label>
      <button class="btn btn-dark" type="submit">Save expense</button>
      <div class="muted small">Example: 2 Belize dollars = 1 U.S. dollar, so the rate is 0.5.</div>
    </form></div></details></aside>
</div>
