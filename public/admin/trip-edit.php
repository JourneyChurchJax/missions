<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$t = isset($_GET['id']) ? trip((int)$_GET['id']) : null;
$all = all('SELECT id, name, start_date FROM trips ORDER BY start_date DESC');
page_open($t ? 'Edit ' . $t['name'] : 'New trip');
admin_header('trips');
$v = fn($k) => e($t[$k] ?? '');
?>
<main class="main" style="max-width:860px">
  <div class="bar-head"><div><a class="muted small" href="<?= $t ? '/admin/trip.php?id=' . (int)$t['id'] : '/admin/' ?>" style="text-decoration:none">‹ <?= $t ? e($t['name']) : 'All trips' ?></a><h1><?= $t ? 'Trip details' : 'Plan a trip' ?></h1></div></div>

  <form class="form tile xl" method="post" action="/action.php" style="padding:28px;gap:18px">
    <?= csrf() ?><input type="hidden" name="action" value="trip_save"><input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
    <?php if (!$t): ?>
      <label class="lab">Start from
        <select name="copy_from"><option value="0">A blank trip</option><?php foreach ($all as $x): ?><option value="<?= (int)$x['id'] ?>">Copy <?= e($x['name']) ?> (<?= fdate($x['start_date'], 'Y') ?>): tasks, goals, budget, guide, documents</option><?php endforeach; ?></select>
      </label>
    <?php endif; ?>
    <div class="r2">
      <label class="lab">Trip name<input type="text" name="name" value="<?= $v('name') ?>" placeholder="Belize" required></label>
      <label class="lab">Public name<input type="text" name="public_name" value="<?= $v('public_name') ?>" placeholder="Belize 2027"></label>
    </div>
    <div class="r3">
      <label class="lab">Leaves<input type="date" name="start_date" value="<?= $v('start_date') ?>" required></label>
      <label class="lab">Returns<input type="date" name="end_date" value="<?= $v('end_date') ?>" required></label>
      <label class="lab">Applications close<input type="date" name="app_deadline" value="<?= $v('app_deadline') ?>"></label>
    </div>
    <div class="r3">
      <label class="lab">City<input type="text" name="city" value="<?= $v('city') ?>"></label>
      <label class="lab">Country<input type="text" name="country" value="<?= $v('country') ?>"></label>
      <label class="lab">Partner<input type="text" name="partner" value="<?= $v('partner') ?>" placeholder="Adventures in Missions"></label>
    </div>
    <div class="r3">
      <label class="lab">Cost per traveler<input type="number" step="0.01" name="cost_per_person" value="<?= $v('cost_per_person') ?>"></label>
      <label class="lab">Max team size<input type="number" name="max_team" value="<?= $v('max_team') ?>"></label>
      <label class="lab">Passport valid through<input type="date" name="passport_valid_through" value="<?= $v('passport_valid_through') ?>"></label>
    </div>
    <div class="r2"><label class="lab">Group<input type="text" name="group_name" value="<?= $v('group_name') ?>" placeholder="Students, Adults, Central America"></label>
      <label class="lab">Background checks required for<select name="bg_required"><?php foreach (BG_RULES as $k => $l): ?><option value="<?= $k ?>"<?= ($t['bg_required'] ?? 'leaders') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label></div>
    <label class="lab">About this trip<textarea name="description" rows="4"><?= $v('description') ?></textarea></label>
    <label class="lab">Who can go (one per line)<textarea name="qualifications" rows="5"><?= $v('qualifications') ?></textarea></label>
    <div class="actions"><a class="btn" href="<?= $t ? '/admin/trip.php?id=' . (int)$t['id'] : '/admin/' ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $t ? 'Save' : 'Create trip' ?></button></div>
  </form>

  <?php if ($t): ?>
  <form class="tile" method="post" action="/action.php" style="flex-direction:row;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <?= csrf() ?><input type="hidden" name="action" value="trip_status"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <div><strong>Trip status</strong><div class="muted small">Cancelled and postponed trips move off the home page. Trips archive 30 days after they return.</div></div>
    <div style="display:flex;gap:8px">
      <?php foreach (['active' => 'Active', 'postponed' => 'Postponed', 'cancelled' => 'Cancelled'] as $k => $l): ?>
        <button class="btn<?= $t['status'] === $k ? ' btn-dark' : '' ?>" name="status" value="<?= $k ?>" type="submit"><?= $l ?></button>
      <?php endforeach; ?>
    </div>
  </form>

  <?php $photos = trip_photos((int)$t['id']); ?>
  <section class="tile xl" style="gap:16px" id="photos">
    <div><strong style="font-size:18px">Trip photos</strong><div class="muted small">The first photo is the cover on the trips page and the trip banner. Travelers see up to five on their trip page.</div></div>
    <?php if ($photos): ?>
    <div class="photo-grid">
      <?php foreach ($photos as $i => $ph): ?>
        <figure><img src="<?= e($ph['src']) ?>" alt="<?= e($ph['title']) ?>" loading="lazy"><?php if ($i === 0): ?><span class="cover">Cover</span><?php endif; ?>
          <figcaption>
            <?php if ($i > 0): ?><form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="photo_first"><input type="hidden" name="id" value="<?= $ph['id'] ?>"><button type="submit">Make cover</button></form><?php endif; ?>
            <form method="post" action="/action.php" onsubmit="return confirm('Remove this photo?')"><?= csrf() ?><input type="hidden" name="action" value="file_delete"><input type="hidden" name="id" value="<?= $ph['id'] ?>"><button type="submit">Remove</button></form>
          </figcaption></figure>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form class="form" method="post" action="/action.php" enctype="multipart/form-data" style="grid-template-columns:1fr auto;align-items:end">
      <?= csrf() ?><input type="hidden" name="action" value="photo_upload"><input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
      <label class="lab">Add photos (choose several at once)<input type="file" name="photos[]" accept="image/*" multiple></label>
      <button class="btn btn-dark" type="submit">Upload</button>
    </form>
  </section>
  <?php endif; ?>
</main>
<?php page_close(); ?>
