<?php
// Trip workspace · Team
$not_on = all('SELECT * FROM people WHERE id NOT IN (SELECT person_id FROM members WHERE trip_id = ?) ORDER BY last_name, first_name', [$id]);
?>
<div class="split">
  <section style="display:flex;flex-direction:column;gap:12px;min-width:0">
    <div class="gh"><span><?= count($team) ?> people · <?= count($trav) ?> traveling</span><a href="/admin/reports.php?trip=<?= $id ?>">Roster, rooming and exports</a></div>
    <div class="group">
    <?php foreach ($team as $m): [$d, $n] = readiness($id, (int)$m['person_id']); ?>
      <div class="cell" style="flex-wrap:wrap">
        <span class="av<?= $m['role'] !== 'traveler' ? ' dark' : '' ?>"><?= initials(full_name($m)) ?></span>
        <div class="grow"><a href="/admin/person.php?id=<?= (int)$m['person_id'] ?>&trip=<?= $id ?>" style="font-weight:600;text-decoration:none"><?= e(full_name($m)) ?></a>
          <div class="muted small"><?= e(ucfirst($m['role'])) ?><?= $m['traveling'] ? '' : ' · not traveling' ?> · <?= $d ?>/<?= $n ?> ready<?= $m['room'] ? ' · Room ' . e($m['room']) : '' ?><?= $m['confirmation'] ? ' · Flight conf. ' . e($m['confirmation']) : '' ?></div></div>
        <details class="edit"><summary>Edit</summary>
          <form class="form" method="post" action="/action.php" style="padding-top:12px">
            <?= csrf() ?><input type="hidden" name="action" value="member_update"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <div class="r3">
              <label class="lab">Role<select name="role"><?php foreach (['traveler' => 'Traveler', 'leader' => 'Leader', 'admin' => 'Trip admin'] as $k => $l): ?><option value="<?= $k ?>"<?= $m['role'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
              <label class="lab">Personal goal<input type="number" step="0.01" name="goal" value="<?= e($m['goal']) ?>" placeholder="<?= e($t['cost_per_person']) ?>"></label>
              <label class="lab">Flight confirmation<input type="text" name="confirmation" value="<?= e($m['confirmation']) ?>"></label>
            </div>
            <div class="r3">
              <label class="lab">Room<input type="text" name="room" value="<?= e($m['room']) ?>" placeholder="Girls 1"></label>
              <label class="lab">Van or seat<input type="text" name="seat" value="<?= e($m['seat']) ?>" placeholder="Van A"></label>
              <label class="chk" style="align-self:end;min-height:44px;align-items:center"><input type="checkbox" name="traveling" value="1"<?= $m['traveling'] ? ' checked' : '' ?>> Traveling with the team</label>
            </div>
            <div class="actions"><button class="btn btn-dark" type="submit">Save</button></div>
          </form>
          <form method="post" action="/action.php" data-confirm="Remove <?= e(full_name($m)) ?> from this trip?" class="row-between" style="padding-top:8px">
            <?= csrf() ?><input type="hidden" name="action" value="member_remove"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?php if ((float)$m['raised'] > 0): ?><label class="chk small"><input type="checkbox" name="move_gifts" value="1"> Move their gifts to the team</label><?php else: ?><span></span><?php endif; ?><button class="link-btn danger" type="submit">Remove from trip</button>
          </form>
        </details>
      </div>
    <?php endforeach; ?>
    <?= $team ? '' : '<div class="empty">No one on this trip yet.</div>' ?>
    </div>
  </section>

  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Add someone already in the system</summary><div class="body">
      <form class="form" method="post" action="/action.php">
        <?= csrf() ?><input type="hidden" name="action" value="member_add"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <label class="lab">Person<select name="person_id" required><option value="">Choose…</option><?php foreach ($not_on as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e(full_name($p)) ?></option><?php endforeach; ?></select></label>
        <label class="lab">Role<select name="role"><option value="traveler">Traveler</option><option value="leader">Leader</option><option value="admin">Trip admin</option></select></label>
        <label class="chk"><input type="checkbox" name="traveling" value="1" checked> Traveling with the team</label>
        <button class="btn btn-dark" type="submit">Add to team</button>
      </form>
    </div></details>
    <details class="add"><summary>Add someone new</summary><div class="body">
      <form class="form" method="post" action="/action.php">
        <?= csrf() ?><input type="hidden" name="action" value="member_add"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <div class="r2"><label class="lab">First name<input type="text" name="first_name" required></label><label class="lab">Last name<input type="text" name="last_name" required></label></div>
        <label class="lab">Email<input type="email" name="email"></label>
        <label class="lab">Role<select name="role"><option value="traveler">Traveler</option><option value="leader">Leader</option><option value="admin">Trip admin</option></select></label>
        <label class="chk"><input type="checkbox" name="traveling" value="1" checked> Traveling with the team</label>
        <button class="btn btn-dark" type="submit">Add to team</button>
        <div class="muted small">Tip: people already in Planning Center can be added from People → Add from Planning Center, so their details fill in.</div>
      </form>
    </div></details>
    <section class="note"><strong>Roles</strong><div class="muted small">Trip admins see and change everything on this trip. Leaders see what you allow in Settings → Leader permissions. Travelers only see their own information.</div></section>
  </aside>
</div>
