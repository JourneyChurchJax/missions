<?php // Shared task form fields. Expects $task (array). ?>
<label class="lab">Task<input type="text" name="title" value="<?= e($task['title'] ?? '') ?>" placeholder="Upload a passport copy" required></label>
<div class="r2">
  <label class="lab">Type<select name="type"><?php foreach (TASK_TYPES as $tk => $tl): ?><option value="<?= $tk ?>"<?= ($task['type'] ?? '') === $tk ? ' selected' : '' ?>><?= $tl ?></option><?php endforeach; ?></select></label>
  <label class="lab">Due<input type="date" name="due_date" value="<?= e($task['due_date'] ?? '') ?>"></label>
</div>
<label class="lab">Instructions<textarea name="description" rows="2"><?= e($task['description'] ?? '') ?></textarea></label>
<label class="chk"><input type="checkbox" name="allow_self" value="1"<?= !empty($task['allow_self']) ? ' checked' : '' ?>> Travelers can mark it done themselves</label>
<label class="chk"><input type="checkbox" name="minors_only" value="1"<?= !empty($task['minors_only']) ? ' checked' : '' ?>> Only for travelers under 18</label>
<?php $docs_for_sign = all("SELECT id, title FROM files WHERE trip_id = ? AND person_id IS NULL AND kind IN ('doc','link') ORDER BY title", [$id]); ?>
<div class="r2">
  <label class="lab">Document to sign (for "Sign a document")<select name="file_id"><option value="0">None</option><?php foreach ($docs_for_sign as $df): ?><option value="<?= (int)$df['id'] ?>"<?= (int)($task['file_id'] ?? 0) === (int)$df['id'] ? ' selected' : '' ?>><?= e($df['title']) ?></option><?php endforeach; ?></select></label>
  <label class="chk" style="align-self:end;padding-bottom:10px"><input type="checkbox" name="parent_sign" value="1"<?= !empty($task['parent_sign']) ? ' checked' : '' ?>> A parent also signs for travelers under 18</label>
</div>
<div class="muted small">For a signature, the instructions above become the statement people agree to, like "I have read the waiver and agree to it."</div>
