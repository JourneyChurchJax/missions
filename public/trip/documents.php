<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$docs = all("SELECT f.*, a.opened_at, a.acked_at FROM files f LEFT JOIN file_acks a ON a.file_id = f.id AND a.person_id = ?
             WHERE f.trip_id = ? AND f.visible = 1 AND f.person_id IS NULL AND f.kind = 'doc' AND f.path IS NOT NULL ORDER BY f.id", [$me_id, $tid]);
$links = all("SELECT * FROM files WHERE trip_id = ? AND visible = 1 AND person_id IS NULL AND kind IN ('link','doc') AND path IS NULL AND url IS NOT NULL AND url <> '' ORDER BY id", [$tid]);
$mine = all("SELECT * FROM files WHERE person_id = ? AND kind IN ('upload','pagephoto') ORDER BY id DESC", [$me_id]);
$upload_tasks = array_values(array_filter($my_tasks, fn($k) => $k['type'] === 'upload'));
$needs = array_values(array_filter($upload_tasks, fn($k) => !$k['done_at']));
$need_count = count($needs) + count($unread);
page_open('Documents');
member_header('docs');
?>
<main class="main m" id="main">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Documents</h1></div>
    <a class="btn btn-primary" href="/packet.php" target="_blank">Trip packet (print or PDF)</a>
  </div>

  <div class="split side-380">
    <div style="display:flex;flex-direction:column;gap:28px">
      <?php if ($need_count): ?>
      <section><div class="gh">Needs you · <?= $need_count ?></div><div class="group">
        <?php foreach ($unread as $f): ?>
          <div class="cell" style="min-height:68px;flex-wrap:wrap"><span class="doc">READ</span><div class="grow"><strong><?= e($f['title']) ?></strong><div class="muted small">Read it, then tap I agree</div></div>
            <a class="btn" style="height:40px" href="/file.php?id=<?= (int)$f['id'] ?>" target="_blank">Open</a>
            <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="file_ack"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-primary" style="height:40px;padding:0 18px;font-size:15px" type="submit">I've read it and agree</button></form></div>
        <?php endforeach; ?>
        <?php foreach ($needs as $k) include dirname(__DIR__) . '/inc/_my_task.php'; ?>
      </div></section>
      <?php endif; ?>

      <section><div class="gh">Trip documents</div><div class="group">
      <?php foreach ($docs as $f): ?>
        <a class="cell" style="min-height:64px" href="/file.php?id=<?= (int)$f['id'] ?>" target="_blank"><span class="doc"><?= e(strtoupper(pathinfo((string)$f['original'], PATHINFO_EXTENSION) ?: 'DOC')) ?></span><div class="grow"><strong><?= e($f['title']) ?></strong><div class="muted small"><?= e($f['note'] ?? '') ?></div></div>
          <?php if ($f['must_ack']): ?><span class="pill<?= $f['acked_at'] ? ' pill-ok' : '' ?>"><?= $f['acked_at'] ? 'Agreed' : 'Must read' ?></span><?php else: ?><span class="chev">›</span><?php endif; ?></a>
      <?php endforeach; ?>
      <?= $docs ? '' : '<div class="empty">Your leader will post documents here.</div>' ?>
      </div></section>

      <?php if ($links): ?>
      <section><div class="gh">Guides and links</div><div class="group">
      <?php foreach ($links as $f): ?><a class="cell" style="min-height:60px" href="/file.php?id=<?= (int)$f['id'] ?>" target="_blank"><span class="doc">LINK</span><div class="grow"><strong><?= e($f['title']) ?></strong><?php if ($f['note']): ?><div class="muted small"><?= e($f['note']) ?></div><?php endif; ?></div><span class="chev">›</span></a><?php endforeach; ?>
      </div></section>
      <?php endif; ?>
    </div>

    <aside class="sticky" style="top:96px;display:flex;flex-direction:column;gap:20px">
      <section class="panel" style="gap:12px"><strong style="font-size:18px">What you've uploaded</strong>
        <?php foreach ($mine as $f): ?><a class="row" href="/file.php?id=<?= (int)$f['id'] ?>"><span><?= e($f['title']) ?></span><span class="muted small"><?= fdate($f['created_at'], 'M j') ?></span></a><?php endforeach; ?>
        <?= $mine ? '' : '<div class="muted small">Nothing yet. Upload tasks like your passport copy show up under Needs you.</div>' ?>
        <div class="muted small">Only you and your trip leaders can see your uploads.</div>
      </section>
      <section class="note"><?php $upd = count($upload_tasks) - count($needs); $tot = count($upload_tasks) + count($must_read); $dn = $upd + (count($must_read) - count($unread)); ?>
        <strong><?= $dn ?> of <?= $tot ?> done</strong><?= bar(pct($dn, max(1, $tot))) ?><div class="muted"><?= $need_count ? 'Finish what\'s under Needs you to complete this part.' : 'You\'re all set here.' ?></div></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
