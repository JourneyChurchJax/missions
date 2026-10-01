<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$p = isset($_GET['id']) ? person((int)$_GET['id']) : [];
$trips_of = $p ? all('SELECT t.*, m.role, m.id AS member_id FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ? ORDER BY t.start_date DESC', [$p['id']]) : [];
$uploads = $p ? all('SELECT * FROM files WHERE person_id = ? ORDER BY id DESC', [$p['id']]) : [];
$staff = true;
$back = '/admin/person.php?id=' . (int)($p['id'] ?? 0);
page_open($p ? full_name($p) : 'Add a person');
admin_header('people');
?>
<main class="main" style="max-width:1100px">
  <div class="bar-head">
    <div style="display:flex;gap:14px;align-items:center">
      <span class="av dark lg"><?= $p ? initials(full_name($p)) : '+' ?></span>
      <div><a class="muted small" href="<?= isset($_GET['trip']) ? '/admin/trip.php?id=' . (int)$_GET['trip'] . '&tab=team' : '/admin/people.php' ?>" style="text-decoration:none">‹ Back</a><h1><?= $p ? e(full_name($p)) : 'Add a person' ?></h1>
      <?php if ($p): ?><div class="muted small"><?= e($p['email'] ?: 'No email') ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?><?= $p['verified_at'] ? ' · confirmed their info ' . fdate($p['verified_at'], 'M j') : '' ?></div><?php endif; ?></div>
    </div>
  </div>
  <div class="split">
    <div><?php include dirname(__DIR__) . '/inc/_person_form.php'; ?></div>
    <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
      <section><div class="gh">Trips</div><div class="group">
        <?php foreach ($trips_of as $t): [$d, $n] = readiness((int)$t['id'], (int)$p['id']); ?>
          <a class="cell" href="/admin/trip.php?id=<?= (int)$t['id'] ?>&tab=team"><div class="grow"><strong><?= e($t['name']) ?></strong><div class="muted small"><?= e(ucfirst($t['role'])) ?> · <?= fdate($t['start_date'], 'M Y') ?> · <?= $d ?>/<?= $n ?> ready</div></div><span class="chev">›</span></a>
        <?php endforeach; ?>
        <?= $trips_of ? '' : empty_state('Not on a trip yet') ?>
      </div></section>
      <?php if ($p): $bgs = all('SELECT * FROM background_checks WHERE person_id = ? ORDER BY id DESC', [$p['id']]); $bg = $bgs[0] ?? null; $nsig = (int)val('SELECT COUNT(*) FROM signatures WHERE person_id = ?', [$p['id']]); ?>
      <section><div class="gh"><span>Background check</span><span class="pill<?= bg_state($bg) === 'ok' ? ' pill-ok' : '' ?>"><?= e(BG_STATE_LABEL[bg_state($bg)]) ?></span></div><div class="group">
        <?php foreach ($bgs as $b): ?><div class="cell"><div class="grow"><strong><?= e(BG_STATUS[$b['status']] ?? $b['status']) ?><?= $b['provider'] ? ' · ' . e($b['provider']) : '' ?></strong><div class="muted small"><?= $b['completed_at'] ? 'Cleared ' . fdate($b['completed_at'], 'M j, Y') : 'Requested ' . fdate($b['requested_at'], 'M j, Y') ?><?= $b['expires_on'] ? ' · expires ' . fdate($b['expires_on'], 'M j, Y') : '' ?><?= $b['pco_id'] ? ' · from Planning Center' : '' ?></div></div></div><?php endforeach; ?>
        <?= $bgs ? '' : empty_state('None on file') ?>
      </div>
      <details class="add" style="margin-top:10px"><summary><?= $bg && $bg['status'] === 'requested' ? 'Update this check' : 'Record a check' ?></summary><div class="body">
        <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="bg_save"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="id" value="<?= $bg && $bg['status'] === 'requested' ? (int)$bg['id'] : 0 ?>">
          <div class="r2"><label class="lab">Status<select name="status"><?php foreach (BG_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= ($bg && $bg['status'] === 'requested' ? 'clear' : 'requested') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">Provider<input type="text" name="provider" value="<?= e($bg['provider'] ?? 'Protect My Ministry') ?>"></label></div>
          <div class="r2"><label class="lab">Cleared on<input type="date" name="completed_at"></label><label class="lab">Expires<input type="date" name="expires_on"></label></div>
          <label class="lab">Note<input type="text" name="note"></label>
          <button class="btn btn-dark" type="submit">Save</button><div class="muted small">Clear checks expire after 3 years unless you set a date.</div>
        </form></div></details>
      </section>
      <section><div class="gh"><span>Planning Center</span><?= $p['pco_id'] ? '<span class="pill pill-ok">Linked</span>' : '' ?></div>
      <?php if (!pco_api_ready()): ?><div class="group"><div class="cell"><span class="grow muted small">Connect Planning Center in Settings to link people and pull their details.</span></div></div>
      <?php elseif ($p['pco_id']): ?><div class="group"><div class="cell" style="flex-wrap:wrap"><div class="grow"><strong>Person #<?= e($p['pco_id']) ?></strong><div class="muted small"><?= $p['pco_synced_at'] ? 'Updated ' . fdate($p['pco_synced_at'], 'M j, g:i A') : 'Not pulled yet' ?></div></div>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="pco_pull"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>"><button class="link-btn">Pull latest</button></form>
          <form method="post" action="/action.php" class="inline" onsubmit="return confirm('Replace what is here with what is in Planning Center?')"><?= csrf() ?><input type="hidden" name="action" value="pco_pull"><input type="hidden" name="overwrite" value="1"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>"><button class="link-btn">Replace with Planning Center</button></form>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="pco_unlink"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>"><button class="link-btn danger">Unlink</button></form></div></div>
      <?php else: $pq = trim((string)($_GET['pco_q'] ?? '')); $found = []; $perr = '';
        if ($pq !== '') { try { $found = pco_search($pq); } catch (Throwable $e) { $perr = $e->getMessage(); } } ?>
        <form method="get" class="form" style="grid-template-columns:1fr auto;align-items:end"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><label class="lab">Find them in Planning Center<input type="search" name="pco_q" value="<?= e($pq ?: ($p['email'] ?: full_name($p))) ?>"></label><button class="btn" type="submit">Search</button></form>
        <?php if ($perr): ?><div class="note"><?= e($perr) ?></div><?php endif; ?>
        <?php if ($pq !== '' && !$perr): ?><div class="group" style="margin-top:10px"><?php foreach ($found as $r): ?>
          <form class="cell" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="pco_link"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="pco_id" value="<?= e($r['id']) ?>">
            <div class="grow"><strong><?= e(trim(($r['fields']['first_name'] ?? '') . ' ' . ($r['fields']['last_name'] ?? ''))) ?></strong><div class="muted small"><?= e($r['fields']['email'] ?? 'No email') ?><?= !empty($r['fields']['phone']) ? ' · ' . e($r['fields']['phone']) : '' ?></div></div><button class="btn" type="submit" style="height:34px">Link</button></form>
        <?php endforeach; ?><?= $found ? '' : empty_state('No one found') ?></div><?php endif; ?>
      <?php endif; ?></section>
      <section><div class="gh">Signed documents</div><div class="group"><a class="cell" href="/admin/signatures.php?person=<?= (int)$p['id'] ?>"><span class="grow"><?= $nsig ?> signed</span><span class="chev">›</span></a></div></section>
      <?php endif; ?>
      <?php if ($p): $gs = guardians((int)$p['id']); ?>
      <section><div class="gh"><span>Parents and guardians</span><?= $p['birth_date'] && is_minor($p, date('Y-m-d')) ? '<span class="muted small">Under 18</span>' : '' ?></div><div class="group">
        <?php foreach ($gs as $g): ?>
          <div class="cell" style="flex-wrap:wrap"><div class="grow"><strong><?= e($g['name']) ?></strong><div class="muted small"><?= e($g['rel']) ?><?= $g['email'] ? ' · ' . e($g['email']) : '' ?><?= $g['phone'] ? ' · ' . e($g['phone']) : '' ?></div></div>
            <div style="display:flex;gap:10px;width:100%;justify-content:flex-end;font-size:14px">
              <a href="#" data-copy="<?= e(parent_url($g)) ?>">Copy their link</a>
              <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="guardian_send"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn">Email it</button></form>
              <a href="/parent/?t=<?= e($g['token']) ?>" target="_blank">Preview</a>
              <form method="post" action="/action.php" class="inline" onsubmit="return confirm('Remove this parent? Their link stops working.')"><?= csrf() ?><input type="hidden" name="action" value="guardian_delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn danger">Remove</button></form>
            </div></div>
        <?php endforeach; ?>
        <?= $gs ? '' : empty_state('No parents added') ?>
      </div>
      <details class="add" style="margin-top:10px"><summary>Add a parent</summary><div class="body">
        <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="guardian_save"><input type="hidden" name="person_id" value="<?= (int)$p['id'] ?>">
          <label class="lab">Name<input type="text" name="name" value="<?= !$gs && $p['ec1_rel'] === 'Parent/Guardian' ? e($p['ec1_name']) : '' ?>" required></label>
          <div class="r2"><label class="lab">Email<input type="email" name="email" value="<?= !$gs && $p['ec1_rel'] === 'Parent/Guardian' ? e($p['ec1_email']) : '' ?>"></label><label class="lab">Phone<input type="tel" name="phone" value="<?= !$gs && $p['ec1_rel'] === 'Parent/Guardian' ? e($p['ec1_phone']) : '' ?>"></label></div>
          <input type="hidden" name="rel" value="Parent"><button class="btn btn-dark" type="submit">Add parent</button>
          <div class="muted small">They get a private page with the schedule, flights, packing list, contacts and updates. No password needed.</div>
        </form></div></details>
      </section>
      <?php endif; ?>
      <section><div class="gh">Their uploads</div><div class="group">
        <?php foreach ($uploads as $f): ?><a class="cell" href="/file.php?id=<?= (int)$f['id'] ?>"><div class="grow"><strong><?= e($f['title']) ?></strong><div class="muted small"><?= fdate($f['created_at'], 'M j, Y') ?></div></div><span class="chev">›</span></a><?php endforeach; ?>
        <?= $uploads ? '' : empty_state('Nothing uploaded') ?>
      </div></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
