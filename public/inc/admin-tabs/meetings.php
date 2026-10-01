<?php
// Trip workspace · Meetings
$meetings = all('SELECT * FROM meetings WHERE trip_id = ? ORDER BY starts_at', [$id]);
$present = [];
foreach (all('SELECT a.* FROM attendance a JOIN meetings m ON m.id = a.meeting_id WHERE m.trip_id = ?', [$id]) as $a) $present[$a['meeting_id']][$a['person_id']] = true;
?>
<div class="split">
  <section style="display:flex;flex-direction:column;gap:16px;min-width:0">
  <?php foreach ($meetings as $mt): $past = strtotime($mt['starts_at']) < time(); $here_n = count($present[$mt['id']] ?? []); ?>
    <div class="tile" style="gap:12px">
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <?= date_box_for($mt['starts_at']) ?>
        <div class="grow" style="flex-grow:1"><strong style="font-size:17px"><?= e($mt['title']) ?></strong><div class="muted small"><?= fdate($mt['starts_at'], 'l, F j · g:i A') ?><?= $mt['ends_at'] ? '–' . fdate($mt['ends_at'], 'g:i A') : '' ?><?= $mt['location'] ? ' · ' . e($mt['location']) : '' ?></div></div>
        <span class="pill<?= $past ? '' : ' pill-ok' ?>"><?= $past ? "$here_n of " . count($trav) . ' came' : 'Upcoming' ?></span>
      </div>
      <?php if ($mt['notes']): ?><div class="muted"><?= soft($mt['notes']) ?></div><?php endif; ?>
      <details class="edit"><summary>Take attendance</summary>
        <form class="form" method="post" action="/action.php" style="padding-top:10px">
          <?= csrf() ?><input type="hidden" name="action" value="attendance_save"><input type="hidden" name="id" value="<?= (int)$mt['id'] ?>">
          <div class="r3"><?php foreach ($team as $m): ?><label class="chk"><input type="checkbox" name="present[]" value="<?= (int)$m['person_id'] ?>"<?= isset($present[$mt['id']][$m['person_id']]) ? ' checked' : '' ?>> <?= e(full_name($m)) ?></label><?php endforeach; ?></div>
          <div class="actions"><button class="btn btn-dark" type="submit">Save attendance</button></div>
        </form>
      </details>
      <details class="edit"><summary>Edit meeting</summary>
        <form class="form" method="post" action="/action.php" style="padding-top:10px">
          <?= csrf() ?><input type="hidden" name="action" value="meeting_save"><input type="hidden" name="id" value="<?= (int)$mt['id'] ?>"><input type="hidden" name="trip_id" value="<?= $id ?>">
          <?php $mtg = $mt; include __DIR__ . '/_meeting_fields.php'; ?>
          <div class="actions"><button class="btn btn-dark" type="submit">Save</button></div>
        </form>
        <form method="post" action="/action.php" onsubmit="return confirm('Delete this meeting?')" style="text-align:right;padding-top:8px"><?= csrf() ?><input type="hidden" name="action" value="meeting_delete"><input type="hidden" name="id" value="<?= (int)$mt['id'] ?>"><button class="link-btn danger">Delete meeting</button></form>
      </details>
    </div>
  <?php endforeach; ?>
  <?= $meetings ? '' : '<div class="group"><div class="empty">No meetings yet.</div></div>' ?>
  </section>
  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Schedule a meeting</summary><div class="body">
      <form class="form" method="post" action="/action.php">
        <?= csrf() ?><input type="hidden" name="action" value="meeting_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <?php $mtg = ['title' => 'Team meeting']; include __DIR__ . '/_meeting_fields.php'; ?>
        <button class="btn btn-dark" type="submit">Add meeting</button>
      </form>
    </div></details>
    <section class="note"><strong>Travelers see it right away</strong><div class="muted small">Meetings show on each traveler's home and schedule. Calendar subscriptions and email reminders come in phase 4.</div></section>
  </aside>
</div>
