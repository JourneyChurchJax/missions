<?php
// Trip workspace · Updates (announcements to the team, plus the activity history)
$posts = all('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id DESC', [$id]);
$log = all('SELECT * FROM activity WHERE trip_id = ? ORDER BY id DESC LIMIT 30', [$id]);
?>
<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <form class="tile xl form" method="post" action="/action.php">
      <?= csrf() ?><input type="hidden" name="action" value="announce_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
      <strong style="font-size:17px">Post an update to the <?= e($t['name']) ?> team</strong>
      <label class="lab">Headline (optional)<input type="text" name="title" placeholder="Bring your passport Sunday"></label>
      <label class="lab">Message<textarea name="body" rows="4" required></textarea></label>
      <div class="actions"><span class="muted small" style="margin-right:auto;align-self:center">Shows in every traveler's Messages. Email and text delivery comes in phase 4.</span><button class="btn btn-primary" type="submit">Post</button></div>
    </form>
    <section>
      <div class="gh">Posted</div>
      <div class="group">
      <?php foreach ($posts as $p): ?>
        <div class="post"><div style="display:flex;justify-content:space-between;gap:12px"><strong><?= e($p['title'] ?: 'Update') ?></strong><span class="muted small"><?= e($p['author']) ?> · <?= fdate($p['created_at'], 'M j, g:i A') ?></span></div><div><?= soft($p['body']) ?></div>
          <form method="post" action="/action.php" onsubmit="return confirm('Delete this update?')"><?= csrf() ?><input type="hidden" name="action" value="announce_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn danger">Delete</button></form></div>
      <?php endforeach; ?>
      <?= $posts ? '' : '<div class="empty">No updates yet.</div>' ?>
      </div>
    </section>
  </div>
  <aside class="sticky">
    <div class="gh">History</div>
    <div class="group">
    <?php foreach ($log as $x): ?><div class="cell"><div class="grow"><strong><?= e($x['who']) ?></strong> <span class="muted"><?= e(lcfirst($x['what'])) ?></span><div class="muted small"><?= fdate($x['created_at'], 'M j, g:i A') ?></div></div></div><?php endforeach; ?>
    <?= $log ? '' : empty_state('Nothing yet') ?>
    </div>
  </aside>
</div>
