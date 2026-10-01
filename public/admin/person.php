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
