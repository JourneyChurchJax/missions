<?php
// Trip workspace · Documents (posted by staff, plus what travelers uploaded)
$docs = all("SELECT * FROM files WHERE trip_id = ? AND person_id IS NULL ORDER BY kind, id", [$id]);
$uploads = all("SELECT f.*, p.first_name, p.preferred_name, p.last_name FROM files f JOIN people p ON p.id = f.person_id WHERE f.trip_id = ? ORDER BY f.id DESC", [$id]);
$n = count($trav);
?>
<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <section>
      <div class="gh">Posted for the team</div>
      <div class="group">
      <?php foreach ($docs as $f):
        $opened = (int)val('SELECT COUNT(*) FROM file_acks WHERE file_id = ?', [$f['id']]);
        $acked = (int)val('SELECT COUNT(*) FROM file_acks WHERE file_id = ? AND acked_at IS NOT NULL', [$f['id']]);
        $missing = !$f['path'] && !$f['url']; ?>
        <div class="cell" style="flex-wrap:wrap">
          <span class="doc"><?= $f['kind'] === 'link' ? 'LINK' : e(strtoupper(pathinfo((string)$f['original'], PATHINFO_EXTENSION) ?: 'DOC')) ?></span>
          <div class="grow"><a href="/file.php?id=<?= (int)$f['id'] ?>" style="font-weight:600;text-decoration:none"><?= e($f['title']) ?></a>
            <div class="muted small"><?= $f['visible'] ? 'Travelers can see it' : 'Staff only' ?><?= $f['must_ack'] ? " · must read: $acked of $n agreed" : " · opened by $opened" ?><?= $missing ? ' · <strong>file not uploaded yet</strong>' : '' ?></div></div>
          <details class="edit"><summary>Edit</summary>
            <form class="form" method="post" action="/action.php" enctype="multipart/form-data" style="padding-top:10px">
              <?= csrf() ?><input type="hidden" name="action" value="file_update"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="trip_id" value="<?= $id ?>">
              <label class="lab">Title<input type="text" name="title" value="<?= e($f['title']) ?>"></label>
              <label class="lab">Note<input type="text" name="note" value="<?= e($f['note']) ?>"></label>
              <?php if ($f['kind'] === 'link'): ?><label class="lab">Link<input type="url" name="url" value="<?= e($f['url']) ?>" placeholder="https://"></label>
              <?php else: ?><label class="lab"><?= $f['path'] ? 'Replace the file' : 'Upload the file' ?><input type="file" name="file"></label><?php endif; ?>
              <label class="chk"><input type="checkbox" name="visible" value="1"<?= $f['visible'] ? ' checked' : '' ?>> Travelers can see it</label>
              <label class="chk"><input type="checkbox" name="must_ack" value="1"<?= $f['must_ack'] ? ' checked' : '' ?>> Everyone must read and agree</label>
              <div class="actions"><button class="btn btn-dark" type="submit">Save</button></div>
            </form>
            <?php if ($f['must_ack']): ?>
              <div class="small" style="padding-top:10px"><?php foreach ($trav as $m): $a = one('SELECT * FROM file_acks WHERE file_id = ? AND person_id = ?', [$f['id'], $m['person_id']]); ?>
                <div><?= e(full_name($m)) ?>: <span class="muted"><?= $a && $a['acked_at'] ? 'agreed ' . fdate($a['acked_at'], 'M j') : ($a ? 'opened, not agreed' : 'not opened') ?></span></div>
              <?php endforeach; ?></div>
            <?php endif; ?>
            <form method="post" action="/action.php" onsubmit="return confirm('Delete this document?')" style="text-align:right;padding-top:8px"><?= csrf() ?><input type="hidden" name="action" value="file_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="link-btn danger">Delete</button></form>
          </details>
        </div>
      <?php endforeach; ?>
      <?= $docs ? '' : '<div class="empty">Nothing posted yet.</div>' ?>
      </div>
    </section>

    <section>
      <div class="gh">Uploaded by travelers</div>
      <div class="group">
      <?php foreach ($uploads as $f): ?>
        <a class="cell" href="/file.php?id=<?= (int)$f['id'] ?>"><span class="doc"><?= e(strtoupper(pathinfo((string)$f['original'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span><div class="grow"><strong><?= e(full_name($f)) ?></strong><div class="muted small"><?= e($f['title']) ?> · <?= fdate($f['created_at'], 'M j') ?></div></div><span class="chev">›</span></a>
      <?php endforeach; ?>
      <?= $uploads ? '' : '<div class="empty">No uploads yet. Upload tasks collect passports, insurance cards and forms here.</div>' ?>
      </div>
    </section>
  </div>

  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Post a document</summary><div class="body">
      <form class="form" method="post" action="/action.php" enctype="multipart/form-data">
        <?= csrf() ?><input type="hidden" name="action" value="file_upload"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <label class="lab">Title<input type="text" name="title" placeholder="Packing list"></label>
        <label class="lab">File (PDF, photo, Word, Excel · 20 MB)<input type="file" name="file"></label>
        <label class="lab">Or a link<input type="url" name="url" placeholder="https://"></label>
        <label class="lab">Note<input type="text" name="note" placeholder="Read before the November meeting"></label>
        <label class="chk"><input type="checkbox" name="visible" value="1" checked> Travelers can see it</label>
        <label class="chk"><input type="checkbox" name="must_ack" value="1"> Everyone must read and agree</label>
        <button class="btn btn-dark" type="submit">Post</button>
      </form>
    </div></details>
    <section class="note"><strong>Files stay private</strong><div class="muted small">Documents are stored outside the public website. Only people on this trip can open what you mark visible.</div></section>
  </aside>
</div>
