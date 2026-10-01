<?php // Shared meeting form fields. Expects $mtg (array).
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : ''; ?>
<label class="lab">Name<input type="text" name="title" value="<?= e($mtg['title'] ?? '') ?>" required></label>
<div class="r2">
  <label class="lab">Starts<input type="datetime-local" name="starts_at" value="<?= $dt($mtg['starts_at'] ?? null) ?>" required></label>
  <label class="lab">Ends<input type="datetime-local" name="ends_at" value="<?= $dt($mtg['ends_at'] ?? null) ?>"></label>
</div>
<div class="r2">
  <label class="lab">Where<input type="text" name="location" value="<?= e($mtg['location'] ?? '') ?>" placeholder="Room B133"></label>
  <label class="lab">Address<input type="text" name="address" value="<?= e($mtg['address'] ?? '') ?>"></label>
</div>
<label class="lab">Notes for the team<textarea name="notes" rows="2"><?= e($mtg['notes'] ?? '') ?></textarea></label>
