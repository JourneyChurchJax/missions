<?php
// Trip workspace · Tasks & goals
$tasks = all('SELECT * FROM tasks WHERE trip_id = ? ORDER BY due_date, id', [$id]);
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$id]);
$doneMap = [];
foreach (all('SELECT d.* FROM task_done d JOIN tasks t ON t.id = d.task_id WHERE t.trip_id = ?', [$id]) as $d) $doneMap[$d['task_id']][$d['person_id']] = $d;
$traveler_tasks = array_values(array_filter($tasks, fn($k) => in_array($k['type'], ['traveler', 'upload', 'verify'], true)));
$other_tasks = array_values(array_filter($tasks, fn($k) => !in_array($k['type'], ['traveler', 'upload', 'verify'], true)));
?>
<section>
  <div class="gh"><span>Who has done what · click a circle to mark done</span></div>
  <div class="group tbl"><table class="matrix">
    <thead><tr><th>Traveler</th><?php foreach ($traveler_tasks as $k): ?><th title="<?= e($k['title']) ?>"><?= e(mb_strimwidth($k['title'], 0, 22, '…')) ?><div class="muted" style="font-weight:400;font-size:12px"><?= fdate($k['due_date'], 'M j') ?></div></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($trav as $m): ?>
      <tr><td><strong><?= e(full_name($m)) ?></strong></td>
      <?php foreach ($traveler_tasks as $k): $d = $doneMap[$k['id']][$m['person_id']] ?? null; ?>
        <td>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="task_toggle"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><input type="hidden" name="person_id" value="<?= (int)$m['person_id'] ?>">
            <button class="dotbtn<?= $d ? ' done' : '' ?>" type="submit" aria-label="<?= e($k['title']) ?> for <?= e(full_name($m)) ?>: <?= $d ? 'done' : 'not done' ?>">✓</button>
          </form>
          <?php if ($d && $d['file_id']): ?><div><a class="small" href="/file.php?id=<?= (int)$d['file_id'] ?>">File</a></div><?php endif; ?>
        </td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (!$trav || !$traveler_tasks): ?><tr><td colspan="<?= count($traveler_tasks) + 1 ?>" class="empty">Add travelers and traveler tasks to see this grid.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <section>
      <div class="gh">All tasks</div>
      <div class="group">
      <?php foreach ($tasks as $k): $cnt = count($doneMap[$k['id']] ?? []); ?>
        <div class="cell" style="flex-wrap:wrap">
          <?= date_box_for($k['due_date']) ?>
          <div class="grow"><strong><?= e($k['title']) ?></strong><div class="muted small"><?= e(TASK_TYPES[$k['type']] ?? $k['type']) ?><?= $k['minors_only'] ? ' · under 18 only' : '' ?><?= in_array($k['type'], ['traveler', 'upload', 'verify'], true) ? " · $cnt of " . count($trav) . ' done' : '' ?></div></div>
          <details class="edit"><summary>Edit</summary>
            <form class="form" method="post" action="/action.php" style="padding-top:12px">
              <?= csrf() ?><input type="hidden" name="action" value="task_save"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><input type="hidden" name="trip_id" value="<?= $id ?>">
              <?php $task = $k; include __DIR__ . '/_task_fields.php'; ?>
              <div class="actions"><button class="btn btn-dark" type="submit">Save</button></div>
            </form>
            <form method="post" action="/action.php" onsubmit="return confirm('Delete this task?')" style="text-align:right;padding-top:8px"><?= csrf() ?><input type="hidden" name="action" value="task_delete"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="link-btn danger">Delete task</button></form>
          </details>
        </div>
      <?php endforeach; ?>
      <?= $tasks ? '' : '<div class="empty">No tasks yet.</div>' ?>
      </div>
    </section>

    <section>
      <div class="gh">Fundraising goals</div>
      <div class="group">
      <?php foreach ($goals as $g): ?>
        <div class="cell" style="flex-wrap:wrap"><?= date_box_for($g['due_date']) ?><div class="grow"><strong><?= $g['kind'] === 'percent' ? (int)$g['amount'] . '% of each goal' : money((float)$g['amount']) . ' each' ?></strong><div class="muted small">Due <?= fdate($g['due_date'], 'F j, Y') ?></div></div>
          <form method="post" action="/action.php" onsubmit="return confirm('Delete this goal?')"><?= csrf() ?><input type="hidden" name="action" value="goal_delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn danger">Delete</button></form></div>
      <?php endforeach; ?>
      <?= $goals ? '' : '<div class="empty">No goals yet.</div>' ?>
      </div>
    </section>
  </div>

  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Add a task</summary><div class="body">
      <form class="form" method="post" action="/action.php">
        <?= csrf() ?><input type="hidden" name="action" value="task_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <?php $task = ['type' => 'traveler', 'allow_self' => 1]; include __DIR__ . '/_task_fields.php'; ?>
        <button class="btn btn-dark" type="submit">Add task</button>
      </form>
    </div></details>
    <details class="add"><summary>Add a fundraising goal</summary><div class="body">
      <form class="form" method="post" action="/action.php">
        <?= csrf() ?><input type="hidden" name="action" value="goal_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <div class="r2"><label class="lab">Amount<input type="number" step="0.01" name="amount" required></label><label class="lab">As<select name="kind"><option value="percent">% of goal</option><option value="amount">Dollars</option></select></label></div>
        <label class="lab">Due<input type="date" name="due_date" required></label>
        <button class="btn btn-dark" type="submit">Add goal</button>
      </form>
    </div></details>
    <section class="note"><strong>Task types</strong><div class="muted small">Traveler tasks show on each traveler's checklist. Upload tasks ask for a file. Confirm-info tasks ask them to check their name and birth date. Leader and admin tasks stay with staff.</div></section>
  </aside>
</div>
