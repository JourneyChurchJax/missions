<?php
// One task row for the signed-in traveler. Expects $k (task with done_at, file_id), $me (person).
$done = (bool)$k['done_at'];
?>
<div class="cell task" id="task-<?= (int)$k['id'] ?>">
  <?php if ($k['type'] === 'traveler'): ?>
    <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="task_toggle"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
      <button class="check<?= $done ? ' done' : '' ?>" type="submit" aria-label="<?= $done ? 'Mark not done' : 'Mark done' ?>: <?= e($k['title']) ?>"<?= $k['allow_self'] ? '' : ' disabled' ?>>✓</button></form>
  <?php else: ?>
    <span class="check<?= $done ? ' done' : '' ?>" aria-hidden="true">✓</span>
  <?php endif; ?>
  <div class="grow"><strong<?= $done ? ' style="text-decoration:line-through;color:var(--muted)"' : '' ?>><?= e($k['title']) ?></strong>
    <div class="muted small"><?= $done ? 'Done ' . fdate($k['done_at'], 'M j') : ($k['due_date'] ? 'Due ' . fdate($k['due_date'], 'M j') : '') ?><?= $k['description'] && !$done && $k['type'] !== 'sign' ? ' · ' . e($k['description']) : '' ?></div>
    <?php if ($k['file_id']): ?><a class="small" href="/file.php?id=<?= (int)$k['file_id'] ?>">See what you uploaded</a><?php endif; ?>
  </div>
  <?php ob_start(); ?>
  <?php if ($k['type'] === 'sign'): [$signed, $psigned, $pneed] = signature_state($k, (int)$me['id']); ?>
    <?php if (!$signed): ?><a class="btn btn-primary" href="/sign.php?task=<?= (int)$k['id'] ?>" style="height:38px">Read and sign</a>
    <?php elseif ($pneed && !$psigned): ?><span class="muted small">You signed. Waiting for a parent.</span>
    <?php else: ?><a class="small" href="/sign.php?task=<?= (int)$k['id'] ?>">View signature</a><?php endif; ?>
  <?php elseif ($k['type'] === 'upload' && !$done): ?>
    <details class="edit"><summary>Upload</summary>
      <form class="form" method="post" action="/action.php" enctype="multipart/form-data" style="padding-top:10px"><?= csrf() ?><input type="hidden" name="action" value="task_upload"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
        <label class="drop"><strong>Choose a photo or PDF</strong><span class="muted small">Only you and your leaders can see it</span><input type="file" name="file" accept="image/*,application/pdf" required></label>
        <button class="btn btn-dark" type="submit">Upload</button></form>
    </details>
  <?php elseif ($k['type'] === 'verify' && !$done): ?>
    <details class="edit"><summary>Confirm</summary>
      <form class="form" method="post" action="/action.php" style="padding-top:10px"><?= csrf() ?><input type="hidden" name="action" value="verify_info"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
        <div class="muted small">These must match your passport exactly.</div>
        <div class="r2"><label class="lab">First name<input type="text" name="first_name" value="<?= e($me['first_name']) ?>" required></label><label class="lab">Last name<input type="text" name="last_name" value="<?= e($me['last_name']) ?>" required></label></div>
        <label class="lab">Birth date<input type="date" name="birth_date" value="<?= e($me['birth_date']) ?>" required></label>
        <button class="btn btn-dark" type="submit">This is correct</button></form>
    </details>
  <?php elseif ($k['type'] === 'traveler' && !$done && $k['allow_self']): ?>
    <span class="muted small">Tap the circle when it's done</span>
  <?php endif; ?>
  <?php $act = trim(ob_get_clean()); if ($act !== ''): ?><div class="task-act"><?= $act ?></div><?php endif; ?>
</div>
