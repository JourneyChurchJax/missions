<?php // Shared task form fields. Expects $task (array). ?>
<label class="lab">Task<input type="text" name="title" value="<?= e($task['title'] ?? '') ?>" placeholder="Upload a passport copy" required></label>
<div class="r2">
  <label class="lab">Type<select name="type"><?php foreach (TASK_TYPES as $tk => $tl): ?><option value="<?= $tk ?>"<?= ($task['type'] ?? '') === $tk ? ' selected' : '' ?>><?= $tl ?></option><?php endforeach; ?></select></label>
  <label class="lab">Due<input type="date" name="due_date" value="<?= e($task['due_date'] ?? '') ?>"></label>
</div>
<label class="lab">Instructions<textarea name="description" rows="2"><?= e($task['description'] ?? '') ?></textarea></label>
<label class="chk"><input type="checkbox" name="allow_self" value="1"<?= !empty($task['allow_self']) ? ' checked' : '' ?>> Travelers can mark it done themselves</label>
<label class="chk"><input type="checkbox" name="minors_only" value="1"<?= !empty($task['minors_only']) ? ' checked' : '' ?>> Only for travelers under 18</label>
