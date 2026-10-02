<?php
// Record or edit one gift. Expects $g (gift or []), optional $for_preset ('t3', 'm3-12'), $batch_preset (int).
$g = $g ?? [];
$for_now = $g ? ($g['person_id'] ? 'm' . $g['trip_id'] . '-' . $g['person_id'] : ($g['trip_id'] ? 't' . $g['trip_id'] : '')) : ($for_preset ?? '');
$all_donors = $GLOBALS['_gf_donors'] ??= all('SELECT * FROM donors ORDER BY last_name, first_name, org');
$active = $GLOBALS['_gf_trips'] ??= array_map(fn($t) => $t + ['_trav' => travelers((int)$t['id'])], trips('upcoming'));
?>
<form class="form" method="post" action="/action.php">
  <?= csrf() ?><?= once() ?><input type="hidden" name="action" value="gift_save"><input type="hidden" name="id" value="<?= (int)($g['id'] ?? 0) ?>"><?php if (!empty($g['id'])): ?><input type="hidden" name="back" value="/admin/gift.php?id=<?= (int)$g['id'] ?>"><?php endif; ?>
  <div class="r2">
    <label class="lab">Amount<input type="number" step="0.01" min="0.01" name="amount" value="<?= e(isset($g['amount']) ? (string)$g['amount'] : '') ?>" required></label>
    <label class="lab">Date<input type="date" name="gift_date" value="<?= e($g['gift_date'] ?? date('Y-m-d')) ?>"></label>
  </div>
  <label class="lab">For<select name="for">
    <option value="">General missions</option>
    <?php // Keep a gift's current trip and traveler even when the trip has ended or they're no longer on the team
    if ($for_now !== '' && !in_array((int)($g['trip_id'] ?? 0), array_map('intval', array_column($active, 'id')), true)): $ct = trip((int)($g['trip_id'] ?? 0)); ?>
      <option value="<?= e($for_now) ?>" selected><?= e(gift_for($g)) ?><?= $ct ? ' (' . fdate($ct['start_date'], 'Y') . ')' : '' ?></option>
    <?php elseif ($for_now !== '' && !empty($g['person_id']) && !in_array((int)$g['person_id'], array_map('intval', array_column(array_values(array_filter($active, fn($t) => (int)$t['id'] === (int)($g['trip_id'] ?? 0)))[0]['_trav'] ?? [], 'person_id')), true)): ?>
      <option value="<?= e($for_now) ?>" selected><?= e(gift_for($g)) ?> (no longer traveling)</option>
    <?php endif; ?>
    <?php foreach ($active as $t): ?><optgroup label="<?= e($t['name']) ?>"><option value="t<?= (int)$t['id'] ?>"<?= $for_now === 't' . $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> team (not one person)</option>
      <?php foreach ($t['_trav'] as $m): $ov = 'm' . $t['id'] . '-' . $m['person_id']; ?><option value="<?= $ov ?>"<?= $for_now === $ov ? ' selected' : '' ?>><?= e(full_name($m)) ?></option><?php endforeach; ?></optgroup>
    <?php endforeach; ?>
  </select></label>
  <label class="lab">From<select name="donor_id" data-newdonor>
    <option value="0">New donor, or no name</option>
    <?php foreach ($all_donors as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (int)($g['donor_id'] ?? 0) === (int)$d['id'] ? ' selected' : '' ?>><?= e(donor_name($d)) ?><?= $d['email'] ? ' · ' . e($d['email']) : '' ?></option><?php endforeach; ?>
  </select></label>
  <div class="r2 newdonor"<?= !empty($g['donor_id']) ? ' hidden' : '' ?>>
    <label class="lab">First name<input type="text" name="donor_first"></label><label class="lab">Last name<input type="text" name="donor_last"></label>
    <label class="lab">Email (for their statement)<input type="email" name="donor_email"></label><label class="lab">Business or church<input type="text" name="donor_org"></label>
  </div>
  <div class="r3">
    <label class="lab">Method<select name="method"><?php foreach (GIFT_METHODS as $k => $l): ?><option value="<?= $k ?>"<?= ($g['method'] ?? 'check') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label class="lab">Check number<input type="text" name="check_no" value="<?= e($g['check_no'] ?? '') ?>"></label>
    <label class="lab">Deposit batch<select name="batch_id"><option value="0">None</option><?php $bl = open_batches(); if (!empty($g['batch_id']) && !in_array((int)$g['batch_id'], array_map('intval', array_column($bl, 'id')), true) && ($cb = batch((int)$g['batch_id']))) $bl[] = $cb; foreach ($bl as $b): ?><option value="<?= (int)$b['id'] ?>"<?= (int)($g['batch_id'] ?? ($batch_preset ?? 0)) === (int)$b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></label>
  </div>
  <div class="r2"><label class="lab">Card fee (if any)<input type="number" step="0.01" min="0" name="fee" value="<?= e(isset($g['fee']) ? (string)$g['fee'] : '') ?>" placeholder="0"></label><label class="lab">Note<input type="text" name="note" value="<?= e($g['note'] ?? '') ?>"></label></div>
  <label class="chk"><input type="checkbox" name="anonymous" value="1"<?= !empty($g['anonymous']) ? ' checked' : '' ?>> Hide the donor's name from the traveler</label>
  <div class="actions"><button class="btn btn-dark" type="submit"><?= $g ? 'Save gift' : 'Record gift' ?></button></div>
</form>
