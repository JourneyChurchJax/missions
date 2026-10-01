<?php
// Trip workspace · Budget
$rows = all('SELECT * FROM budget WHERE trip_id = ? ORDER BY est_date, id', [$id]);
$n = max(1, count($trav));
$total = trip_budget($id);
$per = $total / $n;
$types = ['Airfare', 'Lodging', 'Meals/Food', 'Transportation-Other', 'Taxes/Visas', 'Insurance', 'Supplies', 'Ministry', 'Debrief/Tourism', 'MISC'];
$budget_fields = function (array $b) use ($types) { ?>
  <label class="lab">What<input type="text" name="description" value="<?= e($b['description'] ?? '') ?>" required></label>
  <div class="r2"><label class="lab">Type<select name="type"><?php foreach ($types as $ty): ?><option<?= ($b['type'] ?? '') === $ty ? ' selected' : '' ?>><?= $ty ?></option><?php endforeach; ?></select></label><label class="lab">Vendor<input type="text" name="vendor" value="<?= e($b['vendor'] ?? '') ?>"></label></div>
  <div class="r3"><label class="lab">Cost each<input type="number" step="0.01" name="unit_cost" value="<?= e($b['unit_cost'] ?? '') ?>" required></label><label class="lab">Quantity<input type="number" name="qty" value="<?= e($b['qty'] ?? 1) ?>"></label><label class="lab">Date<input type="date" name="est_date" value="<?= e($b['est_date'] ?? '') ?>"></label></div>
  <label class="chk"><input type="checkbox" name="per_traveler" value="1"<?= !empty($b['per_traveler']) ? ' checked' : '' ?>> One per traveler (quantity follows team size)</label>
<?php };
?>
<section class="g4">
  <div class="tile"><div class="k">Total budget</div><div class="disp v"><?= money($total) ?></div></div>
  <div class="tile"><div class="k">Travelers</div><div class="disp v"><?= count($trav) ?></div></div>
  <div class="tile"><div class="k">Works out to</div><div class="disp v"><?= money($per) ?></div><div class="muted small">per person</div></div>
  <div class="tile"><div class="k">Goal per person</div><div class="disp v"><?= money((float)$t['cost_per_person']) ?></div>
    <?php if (abs($per - (float)$t['cost_per_person']) > 1): ?><form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="goal_sync"><input type="hidden" name="trip_id" value="<?= $id ?>"><button class="link-btn">Match the budget</button></form><?php else: ?><div class="muted small">Matches the budget</div><?php endif; ?></div>
</section>
<div class="split">
  <section class="group">
  <?php foreach ($rows as $b): $line = (float)$b['unit_cost'] * ($b['per_traveler'] ? $n : (int)$b['qty']); ?>
    <div class="cell" style="flex-wrap:wrap">
      <div class="grow"><strong><?= e($b['description']) ?></strong><div class="muted small"><?= e($b['type']) ?><?= $b['vendor'] ? ' · ' . e($b['vendor']) : '' ?> · <?= money((float)$b['unit_cost']) ?> × <?= $b['per_traveler'] ? "$n travelers" : (int)$b['qty'] ?><?= $b['est_date'] ? ' · ' . fdate($b['est_date'], 'M j') : '' ?></div></div>
      <strong><?= money($line) ?></strong>
      <details class="edit"><summary>Edit</summary>
        <form class="form" method="post" action="/action.php" style="padding-top:10px"><?= csrf() ?><input type="hidden" name="action" value="budget_save"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="trip_id" value="<?= $id ?>"><?php $budget_fields($b); ?><div class="actions"><button class="btn btn-dark">Save</button></div></form>
        <form method="post" action="/action.php" onsubmit="return confirm('Remove this line?')" style="text-align:right;padding-top:8px"><?= csrf() ?><input type="hidden" name="action" value="budget_delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="link-btn danger">Remove</button></form>
      </details>
    </div>
  <?php endforeach; ?>
    <div class="cell" style="background:var(--tint)"><div class="grow"><strong>Total</strong></div><strong><?= money($total) ?></strong></div>
  </section>
  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Add a budget line</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="budget_save"><input type="hidden" name="trip_id" value="<?= $id ?>"><?php $budget_fields(['qty' => 1]); ?><button class="btn btn-dark">Add</button></form>
    </div></details>
    <section class="note"><strong>Expenses come in phase 3</strong><div class="muted small">You'll log what was actually spent, with currency, and compare it to this budget.</div></section>
  </aside>
</div>
