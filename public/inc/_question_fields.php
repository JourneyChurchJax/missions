<?php
// Question editor fields. Expects $qq (question array or []) and $locked (bool: type can't change once people answer).
?>
<label class="lab">Question<input type="text" name="label" value="<?= e($qq['label'] ?? '') ?>" required></label>
<div class="r2">
  <label class="lab">Answer type<select name="kind"><?php foreach (Q_KINDS as $k => $l): if ($locked && ($qq['kind'] ?? '') !== $k) continue; ?><option value="<?= $k ?>"<?= ($qq['kind'] ?? 'short') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <label class="lab">Help text (optional)<input type="text" name="help" value="<?= e($qq['help'] ?? '') ?>"></label>
</div>
<label class="lab">Choices, one per line (for Pick one and Pick any)<textarea name="options" rows="3"><?= e($qq['options'] ?? '') ?></textarea></label>
<label class="chk" style="display:flex;gap:8px;align-items:center;font-size:14px"><input type="checkbox" name="required" value="1"<?= !isset($qq['required']) || $qq['required'] ? ' checked' : '' ?>> Required</label>
